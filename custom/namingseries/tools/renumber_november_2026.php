<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$mappings = array(
	array('type' => 'sales_order', 'table' => 'commande', 'old' => 'SO-2026-11-0025', 'new' => 'SO-2026-11-0001', 'directory' => DOL_DATA_ROOT.'/commande/SO-2026-11-0025'),
	array('type' => 'sales_order', 'table' => 'commande', 'old' => 'SO-2026-11-0026', 'new' => 'SO-2026-11-0002', 'directory' => DOL_DATA_ROOT.'/commande/SO-2026-11-0026'),
	array('type' => 'shipment', 'table' => 'expedition', 'old' => 'BBK-2026-11-0025', 'new' => 'BBK-2026-11-0001', 'directory' => DOL_DATA_ROOT.'/expedition/sending/BBK-2026-11-0025'),
	array('type' => 'shipment', 'table' => 'expedition', 'old' => 'BBK-2026-11-0026', 'new' => 'BBK-2026-11-0002', 'directory' => DOL_DATA_ROOT.'/expedition/sending/BBK-2026-11-0026'),
);
$columns = array(
	'accounting_bookkeeping' => array('doc_ref', 'label_operation'),
	'actioncomm' => array('label', 'note'),
	'commande' => array('ref', 'last_main_doc'),
	'ecm_files' => array('filepath', 'filename', 'fullpath_orig'),
	'expedition' => array('ref', 'ref_customer', 'last_main_doc'),
	'pi_document_action' => array('element_ref', 'snapshot_json'),
	'pi_entry' => array('note'),
	'stock_mouvement' => array('label'),
);

function fail($message)
{
	fwrite(STDERR, "ERROR: ".$message."\n");
	exit(1);
}

function rowsContaining($db, $table, $columns, $refs)
{
	$where = array();
	foreach ($columns as $column) foreach ($refs as $ref) $where[] = '`'.$column."` LIKE '%".$db->escape($ref)."%'";
	$sql = 'SELECT * FROM `'.MAIN_DB_PREFIX.$table.'` WHERE '.implode(' OR ', $where);
	$res = $db->query($sql);
	$out = array();
	if ($res) while ($row = $db->fetch_array($res)) $out[] = $row;
	return $out;
}

function renameTree($root, $old, $new, &$operations)
{
	if (!is_dir($root)) return;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $entry) {
		$name = $entry->getBasename();
		if (strpos($name, $old) === false) continue;
		$source = $entry->getPathname();
		$target = $entry->getPath().'/'.str_replace($old, $new, $name);
		if (file_exists($target) || !rename($source, $target)) throw new RuntimeException('Gagal rename '.$source.' ke '.$target);
		$operations[] = array($source, $target);
	}
	$targetRoot = dirname($root).'/'.str_replace($old, $new, basename($root));
	if (file_exists($targetRoot) || !rename($root, $targetRoot)) throw new RuntimeException('Gagal rename '.$root.' ke '.$targetRoot);
	$operations[] = array($root, $targetRoot);
}

$refs = array();
foreach ($mappings as $map) {
	$oldSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$map['table']." WHERE entity=1 AND ref='".$db->escape($map['old'])."'";
	$newSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$map['table']." WHERE entity=1 AND ref='".$db->escape($map['new'])."'";
	$oldRes = $db->query($oldSql);
	$newRes = $db->query($newSql);
	if (!$oldRes || $db->num_rows($oldRes) !== 1) fail('Referensi sumber tidak ditemukan atau tidak unik: '.$map['old']);
	if (!$newRes || $db->num_rows($newRes) !== 0) fail('Referensi tujuan sudah digunakan: '.$map['new']);
	$newDirectory = dirname($map['directory']).'/'.str_replace($map['old'], $map['new'], basename($map['directory']));
	if (file_exists($newDirectory)) fail('Direktori tujuan sudah ada: '.$newDirectory);
	$refs[] = $map['old'];
}

$backup = array('created_at' => date(DATE_ATOM), 'mappings' => $mappings, 'constants' => array(
	'COMMANDE_ADDON' => getDolGlobalString('COMMANDE_ADDON'),
	'EXPEDITION_ADDON_NUMBER' => getDolGlobalString('EXPEDITION_ADDON_NUMBER'),
), 'tables' => array());
foreach ($columns as $table => $tableColumns) {
	$backup['tables'][$table] = rowsContaining($db, $table, $tableColumns, $refs);
}

echo ($apply ? "APPLY" : "DRY-RUN")."\n";
foreach ($mappings as $map) echo $map['old'].' -> '.$map['new']."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
if (!$apply) exit(0);

$backupDir = DOL_DATA_ROOT.'/namingseries_backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true)) fail('Tidak dapat membuat direktori backup.');
$backupFile = $backupDir.'/renumber_november_2026_'.date('Ymd_His').'.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) fail('Tidak dapat menulis backup.');

$renamed = array();
$db->begin();
try {
	foreach ($columns as $table => $tableColumns) {
		foreach ($tableColumns as $column) {
			foreach ($mappings as $map) {
				$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'".$db->escape($map['old'])."','".$db->escape($map['new'])."')"
					." WHERE `".$column."` LIKE '%".$db->escape($map['old'])."%'";
				if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
			}
		}
	}

	$ns = new NamingSeries($db, 1);
	$so = $ns->resolveSeries('sales_order');
	$shipment = $ns->resolveSeries('shipment');
	if (!$so || !$shipment) throw new RuntimeException('Series SO atau Shipment tidak ditemukan.');
	if (!$db->query('UPDATE '.MAIN_DB_PREFIX."ns_series SET pattern='BBK-{YYYY}-{MM}-{####}',label='Shipment BBK Monthly',reset_period='monthly' WHERE rowid=".(int) $shipment->rowid.' AND entity=1')) throw new RuntimeException($db->lasterror());
	$ns->setCounter((int) $so->rowid, '2026-11', 2, 0);
	$ns->setCounter((int) $shipment->rowid, '2026-11', 2, 0);
	foreach ($mappings as $map) {
		$series = $map['type'] === 'sales_order' ? $so : $shipment;
		$ns->audit((int) $series->rowid, 'RENUMBER', '2026-11', $map['old'], $map['new'], $map['type']);
	}
	if (dolibarr_set_const($db, 'EXPEDITION_ADDON_NUMBER', 'mod_expedition_namingseries', 'chaine', 0, '', 1) <= 0) throw new RuntimeException('Gagal mengaktifkan NamingSeries untuk shipment.');

	foreach ($mappings as $map) renameTree($map['directory'], $map['old'], $map['new'], $renamed);
	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
	echo 'BACKUP='.$backupFile."\n";
	echo "SUCCESS\n";
} catch (Throwable $e) {
	$db->rollback();
	foreach (array_reverse($renamed) as $operation) if (file_exists($operation[1]) && !file_exists($operation[0])) @rename($operation[1], $operation[0]);
	fail($e->getMessage());
}
