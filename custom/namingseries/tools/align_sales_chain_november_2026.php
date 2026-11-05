<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformatgenerator.class.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$entity = 1;
$scope = '2026-11';

// Compact each downstream series independently for November. Sales Orders are
// intentionally left unchanged; Shipment and Customer Invoice each start at 0001.
$mappings = array(
	array('type' => 'shipment', 'table' => 'expedition', 'id' => 46, 'old' => 'BBK-2026-11-0002', 'new' => 'BBK-2026-11-0001', 'root' => DOL_DATA_ROOT.'/expedition/sending', 'tmp' => '__NS_BBK_46__'),
	array('type' => 'shipment', 'table' => 'expedition', 'id' => 47, 'old' => 'BBK-2026-11-0003', 'new' => 'BBK-2026-11-0002', 'root' => DOL_DATA_ROOT.'/expedition/sending', 'tmp' => '__NS_BBK_47__'),
	array('type' => 'shipment', 'table' => 'expedition', 'id' => 48, 'old' => 'BBK-2026-11-0004', 'new' => 'BBK-2026-11-0003', 'root' => DOL_DATA_ROOT.'/expedition/sending', 'tmp' => '__NS_BBK_48__'),
	array('type' => 'customer_invoice', 'table' => 'facture', 'id' => 37, 'old' => 'SJ-2026-11-0002', 'new' => 'SJ-2026-11-0001', 'root' => DOL_DATA_ROOT.'/facture', 'tmp' => '__NS_SJ_37__'),
	array('type' => 'customer_invoice', 'table' => 'facture', 'id' => 38, 'old' => 'SJ-2026-11-0003', 'new' => 'SJ-2026-11-0002', 'root' => DOL_DATA_ROOT.'/facture', 'tmp' => '__NS_SJ_38__'),
);

$columns = array(
	'accounting_bookkeeping' => array('doc_ref', 'label_operation'),
	'actioncomm' => array('label', 'note'),
	'ecm_files' => array('filepath', 'filename', 'fullpath_orig'),
	'expedition' => array('ref', 'last_main_doc'),
	'facture' => array('ref', 'last_main_doc'),
	// Keep old_value/new_value immutable as audit history; only the live document
	// reference follows a renumbered document.
	'ns_audit' => array('document_ref'),
	'pi_entry' => array('note'),
	'stock_mouvement' => array('label'),
);

function failAlign($message)
{
	fwrite(STDERR, 'ERROR: '.$message."\n");
	exit(1);
}

function rowsContainingAlign($db, $table, $columns, $references)
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

function copyTreeAlign($source, $target)
{
	if (!is_dir($source)) return;
	if (!is_dir($target) && !mkdir($target, 0750, true)) throw new RuntimeException('Gagal membuat backup '.$target);
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($iterator as $entry) {
		$relative = substr($entry->getPathname(), strlen($source) + 1);
		$destination = $target.'/'.$relative;
		if ($entry->isDir()) {
			if (!is_dir($destination) && !mkdir($destination, 0750, true)) throw new RuntimeException('Gagal membuat backup '.$destination);
		} elseif (!copy($entry->getPathname(), $destination)) {
			throw new RuntimeException('Gagal menyalin backup '.$entry->getPathname());
		}
	}
}

function renameFilesAlign($directory, $from, $to)
{
	if (!is_dir($directory)) return;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iterator as $entry) {
		$name = $entry->getBasename();
		if (strpos($name, $from) === false) continue;
		$target = $entry->getPath().'/'.str_replace($from, $to, $name);
		if (file_exists($target) || !rename($entry->getPathname(), $target)) {
			throw new RuntimeException('Gagal mengubah nama file '.$entry->getPathname());
		}
	}
}

function replaceDatabaseAlign($db, $columns, $from, $to)
{
	foreach ($columns as $table => $tableColumns) {
		foreach ($tableColumns as $column) {
			$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'".$db->escape($from)."','".$db->escape($to)."')"
				." WHERE `".$column."` LIKE '%".$db->escape($from)."%'";
			if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
		}
	}
}

