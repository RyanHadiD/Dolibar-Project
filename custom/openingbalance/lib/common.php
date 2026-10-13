<?php

require_once dirname(__DIR__, 3).'/main.inc.php';
require_once __DIR__.'/../class/openingbalance.class.php';

if (!isModEnabled('openingbalance') || !empty($user->socid) || !$user->hasRight('openingbalance', 'read')) {
	accessforbidden();
}

$langs->load('openingbalance@openingbalance');
$ob = new OpeningBalance($db, $conf->entity);

function obEscape($value)
{
	return dol_escape_htmltag((string) $value);
}

function obUrl($path)
{
	return dol_buildpath('/openingbalance/'.$path, 1);
}

function obHeader($title)
{
	llxHeader('', $title, '', '', 0, 0, array(), array('/openingbalance/css/openingbalance.css?v=0.3.0'));
	print load_fiche_titre($title, '', 'accounting');
	print '<script nonce="'.getNonce().'">window.obSearchUrl='.json_encode(obUrl('search.php')).';window.obInitSearch=function(scope){var $=jQuery;$(scope||document).find("select.ob-search").each(function(){var field=$(this);if(field.hasClass("select2-hidden-accessible")){return;}field.select2({width:"100%",allowClear:true,placeholder:"Ketik untuk mencari",minimumInputLength:0,ajax:{url:window.obSearchUrl,dataType:"json",delay:250,data:function(params){return {type:field.data("type"),q:params.term||""};},processResults:function(data){return data;},cache:true}});});};jQuery(function(){window.obInitSearch(document);});</script>';
}

function obEnd()
{
	global $db;
	llxFooter();
	$db->close();
}

function obPost($right)
{
	global $user;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !GETPOST('token', 'alpha') || !hash_equals((string) ($_SESSION['token'] ?? ''), GETPOST('token', 'alpha'))) {
		accessforbidden('Permintaan atau token tidak valid.');
	}
	if (!$user->hasRight('openingbalance', $right)) {
		accessforbidden();
	}
}

function obToken($action)
{
	print '<input type="hidden" name="token" value="'.obEscape(newToken()).'"><input type="hidden" name="action" value="'.obEscape($action).'">';
}

function obStatus($status)
{
	$labels = array(0 => 'Draft', 1 => 'Validated', 2 => 'Posted', 3 => 'Locked');
	return $labels[(int) $status] ?? 'Unknown';
}

function obPostRows($map)
{
	$arrays = array();
	$count = 0;
	foreach ($map as $field => $source) {
		$value = isset($_POST[$source]) && is_array($_POST[$source]) ? array_values($_POST[$source]) : array();
		$arrays[$field] = $value;
		$count = max($count, count($value));
	}
	$rows = array();
	for ($i = 0; $i < $count; $i++) {
		$row = array();
		foreach ($arrays as $field => $values) {
			$row[$field] = isset($values[$i]) && is_scalar($values[$i]) ? trim((string) $values[$i]) : '';
		}
		$rows[] = $row;
	}
	return $rows;
}

function obLookupLabel($type, $value)
{
	global $ob;
	if ($value === '' || $value === null) return '';
	$sql = '';
	if ($type === 'account') $sql = 'SELECT CONCAT(account_number,\' — \',label) text FROM '.$ob->table('accounting_account').' WHERE entity='.$ob->entity.' AND account_number='.$ob->str($value).' AND active=1 AND fk_pcg_version IN (SELECT s.pcg_version FROM '.$ob->table('accounting_system').' s WHERE s.rowid='.(int) getDolGlobalInt('CHARTOFACCOUNTS').')';
	if ($type === 'journal') $sql = 'SELECT CONCAT(code,\' — \',label) text FROM '.$ob->table('accounting_journal').' WHERE entity='.$ob->entity.' AND code='.$ob->str($value).' AND active=1';
	$row = $sql ? $ob->one($sql) : null;
	return $row ? $row->text : (string) $value;
}

function obSearchSelect($name, $type, $selected = '', $label = '')
{
	$html = '<select class="ob-search" data-type="'.obEscape($type).'" name="'.obEscape($name).'">';
	if ($selected !== '' && $selected !== null) {
		$html .= '<option value="'.obEscape($selected).'" selected>'.obEscape($label !== '' ? $label : $selected).'</option>';
	}
	return $html.'</select>';
}
