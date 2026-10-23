<?php
if (!defined('REQUIRE_JQUERY_MULTISELECT')) {
	define('REQUIRE_JQUERY_MULTISELECT', 'select2');
}
require_once dirname(__DIR__, 3).'/main.inc.php';
require_once __DIR__.'/../class/perpetualinventory.class.php';

$piFinancialPages = array('generalledger.php', 'profitloss.php', 'balancesheet.php', 'grossprofit.php', 'receivablepayable.php', 'dailyreport.php');
$piIsFinancialPage = in_array(basename($_SERVER['SCRIPT_NAME']), $piFinancialPages, true);
$piIsLifecyclePage = in_array(basename($_SERVER['SCRIPT_NAME']), array('document_action.php', 'journal_action.php'), true);
$piRequiredRight = $piIsFinancialPage ? 'financial' : 'read';
if (!isModEnabled('perpetualinventory') || !empty($user->socid) || (!$piIsLifecyclePage && !$user->hasRight('perpetualinventory', $piRequiredRight))) {
	accessforbidden();
}
$langs->load('perpetualinventory@perpetualinventory');
$pi = new PerpetualInventory($db, $conf->entity);

function piEscape($value)
{
	return dol_escape_htmltag((string) $value);
}

function piUrl($path)
{
	return dol_buildpath('/perpetualinventory/'.$path, 1);
}

function piHeader($title)
{
	llxHeader('', $title, '', '', 0, 0, array(), array('/perpetualinventory/css/perpetualinventory.css?v=1.6.0'));
	print load_fiche_titre($title, '', 'stock');
	print '<nav class="pi-nav">';
	$page = basename($_SERVER['SCRIPT_NAME']);
	if (in_array($page, array('stockentry_list.php', 'stockentry_card.php'), true)) {
		$links = array('stockentry_list.php' => 'Stock Entry List', 'stockentry_card.php?action=create' => 'New Stock Entry');
	} elseif ($page === 'dailyreport.php') {
		$links = array(
			'dailyreport.php?report=ar-summary' => 'Accounts Receivable Summary',
			'dailyreport.php?report=ap-summary' => 'Accounts Payable Summary',
			'dailyreport.php?report=sales-register' => 'Sales Register',
			'dailyreport.php?report=purchase-register' => 'Purchase Register',
		);
	} elseif (in_array($page, array('generalledger.php', 'profitloss.php', 'balancesheet.php', 'grossprofit.php', 'receivablepayable.php'), true)) {
		$links = array('generalledger.php' => 'General Ledger', 'profitloss.php' => 'Profit & Loss', 'balancesheet.php' => 'Balance Sheet', 'grossprofit.php' => 'Gross Profit', 'receivablepayable.php' => 'Hutang & Piutang');
	} elseif (in_array($page, array('ledger.php', 'stockbalance.php'), true)) {
		$links = array('ledger.php' => 'Stock Ledger', 'stockbalance.php' => 'Stock Balance');
	} else {
		$links = array('index.php' => 'Ringkasan', 'matching.php' => 'Pencocokan tagihan', 'reconcile.php' => 'Rekonsiliasi', 'audit.php' => 'Riwayat Cancel/Revisi', 'admin/setup.php' => 'Pengaturan');
	}
	foreach ($links as $path => $label) {
		print '<a href="'.piEscape(piUrl($path)).'">'.piEscape($label).'</a>';
	}
	print '</nav>';
	print '<script nonce="'.getNonce().'">jQuery(function(){if(!jQuery.fn.select2){return;}jQuery("select.pi-product-search").each(function(){var field=jQuery(this);if(!field.hasClass("select2-hidden-accessible")){field.select2({width:"100%",minimumResultsForSearch:0,placeholder:"Cari kode atau nama barang",allowClear:!field.prop("required"),containerCssClass:":all:",selectionCssClass:":all:"});}});});</script>';
}

function piEnd()
{
	global $db;
	llxFooter();
	$db->close();
}

function piPost($right = '')
{
	global $user;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !GETPOST('token', 'alpha') || !hash_equals((string) ($_SESSION['token'] ?? ''), GETPOST('token', 'alpha'))) {
		accessforbidden('Permintaan atau token tidak valid.');
	}
	if ($right && !$user->hasRight('perpetualinventory', $right)) {
		accessforbidden();
	}
}

function piToken($action)
{
	print '<input type="hidden" name="token" value="'.piEscape(newToken()).'"><input type="hidden" name="action" value="'.piEscape($action).'">';
}

function piSelect($name, $options, $value = '', $required = true)
{
	print '<select class="flat minwidth200" name="'.piEscape($name).'" id="'.piEscape($name).'"'.($required ? ' required' : '').'><option value="">— Pilih —</option>';
	foreach ($options as $id => $label) {
		print '<option value="'.piEscape($id).'"'.((string) $id === (string) $value ? ' selected' : '').'>'.piEscape($label).'</option>';
	}
	print '</select>';
}

function piProductSelect($name, $options, $value = '', $required = true)
{
	print '<select class="flat minwidth300 pi-product-search" name="'.piEscape($name).'" id="'.piEscape($name).'"'.($required ? ' required' : '').'><option value=""></option>';
	foreach ($options as $id => $label) {
		print '<option value="'.piEscape($id).'"'.((string) $id === (string) $value ? ' selected' : '').'>'.piEscape($label).'</option>';
	}
	print '</select>';
}

function piMoney($value)
{
	return price($value);
}

function piQty($value)
{
	return price(round((float) $value, 8), 0, '', 1, 0, -1, '');
}

function piVoucherLink($entry)
{
	$paths = array('reception' => '/reception/card.php?id=', 'shipping' => '/expedition/card.php?id=', 'pistockentry' => '/perpetualinventory/stockentry_card.php?id=');
	$label = piEscape($entry->voucher_ref);
	if (!empty($paths[$entry->origin_type]) && $entry->origin_id) {
		return '<a href="'.piEscape(DOL_URL_ROOT.$paths[$entry->origin_type].(int) $entry->origin_id).'">'.$label.'</a>';
	}
	return $label;
}

function piNumber($key)
{
	$raw = GETPOST($key, 'alphanohtml');
	if ($raw === '' || !is_numeric($raw) || !is_finite((float) $raw)) {
		throw new DomainException('Isi angka '.$key.' menggunakan titik untuk desimal, tanpa pemisah ribuan.');
	}
	return (float) $raw;
}

function piMasters()
{
	global $pi;
	$products = $warehouses = array();
	foreach ($pi->rows('SELECT rowid,ref,label FROM '.$pi->table('product').' WHERE entity='.$pi->entity.' AND fk_product_type=0 AND stockable_product=1 ORDER BY ref') as $p) {
		$products[$p->rowid] = $p->ref.' — '.$p->label;
	}
	foreach ($pi->rows('SELECT rowid,ref FROM '.$pi->table('entrepot').' WHERE entity='.$pi->entity.' ORDER BY ref') as $w) {
		$warehouses[$w->rowid] = $w->ref;
	}
	return array($products, $warehouses);
}

function piKind($kind)
{
	$names = array('opening' => 'Saldo awal', 'receipt' => 'Penerimaan', 'delivery' => 'Pengiriman', 'adjustment' => 'Penyesuaian / opname', 'transfer_out' => 'Transfer keluar', 'transfer_in' => 'Transfer masuk', 'return' => 'Retur', 'reversal' => 'Pembalik');
	return $names[$kind] ?? $kind;
}
