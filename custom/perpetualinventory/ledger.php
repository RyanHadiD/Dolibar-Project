<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/lib/financial.php';
require_once __DIR__.'/class/pistockreport.class.php';

try {
	list($start, $end) = piReportDates();
	$product = GETPOSTINT('product');
	$warehouse = GETPOSTINT('warehouse');
	$kind = GETPOST('kind', 'alpha');
	$id = GETPOSTINT('id');
	$page = max(0, GETPOSTINT('page'));
	$kinds = array('' => 'Semua transaksi', 'opening' => 'Saldo awal', 'receipt' => 'Penerimaan', 'delivery' => 'Pengiriman', 'adjustment' => 'Penyesuaian / opname', 'transfer_out' => 'Transfer keluar', 'transfer_in' => 'Transfer masuk', 'return' => 'Retur', 'reversal' => 'Pembalik');
	if (!isset($kinds[$kind])) throw new DomainException('Jenis transaksi tidak valid.');
	$reporter = new PiStockReport($pi);
	$summary = $reporter->ledgerSummary($start, $end, $product, $warehouse, $kind);
	$isExcel = GETPOST('format', 'alpha') === 'xlsx';
	$rows = $reporter->ledger($start, $end, $product, $warehouse, $kind, $isExcel ? 0 : 51, $page * 50);
	if ($isExcel) {
		$excelRows = (function () use ($rows) {
			foreach ($rows as $row) {
				$rate = (float) $row->balance_qty ? (float) $row->balance_value / (float) $row->balance_qty : 0;
				yield array(substr($row->date_post, 0, 10), '1970-01-01 '.substr($row->date_post, 11, 8), $row->product_ref, $row->product_label, $row->warehouse_ref, $row->origin_type ?: 'perpetualinventory', $row->voucher_ref, piKind($row->kind), $row->qty > 0 ? $row->qty : 0, $row->qty < 0 ? -$row->qty : 0, $row->qty > 0 ? $row->unit_cost : 0, $row->value_delta, $row->balance_qty, $rate, $row->balance_value, $row->journal_posted == 1 ? 'Posted' : 'No GL', $row->note);
			}
		})();
		$qty = array('type' => 'number', 'format' => '#,##0.########');
		$money = array('type' => 'number', 'format' => '#,##0.00');
		piExcelOutput('stock-ledger-'.$start.'-'.$end.'.xlsx', 'Stock Ledger', array('Posting Date', 'Posting Time', 'Item Code', 'Item Name', 'Warehouse', 'Voucher Type', 'Voucher No', 'Transaction', 'Incoming Qty', 'Outgoing Qty', 'Incoming Rate', 'Value Change', 'Balance Qty', 'Valuation Rate', 'Balance Value', 'Journal Status', 'Remarks'), $excelRows, array(
			1 => array('type' => 'date', 'format' => 'dd/mm/yyyy', 'width' => 13), 2 => array('type' => 'time', 'format' => 'hh:mm:ss', 'width' => 12), 3 => array('width' => 24), 4 => array('width' => 40), 5 => array('width' => 20), 6 => array('width' => 18), 7 => array('width' => 20), 8 => array('width' => 22), 9 => $qty, 10 => $qty, 11 => $money, 12 => $money, 13 => $qty, 14 => $money, 15 => $money, 16 => array('width' => 14), 17 => array('width' => 40),
		));
	}
} catch (DomainException $e) {
	piHeader('Stock Ledger'); print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>'; piEnd(); exit;
}

