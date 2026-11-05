<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$entity = 1;
$oldRef = 'SO-2026-11-0003';
$newRef = 'SO-2026-11-0002';
$scope = '2026-11';
$orderId = 47;
$columns = array(
	'actioncomm' => array('label', 'note'),
	'commande' => array('ref', 'last_main_doc'),
	'ecm_files' => array('filepath', 'filename', 'fullpath_orig'),
);

function failGapFix($message)
{
	fwrite(STDERR, 'ERROR: '.$message."\n");
	exit(1);
}

function matchingRowsGapFix($db, $table, $columns, $reference)
{
	$where = array();
	foreach ($columns as $column) $where[] = '`'.$column."` LIKE '%".$db->escape($reference)."%'";
	$res = $db->query('SELECT * FROM `'.MAIN_DB_PREFIX.$table.'` WHERE '.implode(' OR ', $where));
	if (!$res) throw new RuntimeException($db->lasterror());
	$rows = array();
	while ($row = $db->fetch_array($res)) $rows[] = $row;
	return $rows;
}

$oldResult = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX."commande WHERE entity=".$entity." AND rowid=".$orderId." AND ref='".$db->escape($oldRef)."'");
$newResult = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX."commande WHERE entity=".$entity." AND ref='".$db->escape($newRef)."'");
if (!$oldResult || $db->num_rows($oldResult) !== 1) failGapFix('Sales Order sumber tidak ditemukan atau sudah berubah.');
if (!$newResult || $db->num_rows($newResult) !== 0) failGapFix('Nomor tujuan sudah digunakan.');

$ns = new NamingSeries($db, $entity);
$series = $ns->resolveSeries('sales_order');
if (!$series) failGapFix('Naming Series Sales Order tidak ditemukan.');

$oldDirectory = DOL_DATA_ROOT.'/commande/'.$oldRef;
$newDirectory = DOL_DATA_ROOT.'/commande/'.$newRef;
if (file_exists($newDirectory)) failGapFix('Folder tujuan sudah ada: '.$newDirectory);

$backup = array(
	'created_at' => date(DATE_ATOM),
	'old_ref' => $oldRef,
	'new_ref' => $newRef,
	'order_id' => $orderId,
	'counter_before' => $ns->currentValue((int) $series->rowid, $scope),
	'tables' => array(),
);
foreach ($columns as $table => $tableColumns) {
	$backup['tables'][$table] = matchingRowsGapFix($db, $table, $tableColumns, $oldRef);
}

echo ($apply ? 'APPLY' : 'DRY-RUN')."\n";
echo $oldRef.' -> '.$newRef."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
echo 'Counter: '.$backup['counter_before']." -> 2\n";
if (!$apply) exit(0);

$backupDirectory = DOL_DATA_ROOT.'/namingseries_backups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true)) failGapFix('Folder backup tidak dapat dibuat.');
$stamp = date('Ymd_His');
$backupFile = $backupDirectory.'/fix_sales_order_gap_'.$stamp.'.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) failGapFix('Backup database tidak dapat ditulis.');
if (is_file($oldDirectory.'/'.$oldRef.'.pdf')) {
	if (!copy($oldDirectory.'/'.$oldRef.'.pdf', $backupDirectory.'/'.$oldRef.'_'.$stamp.'.pdf')) failGapFix('Backup PDF tidak dapat dibuat.');
}

$directoryRenamed = false;
$db->begin();
try {
	foreach ($columns as $table => $tableColumns) {
		foreach ($tableColumns as $column) {
			$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'".$db->escape($oldRef)."','".$db->escape($newRef)."')"
				." WHERE `".$column."` LIKE '%".$db->escape($oldRef)."%'";
			if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
		}
	}
	$ns->setCounter((int) $series->rowid, $scope, 2, 0);
	$ns->audit((int) $series->rowid, 'RENUMBER', $scope, $oldRef, $newRef, 'sales_order', $orderId, $newRef, 0);

	if (is_dir($oldDirectory)) {
		if (!rename($oldDirectory, $newDirectory)) throw new RuntimeException('Folder dokumen gagal diubah.');
		$directoryRenamed = true;
		$oldPdf = $newDirectory.'/'.$oldRef.'.pdf';
		$newPdf = $newDirectory.'/'.$newRef.'.pdf';
		if (is_file($oldPdf) && !rename($oldPdf, $newPdf)) throw new RuntimeException('Nama PDF gagal diubah.');
	}

	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
} catch (Throwable $e) {
	$db->rollback();
	if ($directoryRenamed && is_dir($newDirectory) && !file_exists($oldDirectory)) @rename($newDirectory, $oldDirectory);
	failGapFix($e->getMessage());
}

// Regenerate the PDF so its visible content also uses the corrected reference.
$order = new Commande($db);
if ($order->fetch($orderId) <= 0) failGapFix('Nomor berhasil diubah, tetapi Sales Order gagal dimuat untuk regenerasi PDF.');
$model = !empty($order->model_pdf) ? $order->model_pdf : 'eratosthene';
if ($order->generateDocument($model, $langs) <= 0) failGapFix('Nomor berhasil diubah, tetapi PDF gagal dibuat ulang: '.$order->error);

echo 'BACKUP='.$backupFile."\n";
echo "SUCCESS\n";