function regenerateDocumentsAlign($db, $langs)
{
	$jobs = array(
		array('type' => 'shipment', 'id' => 46, 'formats' => array(68, 42)),
		array('type' => 'shipment', 'id' => 47, 'formats' => array(68, 50, 42)),
		array('type' => 'shipment', 'id' => 48, 'formats' => array(68, 50, 42)),
		array('type' => 'customer_invoice', 'id' => 37, 'formats' => array(57)),
		array('type' => 'customer_invoice', 'id' => 38, 'formats' => array(57)),
	);
	foreach ($jobs as $job) {
		if ($job['type'] === 'shipment') {
			$object = new Expedition($db);
			if ($object->fetch($job['id']) <= 0) throw new RuntimeException('Shipment '.$job['id'].' gagal dimuat untuk regenerasi PDF.');
			// Refresh the native reference PDF too. Custom variants are generated below.
			if ($object->generateDocument('rouget', $langs) <= 0) throw new RuntimeException('PDF shipment '.$object->ref.' gagal dibuat ulang: '.$object->error);
			$doctype = 'shipment';
		} else {
			$object = new Facture($db);
			if ($object->fetch($job['id']) <= 0) throw new RuntimeException('Invoice '.$job['id'].' gagal dimuat untuk regenerasi PDF.');
			$doctype = 'invoice';
		}
		foreach ($job['formats'] as $formatId) {
			$generator = new PrintFormatDocumentGenerator($db);
			if ($generator->write($object, $langs, $doctype, $formatId) <= 0) {
				throw new RuntimeException('Print Format '.$formatId.' untuk '.$object->ref.' gagal dibuat ulang: '.$generator->error);
			}
		}
	}
}

$oldReferences = array_column($mappings, 'old');
$newReferences = array_column($mappings, 'new');
foreach ($mappings as $mapping) {
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE entity='.$entity.' AND rowid='.(int) $mapping['id']
		." AND ref='".$db->escape($mapping['old'])."'";
	$res = $db->query($sql);
	if (!$res || $db->num_rows($res) !== 1) failAlign('Dokumen sumber tidak ditemukan atau sudah berubah: '.$mapping['old']);
	$tmpRes = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$mapping['table']." WHERE entity=".$entity." AND ref='".$db->escape($mapping['tmp'])."'");
	if (!$tmpRes || $db->num_rows($tmpRes) !== 0) failAlign('Referensi sementara sudah digunakan: '.$mapping['tmp']);
	$sourceDirectory = $mapping['root'].'/'.$mapping['old'];
	if (!is_dir($sourceDirectory)) failAlign('Folder dokumen tidak ditemukan: '.$sourceDirectory);
}

// A destination may currently be another source in the same atomic mapping.
foreach ($mappings as $mapping) {
	$res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$mapping['table']." WHERE entity=".$entity." AND ref='".$db->escape($mapping['new'])."'");
	if (!$res) failAlign($db->lasterror());
	while ($row = $db->fetch_object($res)) {
		$allowed = false;
		foreach ($mappings as $source) {
			if ($source['table'] === $mapping['table'] && (int) $source['id'] === (int) $row->rowid) $allowed = true;
		}
		if (!$allowed) failAlign('Nomor tujuan sudah digunakan di luar mapping: '.$mapping['new']);
	}
}

$ns = new NamingSeries($db, $entity);
$shipmentSeries = $ns->resolveSeries('shipment');
$invoiceSeries = $ns->resolveSeries('customer_invoice');
if (!$shipmentSeries || !$invoiceSeries) failAlign('Naming Series shipment atau customer invoice tidak ditemukan.');

