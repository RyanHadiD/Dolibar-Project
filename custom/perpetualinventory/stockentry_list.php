<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pistockentry.class.php';

$service = new PiStockEntry($pi);
$purposes = PiStockEntry::purposes();
$search = trim(GETPOST('search', 'alphanohtml'));
$purpose = GETPOST('purpose', 'alpha');
$status = GETPOST('status', 'alphanohtml');
$where = 's.entity='.$pi->entity;
if ($search !== '') $where .= ' AND (s.ref LIKE '.$pi->str('%'.$search.'%').' OR s.note LIKE '.$pi->str('%'.$search.'%').')';
if ($purpose !== '' && isset($purposes[$purpose])) $where .= ' AND s.purpose='.$pi->str($purpose);
if ($status !== '' && in_array($status, array('0', '1'), true)) $where .= ' AND s.status='.(int) $status;
$rows = $pi->rows('SELECT s.*,u.login,(SELECT COUNT(*) FROM '.$pi->table('pi_stock_entry_line').' l WHERE l.fk_stock_entry=s.rowid) item_count FROM '.$pi->table('pi_stock_entry').' s LEFT JOIN '.$pi->table('user').' u ON u.rowid=s.fk_user_author WHERE '.$where.' ORDER BY s.rowid DESC LIMIT 500');

piHeader('Stock Entry');
print '<div class="pi-list-title"><div><h2>Stock Entry</h2><p class="pi-muted">Dokumen perpindahan, penerimaan, pengeluaran, dan penyesuaian stok.</p></div>';
if ($user->hasRight('perpetualinventory', 'write')) print '<a class="button button-save" href="'.piEscape(piUrl('stockentry_card.php?action=create')).'">+ New Stock Entry</a>';
print '</div>';
print '<form method="get" class="pi-report-filter"><label>Cari<input class="flat" name="search" value="'.piEscape($search).'" placeholder="Nomor / catatan"></label><label>Type<select class="flat" name="purpose"><option value="">Semua</option>';
foreach ($purposes as $key => $label) print '<option value="'.piEscape($key).'"'.($purpose === $key ? ' selected' : '').'>'.piEscape($label).'</option>';
print '</select></label><label>Status<select class="flat" name="status"><option value="">Semua</option><option value="0"'.($status === '0' ? ' selected' : '').'>Draft</option><option value="1"'.($status === '1' ? ' selected' : '').'>Posted</option></select></label><button class="button" type="submit">Filter</button></form>';
print '<div class="pi-scroll"><table class="noborder centpercent pi-document-list"><thead><tr class="liste_titre"><th>Reference</th><th>Posting Date</th><th>Stock Entry Type</th><th class="right">Items</th><th>Catatan</th><th>Dibuat oleh</th><th class="center">Status</th></tr></thead><tbody>';
if (!$rows) print '<tr><td colspan="7" class="opacitymedium center">Belum ada Stock Entry.</td></tr>';
foreach ($rows as $row) {
	print '<tr class="oddeven"><td><a href="'.piEscape(piUrl('stockentry_card.php?id='.$row->rowid)).'"><strong>'.piEscape($row->ref).'</strong></a></td><td>'.piEscape(substr($row->date_post, 0, 16)).'</td><td>'.piEscape($purposes[$row->purpose] ?? $row->purpose).'</td><td class="right">'.(int) $row->item_count.'</td><td>'.piEscape($row->note).'</td><td>'.piEscape($row->login).'</td><td class="center"><span class="pi-status pi-status-'.$row->status.'">'.($row->status ? 'Posted' : 'Draft').'</span></td></tr>';
}
print '</tbody></table></div>';
piEnd();
