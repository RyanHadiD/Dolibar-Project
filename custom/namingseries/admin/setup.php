<?php

require_once __DIR__.'/../lib/common.php';
if (!$user->admin) accessforbidden();

nsHeader('Pengaturan Naming Series');
$integrations = array(
	'Customer Proposal' => 'PROPALE_ADDON', 'Sales Order' => 'COMMANDE_ADDON', 'Customer Invoice' => 'FACTURE_ADDON',
	'Supplier Proposal' => 'SUPPLIER_PROPOSAL_ADDON', 'Supplier Order' => 'COMMANDE_SUPPLIER_ADDON_NUMBER', 'Supplier Invoice' => 'INVOICE_SUPPLIER_ADDON_NUMBER',
	'Shipment' => 'EXPEDITION_ADDON_NUMBER', 'Reception' => 'RECEPTION_ADDON_NUMBER', 'Project' => 'PROJECT_ADDON', 'Project Task' => 'PROJECT_TASK_ADDON',
	'Customer Payment' => 'PAYMENT_ADDON', 'Supplier Payment' => 'SUPPLIER_PAYMENT_ADDON',
	'Contract' => 'CONTRACT_ADDON', 'Intervention' => 'FICHEINTER_ADDON', 'Expense Report' => 'EXPENSEREPORT_ADDON',
	'Manufacturing Order' => 'MRP_MO_ADDON', 'Bill of Materials' => 'BOM_ADDON',
	'Stock Transfer' => 'STOCKTRANSFER_STOCKTRANSFER_ADDON',
	'Bookkeeping Entry' => 'BOOKKEEPING_ADDON', 'Asset' => 'ASSET_ASSET_ADDON', 'Cheque Receipt' => 'CHEQUERECEIPTS_ADDON',
	'Delivery' => 'DELIVERY_ADDON_NUMBER', 'Leave Request' => 'HOLIDAY_ADDON', 'HR Evaluation' => 'HRMTEST_EVALUATION_ADDON',
	'Product Lot' => 'PRODUCTBATCH_LOT_ADDON', 'Product Serial Number' => 'PRODUCTBATCH_SN_ADDON',
	'TakePOS Invoice' => 'TAKEPOS_REF_ADDON', 'Ticket' => 'TICKET_ADDON', 'Workstation' => 'WORKSTATION_WORKSTATION_ADDON',
);
print '<div class="ns-panel"><h2>Integrasi Numbering Model</h2><table class="liste centpercent"><tr class="liste_titre"><th>Document type</th><th>Model aktif</th></tr>';
foreach ($integrations as $label => $constant) print '<tr class="oddeven"><td>'.nsEscape($label).'</td><td><code>'.nsEscape(getDolGlobalString($constant) ?: 'Belum dikonfigurasi').'</code></td></tr>';
print '</table>';
print '<p>Pilih model <strong>NamingSeries</strong> pada halaman konfigurasi masing-masing modul. Pengaturan aktif tidak diganti secara otomatis.</p>';
print '<p><a class="button" href="'.nsUrl('index.php').'">Kelola Naming Series</a></p></div>';
nsEnd();
