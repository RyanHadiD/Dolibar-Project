<?php
require_once __DIR__.'/lib/common.php';
/* Views */
piHeader('Perpetual Inventory');
$c = $pi->config();
if (!$c || !$c->enabled) {
	print '<div class="pi-help">Posting otomatis belum dimulai. Lengkapi akun, impor nilai saldo awal, lalu periksa rekonsiliasi melalui <a href="'.piEscape(piUrl('admin/setup.php')).'">Pengaturan</a>.</div>';
}
$r = $pi->reconciliation();
$cogs = $pi->one('SELECT COALESCE(SUM(-e.value_delta),0) amount FROM '.$pi->table('pi_entry').' e LEFT JOIN '.$pi->table('pi_entry').' s ON s.entity=e.entity AND s.rowid=e.fk_source WHERE e.entity='.$pi->entity.' AND e.date_post>='.$pi->str(date('Y-m-01 00:00:00'))." AND (e.kind='delivery' OR (e.kind IN ('return','reversal') AND s.kind='delivery'))");
$unbilled = $pi->one('SELECT COALESCE(SUM(e.value_delta),0) amount FROM '.$pi->table('pi_entry').' e WHERE e.entity='.$pi->entity.' AND e.contra_account='.$pi->str($c ? $c->grni_account : ''));
$matched = $pi->one('SELECT COALESCE(SUM(amount),0) amount FROM '.$pi->table('pi_match').' WHERE entity='.$pi->entity);
print '<div class="pi-cards">';
foreach (array('Nilai persediaan' => $r['ledger'], 'HPP bulan ini' => $cogs->amount, 'Penerimaan belum dicocokkan' => $unbilled->amount - $matched->amount, 'Selisih buku besar' => $r['difference']) as $label => $amount) {
	print '<div class="pi-card">'.piEscape($label).'<strong>'.piMoney($amount).'</strong></div>';
}
print '</div><p>Metode valuasi: <b>Moving Average per barang per gudang</b>. Status: <b>'.($c && $c->enabled ? 'Posting otomatis aktif' : 'Persiapan saldo awal').'</b>.</p>';
print '<div class="pi-help"><b>Pembelian:</b> Pesanan → Reception (Dr Persediaan / Cr GRNI) → Pencocokan → Vendor Invoice (Dr GRNI / Cr Hutang).<br><b>Penjualan:</b> Pesanan pelanggan → Shipment (Dr HPP produk, fallback HPP global / Cr Persediaan) → Customer Invoice (Dr Piutang / Cr Penjualan).</div>';
print '<p>Jurnal stok dibuat langsung. Jurnal utang, pajak, dan penjualan mengikuti proses transfer akuntansi standar Dolibarr. Pencocokan draft belum berarti tagihan sudah masuk buku besar.</p>';
$sourceButtons = '';
if ($user->admin || $user->hasRight('expedition', 'creer')) {
	$sourceButtons .= '<a class="butAction" href="'.piEscape(piUrl('get_items_from.php?target=shipment')).'">Ambil Item dari Sales Order</a> ';
}
if ($user->admin || $user->hasRight('reception', 'creer')) {
	$sourceButtons .= '<a class="butAction" href="'.piEscape(piUrl('get_items_from.php?target=reception')).'">Ambil Item dari Purchase Order</a>';
}
if ($sourceButtons !== '') {
	print '<p>'.$sourceButtons.'</p>';
}
print '<p><a class="butAction" href="'.piEscape(piUrl('operations.php')).'">Operasi stok</a> <a class="butAction" href="'.piEscape(piUrl('matching.php')).'">Pencocokan tagihan</a> <a class="butAction" href="'.piEscape(piUrl('reconcile.php')).'">Periksa rekonsiliasi</a></p>';
print '<p class="pi-muted">Versi 1.3.0: satu akun persediaan per entitas. FIFO, landed cost, selisih harga tagihan, manufaktur, dan perhitungan ulang transaksi mundur belum tersedia.</p>';
piEnd();
