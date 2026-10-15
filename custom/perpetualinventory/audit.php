<?php
require_once __DIR__.'/lib/common.php';

$type = GETPOST('type', 'alpha');
$documentId = GETPOSTINT('document_id');
$allowedTypes = array('customer_order', 'supplier_order', 'shipment', 'reception', 'customer_invoice', 'supplier_invoice', 'customer_payment', 'supplier_payment', 'accounting_journal');
$where = array('a.entity='.(int) $pi->entity);
if (in_array($type, $allowedTypes, true)) {
	$where[] = 'a.element_type='.$pi->str($type);
}
if ($documentId > 0) {
	$where[] = 'a.fk_element='.$documentId;
}
$rows = $pi->rows('SELECT a.*,u.login,u.firstname,u.lastname,(SELECT COUNT(*) FROM '.$pi->table('pi_document_action').' seq WHERE seq.entity=a.entity AND seq.element_type=a.element_type AND seq.fk_element=a.fk_element AND seq.rowid<=a.rowid) revision_no FROM '.$pi->table('pi_document_action').' a LEFT JOIN '.$pi->table('user').' u ON u.rowid=a.fk_user WHERE '.implode(' AND ', $where).' ORDER BY a.date_action DESC,a.rowid DESC LIMIT 500');

piHeader('Riwayat Cancel/Revisi');
print '<p class="pi-report-intro">Audit alasan pembatalan dan pengembalian dokumen ke Draft. Data mencatat dokumen, pengguna, waktu, aksi, dan alasan yang dimasukkan.</p>';
print '<form method="get" class="pi-report-filter"><label>Jenis dokumen<select class="flat minwidth200" name="type"><option value="">Semua</option>';
foreach (array('customer_order' => 'Sales Order', 'supplier_order' => 'Purchase Order', 'shipment' => 'Shipment', 'reception' => 'Reception', 'customer_invoice' => 'Customer Invoice', 'supplier_invoice' => 'Vendor Invoice', 'customer_payment' => 'Customer Payment', 'supplier_payment' => 'Supplier Payment', 'accounting_journal' => 'Accounting Journal') as $value => $label) {
	print '<option value="'.piEscape($value).'"'.($type === $value ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label><label>ID dokumen<input class="flat" type="number" min="1" name="document_id" value="'.($documentId ?: '').'"></label><button class="button" type="submit">Tampilkan</button></form>';
print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table"><thead><tr class="liste_titre"><th>Waktu</th><th>Dokumen</th><th>Revisi</th><th>Aksi</th><th>Alasan</th><th>Snapshot</th><th>Pengguna</th></tr></thead><tbody>';
foreach ($rows as $row) {
	$userName = trim($row->firstname.' '.$row->lastname);
	if ($userName === '') {
		$userName = $row->login ?: '#'.$row->fk_user;
	}
	print '<tr class="oddeven"><td class="nowraponall">'.piEscape(dol_print_date($db->jdate($row->date_action), 'dayhour')).'</td><td>'.piEscape($row->element_type).' — <b>'.piEscape($row->element_ref).'</b> (#'.(int) $row->fk_element.')</td><td>R'.(int) $row->revision_no.'</td><td>'.($row->action_code === 'cancel' ? 'Cancel' : 'Revisi / Amend').'</td><td>'.nl2br(piEscape($row->reason)).'</td><td>'.($row->snapshot_json !== null && $row->snapshot_json !== '' ? 'Tersimpan' : '—').'</td><td>'.piEscape($userName).'</td></tr>';
}
if (!$rows) {
	print '<tr><td colspan="7" class="center pi-muted">Belum ada riwayat untuk filter ini.</td></tr>';
}
print '</tbody></table></div>';
piEnd();
