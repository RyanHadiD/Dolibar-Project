<?php
require_once __DIR__.'/lib/common.php';
/* Views */
piHeader('Rekonsiliasi stok dan buku besar');
$r = $pi->reconciliation();
$ok = !$r['quantity_errors'] && abs($r['difference']) < 0.005 && abs($r['grni_difference']) < 0.005;
print '<p class="'.($ok ? 'pi-ok' : 'pi-error').'"><b>'.($ok ? 'Saldo saat ini cocok.' : 'Ada selisih yang perlu diperiksa.').'</b></p>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Pemeriksaan</th><th class="right">Nilai</th></tr>';
foreach (array('Total nilai buku stok' => $r['ledger'], 'Saldo seluruh jurnal pada akun persediaan' => $r['gl'], 'Selisih' => $r['difference']) as $label => $amount) {
	print '<tr class="oddeven"><td>'.piEscape($label).'</td><td class="right">'.piMoney($amount).'</td></tr>';
}
print '</table><p>Perbandingan menggunakan saldo saat ini, termasuk jurnal manual dan saldo awal pada akun persediaan. Gunakan akun persediaan khusus untuk barang yang dikelola modul.</p>';
print '<h3>Barang diterima belum ditagih</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Pemeriksaan</th><th class="right">Nilai</th></tr>';
foreach (array('Nilai penerimaan neto dikurangi tagihan tervalidasi yang dicocokkan' => $r['grni_expected'], 'Saldo kredit akun sementara di buku besar' => $r['grni_gl'], 'Selisih (termasuk tagihan belum ditransfer ke jurnal)' => $r['grni_difference']) as $label => $amount) {
	print '<tr class="oddeven"><td>'.piEscape($label).'</td><td class="right">'.piMoney($amount).'</td></tr>';
}
print '</table><p>Jika tagihan telah divalidasi tetapi belum ditransfer ke jurnal pembelian Dolibarr, selisih akun sementara masih akan terlihat.</p>';
print '<h3>Vendor Invoice stok yang tidak masuk GRNI</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Tanggal</th><th>Vendor Invoice</th><th>Akun yang dipakai</th><th class="right">Debit neto</th></tr>';
foreach ($r['wrong_supplier_postings'] as $posting) {
	print '<tr class="oddeven"><td>'.piEscape($posting->doc_date).'</td><td>'.piEscape($posting->doc_ref).'</td><td>'.piEscape($posting->numero_compte.' — '.$posting->label_compte).'</td><td class="right">'.piMoney($posting->amount).'</td></tr>';
}
if (!$r['wrong_supplier_postings']) {
	print '<tr><td colspan="4">Tidak ditemukan jurnal Vendor Invoice ke Persediaan/HPP sejak persiapan saldo awal.</td></tr>';
}
print '</table><p>Baris di atas menjelaskan HPP atau Persediaan yang berubah langsung dari Vendor Invoice. Dalam alur perpetual, baris barang harus dicocokkan dahulu sehingga masuk ke GRNI. Jurnal historis tidak diubah otomatis.</p>';
print '<h3>Selisih kuantitas</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Barang</th><th>Gudang</th><th class="right">Stok Dolibarr</th><th class="right">Buku stok</th></tr>';
foreach ($r['quantity_errors'] as $e) {
	print '<tr class="oddeven"><td>'.piEscape($e->ref).'</td><td>'.piEscape($e->warehouse).'</td><td class="right">'.piMoney($e->native_qty).'</td><td class="right">'.piMoney($e->ledger_qty).'</td></tr>';
}
if (!$r['quantity_errors']) {
	print '<tr><td colspan="4">Tidak ada selisih kuantitas.</td></tr>';
}
print '</table><h3>Keseimbangan jurnal otomatis</h3>';
$bad = $pi->rows('SELECT e.rowid,SUM(j.debit-j.credit) difference,COUNT(*) lines FROM '.$pi->table('pi_entry').' e JOIN '.$pi->table('accounting_bookkeeping')." j ON j.entity=e.entity AND ((j.doc_type='perpetualinventory' AND j.fk_doc=e.rowid) OR (j.doc_type IN ('reception','shipment') AND j.fk_docdet=e.rowid)) WHERE e.entity=".$pi->entity.' AND e.journal_posted=1 GROUP BY e.rowid HAVING ABS(SUM(j.debit-j.credit))>0.005 OR COUNT(*)<>2');
$missing = $pi->rows('SELECT e.rowid FROM '.$pi->table('pi_entry').' e LEFT JOIN '.$pi->table('accounting_bookkeeping')." j ON j.entity=e.entity AND ((j.doc_type='perpetualinventory' AND j.fk_doc=e.rowid) OR (j.doc_type IN ('reception','shipment') AND j.fk_docdet=e.rowid)) WHERE e.entity=".$pi->entity.' AND e.journal_posted=1 AND ABS(e.value_delta)>0 AND j.rowid IS NULL');
print '<p>'.(count($bad) + count($missing) ? '<span class="pi-error">Ditemukan jurnal hilang/tidak seimbang: '.(count($bad) + count($missing)).'. Periksa buku besar.</span>' : 'Semua jurnal otomatis yang diperiksa seimbang dan tersedia.').'</p>';
piEnd();
