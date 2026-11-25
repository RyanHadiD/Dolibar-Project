<?php

if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', 1);
require_once dirname(__DIR__, 3).'/main.inc.php';

header('Content-Type: application/javascript; charset=UTF-8');
if (!isModEnabled('namingseries') || !empty($user->socid) || (!$user->admin && !$user->hasRight('namingseries', 'counter'))) {
	echo '/* Naming Series rename action unavailable for this user. */';
	exit;
}

$renameUrl = dol_buildpath('/namingseries/rename.php', 1);
?>
(function () {
	'use strict';
	var routes = [
		[/\/comm\/propal\/card\.php$/, 'sales_proposal'], [/\/commande\/card\.php$/, 'sales_order'],
		[/\/compta\/facture\/card\.php$/, 'customer_invoice'], [/\/supplier_proposal\/card\.php$/, 'supplier_proposal'],
		[/\/fourn\/commande\/card\.php$/, 'supplier_order'], [/\/fourn\/facture\/card\.php$/, 'supplier_invoice'],
		[/\/expedition\/card\.php$/, 'shipment'], [/\/reception\/card\.php$/, 'reception'],
		[/\/compta\/paiement\/card\.php$/, 'customer_payment'], [/\/fourn\/paiement\/card\.php$/, 'supplier_payment'],
		[/\/contrat\/card\.php$/, 'contract'], [/\/fichinter\/card\.php$/, 'intervention'],
		[/\/projet\/card\.php$/, 'project'], [/\/projet\/tasks\/task\.php$/, 'project_task'],
		[/\/expensereport\/card\.php$/, 'expense_report'], [/\/mrp\/mo_card\.php$/, 'manufacturing_order'],
		[/\/bom\/bom_card\.php$/, 'bom'], [/\/product\/stock\/stocktransfer\/stocktransfer_card\.php$/, 'stock_transfer'],
		[/\/accountancy\/bookkeeping\/card\.php$/, 'bookkeeping'], [/\/asset\/card\.php$/, 'asset'],
		[/\/compta\/paiement\/cheque\/card\.php$/, 'cheque_receipt'], [/\/delivery\/card\.php$/, 'delivery'],
		[/\/holiday\/card\.php$/, 'leave_request'], [/\/hrm\/evaluation_card\.php$/, 'hr_evaluation'],
		[/\/product\/stock\/productlot_card\.php$/, 'product_lot'], [/\/ticket\/card\.php$/, 'ticket'],
		[/\/workstation\/workstation_card\.php$/, 'workstation']
	];

	document.addEventListener('DOMContentLoaded', function () {
		if (document.getElementById('ns-rename-document-action')) return;
		var type = '';
		for (var i = 0; i < routes.length; i++) if (routes[i][0].test(window.location.pathname)) { type = routes[i][1]; break; }
		if (!type) return;
		var query = new URLSearchParams(window.location.search);
		var id = parseInt(query.get('id') || query.get('facid') || '0', 10);
		if (!id) return;
		var target = document.querySelector('.tabsAction');
		if (!target) return;
		var link = document.createElement('a');
		link.id = 'ns-rename-document-action';
		link.className = 'butAction';
		link.href = <?php echo json_encode($renameUrl); ?>+'?document_type='+encodeURIComponent(type)+'&id='+id+'&return_url='+encodeURIComponent(window.location.pathname+window.location.search);
		link.innerHTML = '<span class="fas fa-edit" aria-hidden="true"></span> Rename Document';
		target.insertBefore(link, target.firstChild);
	});
})();
