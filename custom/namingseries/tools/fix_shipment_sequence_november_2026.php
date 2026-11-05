<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformatgenerator.class.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$entity = 1;
$scope = '2026-11';
$root = DOL_DATA_ROOT.'/expedition/sending';
$mappings = array(
	array('id' => 42, 'old' => 'BBK-2026-11-0004', 'new' => 'BBK-2026-11-0001', 'tmp' => '__NS_BBK_42__', 'formats' => array(68, 50)),
	array('id' => 46, 'old' => 'BBK-2026-11-0001', 'new' => 'BBK-2026-11-0002', 'tmp' => '__NS_BBK_46__', 'formats' => array(68, 42)),
	array('id' => 47, 'old' => 'BBK-2026-11-0002', 'new' => 'BBK-2026-11-0003', 'tmp' => '__NS_BBK_47__', 'formats' => array(68, 50, 42)),
	array('id' => 48, 'old' => 'BBK-2026-11-0003', 'new' => 'BBK-2026-11-0004', 'tmp' => '__NS_BBK_48__', 'formats' => array(68, 50, 42)),
);
$columns = array(
	'accounting_bookkeeping' => array('doc_ref', 'label_operation'),
	'actioncomm' => array('label', 'note'),
	'ecm_files' => array('filepath', 'filename', 'fullpath_orig'),
	'expedition' => array('ref', 'last_main_doc'),
	'ns_audit' => array('document_ref'),
	'pi_entry' => array('note'),
	'stock_mouvement' => array('label'),
);

function failFixShipment($message)
{
	fwrite(STDERR, 'ERROR: '.$message."\n");
	exit(1);
}

function rowsContainingFixShipment($db, $table, $columns, $references)
{
	$where = array();
	foreach ($columns as $column) {
		foreach ($references as $reference) $where[] = '`'.$column."` LIKE '%".$db->escape($reference)."%'";
	}
	$res = $db->query('SELECT * FROM `'.MAIN_DB_PREFIX.$table.'` WHERE '.implode(' OR ', $where));
	if (!$res) throw new RuntimeException($db->lasterror());
	$rows = array();
	while ($row = $db->fetch_array($res)) $rows[] = $row;
	return $rows;
}

function copyTreeFixShipment($source, $target)
{
	if (!is_dir($target) && !mkdir($target, 0750, true)) throw new RuntimeException('Gagal membuat backup '.$target);
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
	foreach ($iterator as $entry) {
		$destination = $target.'/'.substr($entry->getPathname(), strlen($source) + 1);
		if ($entry->isDir()) {
			if (!is_dir($destination) && !mkdir($destination, 0750, true)) throw new RuntimeException('Gagal membuat backup '.$destination);
		} elseif (!copy($entry->getPathname(), $destination)) {
			throw new RuntimeException('Gagal menyalin backup '.$entry->getPathname());
		}
	}
}

function renameFilesFixShipment($directory, $from, $to)
{
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $entry) {
		if (strpos($entry->getBasename(), $from) === false) continue;
		$target = $entry->getPath().'/'.str_replace($from, $to, $entry->getBasename());
		if (file_exists($target) || !rename($entry->getPathname(), $target)) throw new RuntimeException('Gagal mengubah nama file '.$entry->getPathname());
	}
}

function replaceDatabaseFixShipment($db, $columns, $from, $to)
{
	foreach ($columns as $table => $tableColumns) {
		foreach ($tableColumns as $column) {
			$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'".$db->escape($from)."','".$db->escape($to)."')"
				." WHERE `".$column."` LIKE '%".$db->escape($from)."%'";
			if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
		}
	}
}

$oldReferences = array_column($mappings, 'old');
foreach ($mappings as $mapping) {
	$res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'expedition WHERE entity='.$entity.' AND rowid='.(int) $mapping['id']." AND ref='".$db->escape($mapping['old'])."'");
	if (!$res || $db->num_rows($res) !== 1) failFixShipment('Shipment sumber tidak ditemukan atau sudah berubah: '.$mapping['old']);
	if (!is_dir($root.'/'.$mapping['old'])) failFixShipment('Folder dokumen tidak ditemukan: '.$root.'/'.$mapping['old']);
}

