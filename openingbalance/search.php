<?php

require_once __DIR__.'/lib/common.php';

header('Content-Type: application/json; charset=utf-8');
$type = GETPOST('type', 'alpha');
$term = trim(GETPOST('q', 'restricthtml'));
$like = $ob->str('%'.$term.'%');
$limit = 30;
$sql = '';

if ($type === 'product') {
	$sql = 'SELECT rowid id,CONCAT(ref,\' — \',label) text FROM '.$ob->table('product').' WHERE entity='.$ob->entity.' AND fk_product_type=0 AND stockable_product=1 AND (ref LIKE '.$like.' OR label LIKE '.$like.') ORDER BY ref LIMIT '.$limit;
} elseif ($type === 'warehouse') {
	$sql = 'SELECT rowid id,CONCAT(ref,IF(COALESCE(description,\'\')=\'\',\'\',CONCAT(\' — \',description))) text FROM '.$ob->table('entrepot').' WHERE entity='.$ob->entity.' AND statut=1 AND (ref LIKE '.$like.' OR description LIKE '.$like.') ORDER BY ref LIMIT '.$limit;
} elseif ($type === 'customer' || $type === 'supplier') {
	$flag = $type === 'customer' ? 'client' : 'fournisseur';
	$code = $type === 'customer' ? 'code_client' : 'code_fournisseur';
	$sql = 'SELECT rowid id,CONCAT(IF(COALESCE('.$code.',\'\')=\'\',\'\',CONCAT('.$code.',\' — \')),nom) text FROM '.$ob->table('societe').' WHERE entity='.$ob->entity.' AND '.$flag.'>0 AND (nom LIKE '.$like.' OR '.$code.' LIKE '.$like.') ORDER BY nom LIMIT '.$limit;
} elseif ($type === 'account') {
	$sql = 'SELECT account_number id,CONCAT(account_number,\' — \',label) text FROM '.$ob->table('accounting_account').' WHERE entity='.$ob->entity.' AND active=1 AND fk_pcg_version IN (SELECT s.pcg_version FROM '.$ob->table('accounting_system').' s WHERE s.rowid='.(int) getDolGlobalInt('CHARTOFACCOUNTS').') AND (account_number LIKE '.$like.' OR label LIKE '.$like.') ORDER BY account_number LIMIT '.$limit;
} elseif ($type === 'journal') {
	$sql = 'SELECT code id,CONCAT(code,\' — \',label) text FROM '.$ob->table('accounting_journal').' WHERE entity='.$ob->entity.' AND active=1 AND (code LIKE '.$like.' OR label LIKE '.$like.') ORDER BY code LIMIT '.$limit;
}

if ($sql === '') {
	http_response_code(400);
	print json_encode(array('results' => array()));
	exit;
}

$results = array();
foreach ($ob->rows($sql) as $row) {
	$results[] = array('id' => (string) $row->id, 'text' => (string) $row->text);
}
print json_encode(array('results' => $results), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
