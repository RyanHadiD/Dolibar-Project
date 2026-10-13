<?php
require_once __DIR__.'/lib/common.php';
if (!$user->hasRight('perpetualinventory', 'write')) {
	accessforbidden();
}
/* Actions */
if (GETPOST('action', 'aZ09') === 'post') {
	piPost('write');
	try {
		$kind = GETPOST('kind', 'alpha');
		$pi->operate($kind, GETPOSTINT('product'), GETPOSTINT('warehouse'), piNumber('qty'), GETPOST('cost', 'alphanohtml') === '' ? null : piNumber('cost'), GETPOSTINT('target'), GETPOSTINT('source'), GETPOST('note', 'alphanohtml'), GETPOST('request_key', 'alphanohtml'), $user);
		setEventMessages('Operasi stok dan jurnal terkait berhasil diposting.', null, 'mesgs');
		header('Location: '.piUrl('ledger.php'));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}
/* Views */
piHeader('Operasi stok');
list($products, $warehouses) = piMasters();
$openingAllowed = !$pi->one('SELECT rowid FROM '.$pi->table('pi_entry').' WHERE entity='.$pi->entity." AND kind<>'opening' LIMIT 1");
print '<div class="pi-help"><b>Saldo awal</b> memakai akun Penyesuaian Saldo Awal dan hanya tersedia sebelum transaksi operasional pertama. Status input saldo awal: <b>'.($openingAllowed ? 'masih terbuka' : 'sudah ditutup karena ada transaksi operasional').'</b>.<br><b>Penyesuaian/opname</b> memakai akun Selisih Stok dan hanya untuk koreksi fisik setelah operasional berjalan. Penerimaan dan pengiriman normal tetap dibuat melalui menu Dolibarr. Retur fisik tidak membuat credit note atau membatalkan invoice pelanggan.</div>';
print '<form method="post" class="pi-form">';
piToken('post');
print '<input type="hidden" name="request_key" value="'.piEscape(GETPOST('request_key', 'alphanohtml') ?: bin2hex(random_bytes(16))).'">';
print '<label for="kind">Jenis operasi</label>';
$operationOptions = array('count' => 'Stock opname — isi jumlah fisik akhir', 'adjustment' => 'Penyesuaian selisih stok (+/-)', 'transfer' => 'Transfer antargudang', 'return' => 'Retur terhadap transaksi asal', 'reversal' => 'Pembalik seluruh transaksi terakhir');
if ($openingAllowed) {
	$operationOptions = array('opening' => 'Saldo awal — sebelum transaksi operasional') + $operationOptions;
}
piSelect('kind', $operationOptions, GETPOST('kind', 'alpha'));
print '<label for="product">Barang (opname, penyesuaian, transfer)</label>';
piProductSelect('product', $products, GETPOSTINT('product'), false);
print '<label for="warehouse">Gudang asal</label>';
piSelect('warehouse', $warehouses, GETPOSTINT('warehouse'), false);
print '<label for="qty">Jumlah</label><input id="qty" name="qty" type="number" step="0.00000001" required value="'.piEscape(GETPOST('qty', 'alphanohtml')).'"><p class="pi-muted">Saldo awal/retur/transfer: jumlah positif. Pembalik: isi 1, sistem membalik seluruh jumlah transaksi asal. Opname: jumlah fisik akhir, termasuk nol.</p>';
print '<label for="cost">Modal per unit (saldo awal / penyesuaian masuk)</label><input id="cost" name="cost" type="number" min="0" step="0.00000001" value="'.piEscape(GETPOST('cost', 'alphanohtml')).'"><p class="pi-muted">Barang keluar menggunakan Moving Average. Transfer/retur menggunakan nilai asal.</p>';
print '<label for="target">Gudang tujuan (khusus transfer)</label>';
piSelect('target', $warehouses, GETPOSTINT('target'), false);
print '<label for="source">ID buku stok asal (khusus retur/pembalik)</label><input id="source" name="source" type="number" min="1" value="'.(GETPOSTINT('source') ?: '').'"><p><a href="'.piEscape(piUrl('ledger.php')).'">Cari ID transaksi pada Buku Stok</a>.</p>';
print '<label for="note">Alasan / catatan</label><input class="minwidth300" id="note" name="note" maxlength="255" required value="'.piEscape(GETPOST('note', 'alphanohtml')).'"><p>Posting menggunakan waktu saat ini. Perubahan stok dan jurnal disimpan bersama.</p><button class="button" type="submit">Posting operasi stok</button></form>';
piEnd();
