<?php

define('NOLOGIN', 1);
require dirname(__DIR__, 3).'/main.inc.php';
require_once dirname(__DIR__).'/class/namingseries.class.php';

$apply = in_array('--apply', $argv, true);
$entity = 1;
$mappings = array(
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 1, 'old' => 'PAY2610-0001', 'new' => 'PR-2026-10-0001', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 2, 'old' => 'PAY2610-0002', 'new' => 'PR-2026-10-0002', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 4, 'old' => 'PAY2610-0003', 'new' => 'PR-2026-10-0003', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 5, 'old' => 'PAY2610-0004', 'new' => 'PR-2026-10-0004', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 6, 'old' => 'PAY2610-0005', 'new' => 'PR-2026-10-0005', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 7, 'old' => 'PAY2610-0006', 'new' => 'PR-2026-10-0006', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 8, 'old' => 'PAY2610-0007', 'new' => 'PR-2026-10-0007', 'scope' => '2026-10'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 9, 'old' => 'PAY2611-0008', 'new' => 'PR-2026-11-0001', 'scope' => '2026-11'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 10, 'old' => 'PAY2611-0009', 'new' => 'PR-2026-11-0002', 'scope' => '2026-11'),
	array('type' => 'customer_payment', 'table' => 'paiement', 'id' => 11, 'old' => 'PAY2611-0010', 'new' => 'PR-2026-11-0003', 'scope' => '2026-11'),
);

// These fields embed a payment reference in human-readable history. The actual
// invoice and bank relationships use numeric IDs and must never be rewritten.
$referenceColumns = array(
	'actioncomm' => array('note'),
	'pi_document_action' => array('element_ref', 'snapshot_json'),
);

function failPaymentAlignment($message)
{
	fwrite(STDERR, 'ERROR: '.$message."\n");
	exit(1);
}

function rowsContainingPaymentReferences($db, $table, $columns, $references)
{
	$where = array();
	foreach ($columns as $column) {
		foreach ($references as $reference) {
			$where[] = '`'.$column."` LIKE '%".$db->escape($reference)."%'";
		}
	}
	$res = $db->query('SELECT * FROM `'.MAIN_DB_PREFIX.$table.'` WHERE '.implode(' OR ', $where));
	if (!$res) throw new RuntimeException($db->lasterror());
	$rows = array();
	while ($row = $db->fetch_array($res)) $rows[] = $row;
	return $rows;
}

function replacePaymentReference($db, $table, $columns, $from, $to)
{
	foreach ($columns as $column) {
		$sql = 'UPDATE `'.MAIN_DB_PREFIX.$table.'` SET `'.$column.'`=REPLACE(`'.$column."`,'"
			.$db->escape($from)."','".$db->escape($to)."') WHERE `".$column."` LIKE '%".$db->escape($from)."%'";
		if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
	}
}

function relationHealthPaymentAlignment($db, $entity)
{
	$checks = array(
		'payments_without_allocation' => 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement p LEFT JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_paiement=p.rowid WHERE p.entity='.(int) $entity.' AND pf.rowid IS NULL',
		'allocations_missing_payment' => 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement_facture pf LEFT JOIN '.MAIN_DB_PREFIX.'paiement p ON p.rowid=pf.fk_paiement WHERE p.rowid IS NULL',
		'allocations_missing_invoice' => 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement_facture pf LEFT JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid=pf.fk_facture WHERE f.rowid IS NULL',
		'payments_without_bank' => 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement p LEFT JOIN '.MAIN_DB_PREFIX.'bank b ON b.rowid=p.fk_bank WHERE p.entity='.(int) $entity.' AND b.rowid IS NULL',
		'payments_without_bank_url' => 'SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement p LEFT JOIN '.MAIN_DB_PREFIX."bank_url bu ON bu.url_id=p.rowid AND bu.type='payment' WHERE p.entity=".(int) $entity.' AND bu.rowid IS NULL',
		'amount_mismatch' => 'SELECT COUNT(*) total FROM (SELECT p.rowid,p.amount FROM '.MAIN_DB_PREFIX.'paiement p JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_paiement=p.rowid WHERE p.entity='.(int) $entity.' GROUP BY p.rowid,p.amount HAVING ABS(p.amount-SUM(pf.amount))>0.000001) mismatches',
	);
	$health = array();
	foreach ($checks as $name => $sql) {
		$res = $db->query($sql);
		$row = $res ? $db->fetch_object($res) : null;
		if (!$row) throw new RuntimeException($db->lasterror());
		$health[$name] = (int) $row->total;
	}
	return $health;
}

$ns = new NamingSeries($db, $entity);
$customerSeries = $ns->resolveSeries('customer_payment');
$supplierSeries = $ns->resolveSeries('supplier_payment');
if (!$customerSeries || !$supplierSeries) failPaymentAlignment('Naming Series customer atau supplier payment tidak ditemukan.');
if ($customerSeries->pattern !== 'PR-{YYYY}-{MM}-{####}' || $customerSeries->reset_period !== 'monthly') {
	failPaymentAlignment('Pola Customer Payment aktif sudah berubah; audit ulang sebelum menjalankan migrasi.');
}
if ($supplierSeries->pattern !== 'PE-{YYYY}-{MM}-{####}' || $supplierSeries->reset_period !== 'monthly') {
	failPaymentAlignment('Pola Supplier Payment aktif sudah berubah; audit ulang sebelum menjalankan migrasi.');
}