$backup = array(
	'created_at' => date(DATE_ATOM),
	'mappings' => $mappings,
	'counters_before' => array(
		'shipment' => $ns->currentValue((int) $shipmentSeries->rowid, $scope),
		'customer_invoice' => $ns->currentValue((int) $invoiceSeries->rowid, $scope),
	),
	'tables' => array(),
);
foreach ($columns as $table => $tableColumns) $backup['tables'][$table] = rowsContainingAlign($db, $table, $tableColumns, $oldReferences);

echo ($apply ? 'APPLY' : 'DRY-RUN')."\n";
foreach ($mappings as $mapping) echo $mapping['old'].' -> '.$mapping['new']."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
echo 'Shipment counter: '.$backup['counters_before']['shipment']." -> 3\n";
echo 'Customer invoice counter: '.$backup['counters_before']['customer_invoice']." -> 2\n";
if (!$apply) exit(0);

$backupDirectory = DOL_DATA_ROOT.'/namingseries_backups/align_sales_chain_'.date('Ymd_His');
if (!mkdir($backupDirectory, 0750, true)) failAlign('Folder backup tidak dapat dibuat: '.$backupDirectory);
$backupFile = $backupDirectory.'/database.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
	failAlign('Backup database tidak dapat ditulis.');
}
try {
	foreach ($mappings as $mapping) copyTreeAlign($mapping['root'].'/'.$mapping['old'], $backupDirectory.'/documents/'.$mapping['old']);
} catch (Throwable $e) {
	failAlign($e->getMessage());
}

$filesystemState = array();
$db->begin();
try {
	// Move every source away first so overlapping destinations cannot collide.
	foreach ($mappings as $index => $mapping) {
		$source = $mapping['root'].'/'.$mapping['old'];
		$temporary = $mapping['root'].'/.namingseries_'.$mapping['tmp'];
		if (file_exists($temporary) || !rename($source, $temporary)) throw new RuntimeException('Folder gagal dipindah sementara: '.$source);
		$filesystemState[$index] = array('stage' => 'temporary', 'path' => $temporary);
	}
	foreach ($mappings as $mapping) replaceDatabaseAlign($db, $columns, $mapping['old'], $mapping['tmp']);
	foreach ($mappings as $mapping) replaceDatabaseAlign($db, $columns, $mapping['tmp'], $mapping['new']);

	foreach ($mappings as $index => $mapping) {
		$temporary = $filesystemState[$index]['path'];
		renameFilesAlign($temporary, $mapping['old'], $mapping['new']);
		$destination = $mapping['root'].'/'.$mapping['new'];
		if (file_exists($destination) || !rename($temporary, $destination)) throw new RuntimeException('Folder tujuan gagal dibuat: '.$destination);
		$filesystemState[$index] = array('stage' => 'final', 'path' => $destination);
	}

	$ns->setCounter((int) $shipmentSeries->rowid, $scope, 3, 0);
	$ns->setCounter((int) $invoiceSeries->rowid, $scope, 2, 0);
	foreach ($mappings as $mapping) {
		$series = $mapping['type'] === 'shipment' ? $shipmentSeries : $invoiceSeries;
		$ns->audit((int) $series->rowid, 'RENUMBER', $scope, $mapping['old'], $mapping['new'], $mapping['type'], $mapping['id'], $mapping['new'], 0);
	}
	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
} catch (Throwable $e) {
	$db->rollback();
	foreach (array_reverse(array_keys($filesystemState)) as $index) {
		$mapping = $mappings[$index];
		$current = $filesystemState[$index]['path'];
		$original = $mapping['root'].'/'.$mapping['old'];
		if ($filesystemState[$index]['stage'] === 'final' && is_dir($current)) renameFilesAlign($current, $mapping['new'], $mapping['old']);
		if (is_dir($current) && !file_exists($original)) @rename($current, $original);
	}
	failAlign($e->getMessage());
}

try {
	regenerateDocumentsAlign($db, $langs);
} catch (Throwable $e) {
	failAlign('Nomor sudah berhasil diubah dan backup tersedia, tetapi '.$e->getMessage());
}

echo 'BACKUP='.$backupDirectory."\n";
echo "SUCCESS\n";
