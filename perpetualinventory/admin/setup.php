<?php
require_once __DIR__.'/../lib/common.php';
if (!$user->admin) {
	accessforbidden();
}
/* Actions */
$action = GETPOST('action', 'aZ09');
if ($action) {
	piPost();
	try {
		if ($action === 'save') {
			$data = array();
			foreach (array('stock_account', 'grni_account', 'cogs_account', 'adjustment_account', 'opening_account', 'journal_code') as $field) {
				$data[$field] = GETPOST($field, 'alphanohtml');
			}
			$data['lock_date'] = GETPOST('lock_date', 'alphanohtml');
			if ($data['lock_date'] && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['lock_date']) || date('Y-m-d', strtotime($data['lock_date'])) !== $data['lock_date'] || $data['lock_date'] > date('Y-m-d'))) {
				throw new DomainException('Tanggal kunci tidak valid atau di masa depan.');
			}
			$pi->saveConfig($data, $user);
		} elseif ($action === 'opening') {
			$pi->opening(GETPOSTINT('product'), GETPOSTINT('warehouse'), piNumber('cost'), GETPOST('opening_mode', 'alpha') === 'adopt', $user);
		} elseif ($action === 'start') {
			$pi->start($user);
		}
		setEventMessages('Pengaturan berhasil disimpan.', null, 'mesgs');
		header('Location: '.piUrl('admin/setup.php'));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}