$oldReferences = array_column($mappings, 'old');
$newReferences = array_column($mappings, 'new');
foreach ($mappings as $mapping) {
	$res = $db->query('SELECT rowid,datep FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE entity='.$entity.' AND rowid='.(int) $mapping['id']
		." AND ref='".$db->escape($mapping['old'])."'");
	$row = $res ? $db->fetch_object($res) : null;
	if (!$row || substr((string) $row->datep, 0, 7) !== $mapping['scope']) {
		failPaymentAlignment('Payment sumber tidak ditemukan, tanggal berubah, atau sudah diproses: '.$mapping['old']);
	}
	$res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE entity='.$entity
		." AND ref='".$db->escape($mapping['new'])."' AND rowid<>".(int) $mapping['id']);
	if (!$res || $db->num_rows($res) > 0) failPaymentAlignment('Nomor tujuan sudah digunakan: '.$mapping['new']);
}

$res = $db->query('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiement WHERE entity='.$entity);
$row = $res ? $db->fetch_object($res) : null;
if (!$row || (int) $row->total !== count($mappings)) failPaymentAlignment('Jumlah Customer Payment berubah; audit ulang diperlukan.');
$res = $db->query('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'paiementfourn WHERE entity='.$entity);
$row = $res ? $db->fetch_object($res) : null;
if (!$row || (int) $row->total !== 0) failPaymentAlignment('Supplier Payment baru ditemukan; audit ulang diperlukan.');

try {
	$healthBefore = relationHealthPaymentAlignment($db, $entity);
} catch (Throwable $e) {
	failPaymentAlignment($e->getMessage());
}
foreach ($healthBefore as $name => $count) {
	if ($count !== 0) failPaymentAlignment('Relasi tidak sehat sebelum migrasi: '.$name.'='.$count);
}

$backup = array(
	'created_at' => date(DATE_ATOM),
	'mappings' => $mappings,
	'patterns' => array('customer_payment' => $customerSeries->pattern, 'supplier_payment' => $supplierSeries->pattern),
	'counters_before' => array(
		'customer_2026_10' => $ns->currentValue((int) $customerSeries->rowid, '2026-10'),
		'customer_2026_11' => $ns->currentValue((int) $customerSeries->rowid, '2026-11'),
		'supplier_2026_11' => $ns->currentValue((int) $supplierSeries->rowid, '2026-11'),
	),
	'relation_health_before' => $healthBefore,
	'tables' => array(),
);
$backup['tables']['paiement'] = rowsContainingPaymentReferences($db, 'paiement', array('ref'), $oldReferences);
foreach ($referenceColumns as $table => $columns) {
	$backup['tables'][$table] = rowsContainingPaymentReferences($db, $table, $columns, $oldReferences);
}

echo ($apply ? 'APPLY' : 'DRY-RUN')."\n";
foreach ($mappings as $mapping) echo '#'.$mapping['id'].' '.$mapping['old'].' -> '.$mapping['new']."\n";
foreach ($backup['tables'] as $table => $rows) echo MAIN_DB_PREFIX.$table.': '.count($rows)." affected rows\n";
echo 'Customer Payment counter 2026-10: '.$backup['counters_before']['customer_2026_10']." -> 7\n";
echo 'Customer Payment counter 2026-11: '.$backup['counters_before']['customer_2026_11']." -> 3\n";
echo 'Supplier Payment counter 2026-11: '.$backup['counters_before']['supplier_2026_11']." (next PE-2026-11-0001)\n";
if (!$apply) exit(0);

$backupDirectory = DOL_DATA_ROOT.'/namingseries_backups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true)) failPaymentAlignment('Folder backup tidak dapat dibuat.');
$backupFile = $backupDirectory.'/align_payment_series_'.date('Ymd_His').'.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
	failPaymentAlignment('Backup database tidak dapat ditulis.');
}

$db->begin();
try {
	foreach ($mappings as $mapping) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$mapping['table']." SET ref='".$db->escape($mapping['new'])."' WHERE entity=".$entity
			.' AND rowid='.(int) $mapping['id']." AND ref='".$db->escape($mapping['old'])."'";
		$res = $db->query($sql);
		if (!$res || $db->affected_rows($res) !== 1) {
			throw new RuntimeException('Payment gagal diperbarui: '.$mapping['old']);
		}
		foreach ($referenceColumns as $table => $columns) {
			replacePaymentReference($db, $table, $columns, $mapping['old'], $mapping['new']);
		}
		$ns->audit((int) $customerSeries->rowid, 'RENUMBER', $mapping['scope'], $mapping['old'], $mapping['new'],
			$mapping['type'], (int) $mapping['id'], $mapping['new'], 0);
	}
	$ns->setCounter((int) $customerSeries->rowid, '2026-10', 7, 0);
	$ns->setCounter((int) $customerSeries->rowid, '2026-11', 3, 0);

	$healthAfter = relationHealthPaymentAlignment($db, $entity);
	if ($healthAfter !== $healthBefore) throw new RuntimeException('Kesehatan relasi berubah setelah renumber.');
	foreach ($mappings as $mapping) {
		$res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE entity='.$entity.' AND rowid='.(int) $mapping['id']
			." AND ref='".$db->escape($mapping['new'])."'");
		if (!$res || $db->num_rows($res) !== 1) throw new RuntimeException('Verifikasi gagal: '.$mapping['new']);
	}
	if (!$db->commit()) throw new RuntimeException('Commit database gagal.');
} catch (Throwable $e) {
	$db->rollback();
	failPaymentAlignment($e->getMessage());
}

echo 'BACKUP='.$backupFile."\n";
echo "SUCCESS\n";
