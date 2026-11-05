<?php

require_once dirname(__DIR__, 3).'/main.inc.php';
require_once __DIR__.'/../class/namingseries.class.php';

if (!isModEnabled('namingseries') || !empty($user->socid) || !$user->hasRight('namingseries', 'read')) accessforbidden();

$langs->load('namingseries@namingseries');
$ns = new NamingSeries($db, $conf->entity);

function nsEscape($value) { return dol_escape_htmltag((string) $value); }
function nsUrl($path) { return dol_buildpath('/namingseries/'.$path, 1); }
function nsHeader($title)
{
	llxHeader('', $title, '', '', 0, 0, array(), array('/namingseries/css/namingseries.css?v=0.4.0'));
	print load_fiche_titre($title, '', 'barcode');
}
function nsEnd() { global $db; llxFooter(); $db->close(); }
function nsPost($right)
{
	global $user;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !GETPOST('token', 'alpha') || !hash_equals((string) ($_SESSION['token'] ?? ''), GETPOST('token', 'alpha'))) accessforbidden('Permintaan atau token tidak valid.');
	if (!$user->hasRight('namingseries', $right)) accessforbidden();
}
function nsToken($action)
{
	print '<input type="hidden" name="token" value="'.nsEscape(newToken()).'"><input type="hidden" name="action" value="'.nsEscape($action).'">';
}