/* Views */
piHeader('Pengaturan Perpetual Inventory');
$c = $pi->config();
$accounts = $journals = array();
$chart = '';
try {
	$chart = $pi->chartVersion();
} catch (DomainException $e) {
	print '<div class="pi-help">'.piEscape($e->getMessage()).'</div>';
}
foreach ($pi->rows('SELECT account_number,label FROM '.$pi->table('accounting_account').' WHERE entity='.$pi->entity.' AND active=1 AND fk_pcg_version='.$pi->str($chart).' ORDER BY account_number') as $a) {
	$accounts[$a->account_number] = $a->account_number.' — '.$a->label;
}
foreach ($pi->rows('SELECT code,label FROM '.$pi->table('accounting_journal').' WHERE entity='.$pi->entity.' AND active=1 ORDER BY code') as $j) {
	$journals[$j->code] = $j->code.' — '.$j->label;
}
print '<div class="pi-help"><b>Alur setara ERPNext</b><br><br><table class="noborder centpercent"><tr class="liste_titre"><th>Dokumen Dolibarr</th><th>Debit</th><th>Kredit</th><th>Pembuat jurnal</th></tr><tr><td>Reception (Penerimaan)</td><td>Persediaan</td><td>Barang diterima belum ditagih / GRNI</td><td>Modul ini, saat validasi</td></tr><tr><td>Vendor Invoice (Tagihan supplier)</td><td>GRNI (+ pajak masukan)</td><td>Hutang usaha</td><td>Jurnal Pembelian Dolibarr</td></tr><tr><td>Shipment (Pengiriman)</td><td>HPP dari Accounting code (purchase) produk; fallback HPP global</td><td>Persediaan</td><td>Modul ini, saat validasi</td></tr><tr><td>Customer Invoice (Invoice pelanggan)</td><td>Piutang usaha</td><td>Penjualan (+ pajak keluaran)</td><td>Jurnal Penjualan Dolibarr</td></tr></table><br>Nomor Reception/Shipment tampil sebagai referensi voucher; PI-ID tetap ada pada keterangan untuk audit. Modul tidak membuat ulang jurnal invoice agar tidak terjadi duplikasi.</div>';
print '<div class="pi-help">1. Pilih akun sesuai bagan akun perusahaan. 2. Impor nilai stok yang sudah ada. 3. Cocokkan buku stok dan buku besar. 4. Mulai posting otomatis.<br>Atur stok Dolibarr untuk berubah saat <b>validasi penerimaan</b> dan <b>validasi pengiriman</b>. Perubahan saat pesanan/invoice harus nonaktif.</div>';
$workflowProblems = array();
try {
	$pi->validateWorkflow();
} catch (DomainException $e) {
	$workflowProblems[] = $e->getMessage();
}
if ($workflowProblems) {
	print '<div class="pi-error"><b>Alur stok belum siap.</b><br>'.piEscape(implode(' ', $workflowProblems)).'<br>Ubah melalui Pengaturan → Produk → Stok. Opsi kit boleh tetap aktif; hanya transaksi barang yang benar-benar menjadi induk/komponen kit yang ditolak.</div>';
} else {
	print '<div class="pi-ok"><b>Pengaturan pemicu stok sudah sesuai.</b> Opsi kit tidak memblokir barang biasa.</div>';
}
print '<form method="post" class="pi-form">';
piToken('save');
foreach (array('stock_account' => 'Akun aset persediaan', 'grni_account' => 'Barang diterima belum ditagih (kewajiban sementara)', 'cogs_account' => 'Harga pokok penjualan (fallback jika produk belum memiliki akun purchase/HPP)', 'adjustment_account' => 'Selisih stok', 'opening_account' => 'Penyesuaian saldo awal') as $f => $label) {
	print '<label for="'.$f.'">'.piEscape($label).'</label>';
	piSelect($f, $accounts, $c->$f ?? '');
}
print '<label for="journal_code">Jurnal persediaan</label>';
piSelect('journal_code', $journals, $c->journal_code ?? '');
print '<label for="lock_date">Kunci transaksi sampai tanggal (inklusif)</label><input id="lock_date" type="date" name="lock_date" value="'.piEscape($c->lock_date ?? '').'"><br><button class="button" type="submit">Simpan pengaturan</button></form>';
if (!$c || !$c->enabled) {
	list($products, $warehouses) = piMasters();
	$openingRows = $pi->rows('SELECT ps.fk_product,ps.fk_entrepot,p.ref product_ref,p.label product_label,w.ref warehouse_ref,ps.reel native_qty,p.pmp,COALESCE(b.qty,0) ledger_qty,COALESCE(b.stock_value,0) ledger_value FROM '.$pi->table('product_stock').' ps JOIN '.$pi->table('product').' p ON p.rowid=ps.fk_product JOIN '.$pi->table('entrepot').' w ON w.rowid=ps.fk_entrepot LEFT JOIN '.$pi->table('pi_balance').' b ON b.entity='.$pi->entity.' AND b.fk_product=ps.fk_product AND b.fk_warehouse=ps.fk_entrepot WHERE p.entity='.$pi->entity.' AND w.entity='.$pi->entity.' AND p.fk_product_type=0 AND p.stockable_product=1 AND ABS(ps.reel)>0.00000001 ORDER BY p.ref,w.ref');
	print '<h3>Kesiapan saldo awal</h3><div class="pi-scroll"><table class="noborder centpercent"><tr class="liste_titre"><th>Barang</th><th>Gudang</th><th class="right">Stok Dolibarr</th><th class="right">PMP Dolibarr</th><th class="right">Jumlah buku stok</th><th class="right">Nilai buku stok</th><th>Status</th></tr>';
	foreach ($openingRows as $row) {
		$ready = abs($row->native_qty - $row->ledger_qty) < 0.0000001;
		$status = $row->native_qty < 0 ? 'Stok negatif—selesaikan dahulu' : ($ready ? 'Siap' : 'Belum diimpor');
		print '<tr class="oddeven"><td>'.piEscape($row->product_ref.' — '.$row->product_label).'</td><td>'.piEscape($row->warehouse_ref).'</td><td class="right">'.piMoney($row->native_qty).'</td><td class="right">'.piMoney($row->pmp).'</td><td class="right">'.piMoney($row->ledger_qty).'</td><td class="right">'.piMoney($row->ledger_value).'</td><td class="'.($ready && $row->native_qty >= 0 ? 'pi-ok' : 'pi-error').'">'.piEscape($status).'</td></tr>';
	}
	if (!$openingRows) {
		print '<tr><td colspan="7">Tidak ada stok fisik positif yang perlu diimpor.</td></tr>';
	}
	print '</table></div><p class="pi-muted">PMP hanya ditampilkan sebagai acuan. Pastikan nilai modal benar. Proses “Mulai” tidak membuat jurnal, tidak menambah HPP, dan tidak menambah Hutang; proses tersebut hanya mengaktifkan trigger setelah seluruh rekonsiliasi cocok.</p>';
	$readiness = $pi->reconciliation();
	print '<h3>Ringkasan sebelum mulai</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Pemeriksaan</th><th class="right">Buku stok / seharusnya</th><th class="right">Buku besar</th><th class="right">Selisih</th></tr><tr class="oddeven"><td>Persediaan</td><td class="right">'.piMoney($readiness['ledger']).'</td><td class="right">'.piMoney($readiness['gl']).'</td><td class="right">'.piMoney($readiness['difference']).'</td></tr><tr class="oddeven"><td>Barang diterima belum ditagih / GRNI</td><td class="right">'.piMoney($readiness['grni_expected']).'</td><td class="right">'.piMoney($readiness['grni_gl']).'</td><td class="right">'.piMoney($readiness['grni_difference']).'</td></tr></table>';
	if ($readiness['quantity_errors'] || abs($readiness['difference']) > 0.005 || abs($readiness['grni_difference']) > 0.005) {
		print '<p class="pi-error"><b>Belum siap dimulai.</b> Lengkapi semua saldo awal dan pastikan kedua selisih menjadi 0. Proses mulai tidak akan menyeimbangkan angka ini secara otomatis.</p>';
	} else {
		print '<p class="pi-ok"><b>Saldo awal siap.</b> Semua pemeriksaan utama sudah cocok.</p>';
	}
	print '<form method="post" class="pi-form"><fieldset><legend>Impor nilai saldo awal</legend><p>Kuantitas diambil dari stok Dolibarr saat ini. Pengisian ini tidak menambah jumlah barang.</p>';
	piToken('opening');
	print '<label for="product">Barang</label>';
	piProductSelect('product', $products);
	print '<label for="warehouse">Gudang</label>';
	piSelect('warehouse', $warehouses);
	print '<label for="cost">Modal per unit</label><input id="cost" type="number" min="0" step="0.00000001" name="cost" required><label for="opening_mode">Pencatatan akuntansi</label>';
	piSelect('opening_mode', array('adopt' => 'Nilai sudah ada di buku besar — tanpa jurnal baru', 'post' => 'Nilai belum ada di buku besar — buat jurnal saldo awal'));
	print '<p class="pi-muted">Saldo awal bersifat permanen setelah disimpan. Periksa modal dan pilihan jurnal untuk menghindari saldo ganda. Penerimaan lama yang belum ditagih perlu diselesaikan sebelum migrasi.</p><button class="button" type="submit">Simpan saldo awal</button></fieldset></form>';
	print '<form method="post" class="pi-form">';
	piToken('start');
	print '<p><a href="'.piEscape(piUrl('reconcile.php')).'">Lihat hasil rekonsiliasi</a>. Tombol ini mengaktifkan posting otomatis untuk transaksi berikutnya.</p><button class="button" type="submit">Mulai perpetual inventory</button></form>';
} else {
	print '<div class="pi-help">Posting aktif sejak '.piEscape($c->start_date).'. Akun dan jurnal dikunci setelah transaksi pertama. Koreksi stok melalui Operasi stok.</div>';
}
piEnd();