$ns = new NamingSeries($db, $entity);
$series = $ns->resolveSeries('shipment');
if (!$series) failFixShipment('Naming Series shipment tidak ditemukan.');
$backup = array(
	'created_at' => date(DATE_ATOM),
	'mappings' => $mappings,
	'counter_before' => $ns->currentValue((int) $series->rowid, $scope),
	'tables' => array(),
);
foreach ($columns as $table => $tableColumns) $backup['tables'][$table] = rowsContainingFixShipment($db, $table, $tableColumns, $oldReferences);

echo ($apply ? 'APPLY' : 'DRY-RUN')."\n";
foreach ($mappings as $mapping) echo '#'.$mapping['id'].' '.$mapping['old'].' -> '.$mapping['new']."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
echo 'Shipment counter: '.$backup['counter_before']." -> 4\n";
if (!$apply) exit(0);

$backupDirectory = DOL_DATA_ROOT.'/namingseries_backups/fix_shipment_sequence_'.date('Ymd_His');
if (!mkdir($backupDirectory, 0750, true)) failFixShipment('Folder backup tidak dapat dibuat: '.$backupDirectory);
if (file_put_contents($backupDirectory.'/database.json', json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) failFixShipment('Backup database tidak dapat ditulis.');
try {
	foreach ($mappings as $mapping) copyTreeFixShipment($root.'/'.$mapping['old'], $backupDirectory.'/documents/'.$mapping['old']);
} catch (Throwable $e) {
	failFixShipment($e->getMessage());
}

$filesystemState = array();
$db->begin();
try {
	foreach ($mappings as $index => $mapping) {
		$temporary = $root.'/.namingseries_'.$mapping['tmp'];
		if (file_exists($temporary) || !rename($root.'/'.$mapping['old'], $temporary)) throw new RuntimeException('Folder gagal dipindah sementara: '.$mapping['old']);
		$filesystemState[$index] = array('stage' => 'temporary', 'path' => $temporary);
	}
	foreach ($mappings as $mapping) replaceDatabaseFixShipment($db, $columns, $mapping['old'], $mapping['tmp']);
	foreach ($mappings as $mapping) replaceDatabaseFixShipment($db, $columns, $mapping['tmp'], $mapping['new']);
	foreach ($mappings as $index => $mapping) {
		$temporary = $filesystemState[$index]['path'];
		renameFilesFixShipment($temporary, $mapping['old'], $mapping['new']);
		$destination = $root.'/'.$mapping['new'];
		if (file_exists($destination) || !rename($temporary, $destination)) throw new RuntimeException('Folder tujuan gagal dibuat: '.$destination);
		$filesystemState[$index] = array('stage' => 'final', 'path' => $destination);
	}
	$ns->setCounter((int) $series->rowid, $scope, 4, 0);
	foreach ($mappings as $mapping) $ns->audit((int) $series->rowid, 'RENUMBER', $scope, $mapping['old'], $mapping['new'], 'shipment', $mapping['id'], $mapping['new'], 0);
	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
} catch (Throwable $e) {
	$db->rollback();
	foreach (array_reverse(array_keys($filesystemState)) as $index) {
		$mapping = $mappings[$index];
		$current = $filesystemState[$index]['path'];
		$original = $root.'/'.$mapping['old'];
		if ($filesystemState[$index]['stage'] === 'final' && is_dir($current)) renameFilesFixShipment($current, $mapping['new'], $mapping['old']);
		if (is_dir($current) && !file_exists($original)) @rename($current, $original);
	}
	failFixShipment($e->getMessage());
}

try {
	foreach ($mappings as $mapping) {
		$object = new Expedition($db);
		if ($object->fetch($mapping['id']) <= 0) throw new RuntimeException('Shipment '.$mapping['id'].' gagal dimuat untuk regenerasi PDF.');
		if ($object->generateDocument('rouget', $langs) <= 0) throw new RuntimeException('PDF shipment '.$object->ref.' gagal dibuat ulang: '.$object->error);
		foreach ($mapping['formats'] as $formatId) {
			$generator = new PrintFormatDocumentGenerator($db);
			if ($generator->write($object, $langs, 'shipment', $formatId) <= 0) throw new RuntimeException('Print Format '.$formatId.' untuk '.$object->ref.' gagal dibuat ulang: '.$generator->error);
		}
	}
} catch (Throwable $e) {
	failFixShipment('Nomor sudah berhasil diubah dan backup tersedia, tetapi '.$e->getMessage());
}

echo 'BACKUP='.$backupDirectory."\n";
echo "SUCCESS\n";
