<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$entity = 1;
$scope = '2026-11';
$replacements = array(
	'SO-2026-11-0003' => 'SO-2026-11-0002',
	'BBK-2026-11-0003' => 'BBK-2026-11-0002',
);
$columns = array(
	'actioncomm' => array('label', 'note'),
	'stock_mouvement' => array('label'),
	'ns_audit' => array('old_value', 'new_value', 'document_ref'),
);

function failReplaceAll($message)
{
	fwrite(STDERR, 'ERROR: '.$message."\n");
	exit(1);
}

function rowsForReplaceAll($db, $table, $columns, $references)
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

$backup = array('created_at' => date(DATE_ATOM), 'replacements' => $replacements, 'tables' => array());
foreach ($columns as $table => $tableColumns) {
	$backup['tables'][$table] = rowsForReplaceAll($db, $table, $tableColumns, array_keys($replacements));
}

$ns = new NamingSeries($db, $entity);
$shipmentSeries = $ns->resolveSeries('shipment');
if (!$shipmentSeries) failReplaceAll('Naming Series shipment tidak ditemukan.');
$backup['shipment_counter_before'] = $ns->currentValue((int) $shipmentSeries->rowid, $scope);

echo ($apply ? 'APPLY' : 'DRY-RUN')."\n";
foreach ($replacements as $old => $new) echo $old.' -> '.$new."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
echo 'Shipment counter: '.$backup['shipment_counter_before']." -> 2\n";
if (!$apply) exit(0);

$backupDirectory = DOL_DATA_ROOT.'/namingseries_backups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true)) failReplaceAll('Folder backup tidak dapat dibuat.');
$backupFile = $backupDirectory.'/replace_all_remaining_0003_'.date('Ymd_His').'.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) failReplaceAll('Backup tidak dapat ditulis.');

$db->begin();
try {
	foreach ($columns as $table => $tableColumns) {
		foreach ($tableColumns as $column) {
			foreach ($replacements as $old => $new) {
				$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'".$db->escape($old)."','".$db->escape($new)."')"
					." WHERE `".$column."` LIKE '%".$db->escape($old)."%'";
				if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
			}
		}
	}
	$ns->setCounter((int) $shipmentSeries->rowid, $scope, 2, 0);
	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
} catch (Throwable $e) {
	$db->rollback();
	failReplaceAll($e->getMessage());
}

echo 'BACKUP='.$backupFile."\n";
echo "SUCCESS\n";