list($products, $warehouses) = piMasters();
$more = count($rows) > 50;
$rows = array_slice($rows, 0, 50);
piHeader('Stock Ledger');
print '<p class="pi-report-intro">Riwayat immutable seluruh pergerakan stok, nilai, saldo berjalan, dan voucher sumber. Saldo berjalan dihitung per kombinasi barang dan gudang.</p>';
print '<form method="get" class="pi-report-filter">';
print '<label for="start">Dari tanggal<input type="date" id="start" name="start" value="'.piEscape($start).'" required></label>';
print '<label for="end">Sampai tanggal<input type="date" id="end" name="end" value="'.piEscape($end).'" required></label>';
print '<label for="product">Barang'; piProductSelect('product', $products, $product, false); print '</label>';
print '<label for="warehouse">Gudang'; piSelect('warehouse', $warehouses, $warehouse, false); print '</label>';
print '<label for="kind">Jenis<select class="flat minwidth150" id="kind" name="kind">';
foreach ($kinds as $value => $label) print '<option value="'.piEscape($value).'"'.($kind === $value ? ' selected' : '').'>'.piEscape($label).'</option>';
print '</select></label><button class="button" type="submit">Tampilkan</button>';
$query = array('start' => $start, 'end' => $end, 'product' => $product, 'warehouse' => $warehouse, 'kind' => $kind, 'format' => 'xlsx');
print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.http_build_query($query)).'">Export Excel (.xlsx)</a></form>';
piReportCards(array('Nilai Awal' => (float) $summary->opening_value, 'Nilai Masuk' => (float) $summary->in_value, 'Nilai Keluar' => (float) $summary->out_value, 'Nilai Akhir' => (float) $summary->closing_value));
print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-stock-ledger"><thead><tr class="liste_titre">';
$numeric = array('Masuk', 'Keluar', 'Incoming Rate', 'Perubahan Nilai', 'Saldo Qty', 'Valuation Rate', 'Saldo Nilai');
foreach (array('Tanggal / waktu', 'Barang', 'Gudang', 'Voucher', 'Transaksi', 'Masuk', 'Keluar', 'Incoming Rate', 'Perubahan Nilai', 'Saldo Qty', 'Valuation Rate', 'Saldo Nilai', 'Jurnal') as $heading) print '<th'.(in_array($heading, $numeric, true) ? ' class="right"' : '').'>'.piEscape($heading).'</th>';
print '</tr></thead><tbody>';
foreach ($rows as $row) {
	$rate = (float) $row->balance_qty ? (float) $row->balance_value / (float) $row->balance_qty : 0;
	print '<tr class="oddeven"><td class="nowraponall">'.piEscape(dol_print_date($db->jdate($row->date_post), 'dayhour')).'</td><td><b>'.piEscape($row->product_ref).'</b><br><small>'.piEscape($row->product_label).'</small></td><td>'.piEscape($row->warehouse_ref).'</td><td>'.piVoucherLink($row).'<br><small><a href="'.piEscape(piUrl('ledger.php?id='.(int) $row->rowid.'&start='.$start.'&end='.$end)).'">PI-'.(int) $row->rowid.'</a></small></td><td>'.piEscape(piKind($row->kind)).'<br><small>'.piEscape($row->note).'</small></td>';
	print '<td class="right">'.piQty($row->qty > 0 ? $row->qty : 0).'</td><td class="right">'.piQty($row->qty < 0 ? -$row->qty : 0).'</td><td class="right">'.piReportMoney($row->qty > 0 ? $row->unit_cost : 0).'</td><td class="right">'.piReportMoney($row->value_delta).'</td><td class="right">'.piQty($row->balance_qty).'</td><td class="right">'.piReportMoney($rate).'</td><td class="right">'.piReportMoney($row->balance_value).'</td><td>'.($row->journal_posted == 1 ? '<span class="pi-ok">Diposting</span>' : 'Tanpa GL').'</td></tr>';
}
if (!$rows) print '<tr><td colspan="13" class="center pi-muted">Tidak ada pergerakan stok pada filter ini.</td></tr>';
print '</tbody></table></div>';
$base = array('start' => $start, 'end' => $end, 'product' => $product, 'warehouse' => $warehouse, 'kind' => $kind);
print '<p>';
if ($page) print '<a href="'.piEscape(piUrl('ledger.php?'.http_build_query($base + array('page' => $page - 1)))).'">← Sebelumnya</a> ';
if ($more) print '<a href="'.piEscape(piUrl('ledger.php?'.http_build_query($base + array('page' => $page + 1)))).'">Berikutnya →</a>';
print '</p>';
if ($id) {
	print '<h3>Jurnal PI-'.(int) $id.'</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Akun</th><th>Keterangan</th><th class="right">Debit</th><th class="right">Kredit</th></tr>';
	$journals = $pi->rows('SELECT numero_compte,label_compte,label_operation,debit,credit FROM '.$pi->table('accounting_bookkeeping').' WHERE entity='.$pi->entity." AND ((doc_type='perpetualinventory' AND fk_doc=".$id.") OR (doc_type IN ('reception','shipment') AND fk_docdet=".$id.')) ORDER BY rowid');
	foreach ($journals as $journal) print '<tr class="oddeven"><td>'.piEscape($journal->numero_compte.' — '.$journal->label_compte).'</td><td>'.piEscape($journal->label_operation).'</td><td class="right">'.piMoney($journal->debit).'</td><td class="right">'.piMoney($journal->credit).'</td></tr>';
	if (!$journals) print '<tr><td colspan="4" class="pi-muted">Entri ini tidak membuat jurnal buku besar.</td></tr>';
	print '</table><p><a href="'.piEscape(piUrl('operations.php?source='.$id)).'">Buat retur atau pembalik dari transaksi ini</a></p>';
}
piEnd();
