<?php
require_once dirname(__DIR__, 3).'/main.inc.php';
require_once __DIR__.'/../class/creditlimit.class.php';

if (!isModEnabled('creditlimit') || !empty($user->socid) || !$user->hasRight('creditlimit', 'read')) accessforbidden();
$langs->load('creditlimit@creditlimit');
$cl = new CreditLimitService($db, $conf->entity);

function clEscape($value) { return dol_escape_htmltag((string) $value); }
function clUrl($path) { return dol_buildpath('/creditlimit/'.$path, 1); }
function clMoney($value) { return price((float) $value); }
function clHeader($title)
{
	print llxHeader('', $title, '', '', 0, 0, array(), array('/creditlimit/css/creditlimit.css?v=1.1.0'));
	print load_fiche_titre($title, '', 'payment');
	print '<nav class="cl-nav"><a href="'.clEscape(clUrl('index.php')).'">Dashboard</a><a href="'.clEscape(clUrl('audit.php')).'">Audit</a>';
	global $user;
	if ($user->admin) print '<a href="'.clEscape(clUrl('admin/bypass_users.php')).'">User Bypass</a><a href="'.clEscape(clUrl('admin/setup.php')).'">Pengaturan</a>';
	print '</nav>';
}
function clEnd() { global $db; llxFooter(); $db->close(); }
function clPost($right = '')
{
	global $user;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !GETPOST('token', 'alpha') || !hash_equals((string) ($_SESSION['token'] ?? ''), GETPOST('token', 'alpha'))) accessforbidden('Permintaan atau token tidak valid.');
	if ($right && !$user->hasRight('creditlimit', $right)) accessforbidden();
}
function clToken($action) { print '<input type="hidden" name="token" value="'.clEscape(newToken()).'"><input type="hidden" name="action" value="'.clEscape($action).'">'; }
function clLoadDocument($type, $id)
{
	global $db, $conf;
	$defs = array(
		'customer_order' => array('/commande/class/commande.class.php', 'Commande'),
		'shipment' => array('/expedition/class/expedition.class.php', 'Expedition'),
		'customer_invoice' => array('/compta/facture/class/facture.class.php', 'Facture'),
	);
	if (!isset($defs[$type]) || $id <= 0) throw new DomainException('Dokumen tidak valid.');
	require_once DOL_DOCUMENT_ROOT.$defs[$type][0];
	$class = $defs[$type][1]; $object = new $class($db);
	if ($object->fetch((int) $id) <= 0 || (!empty($object->entity) && (int) $object->entity !== (int) $conf->entity)) throw new DomainException('Dokumen tidak ditemukan pada entitas aktif.');
	return $object;
}
