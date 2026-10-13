<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/lib/financial.php';
require_once __DIR__.'/class/pistockreport.class.php';

try {
	list($start, $end) = piReportDates();
	$product = GETPOSTINT('product');
	$warehouse = GETPOSTINT('warehouse');
	$includeZero = GETPOSTINT('include_zero') === 1;
	$rows = (new PiStockReport($pi))->stockBalance($start, $end, $product, $warehouse, $includeZero);
	$totals = array('opening_value' => 0, 'in_value' => 0, 'out_value' => 0, 'balance_value' => 0);
	foreach ($rows as $row) foreach ($totals as $field => $unused) $totals[$field] += (float) $row->$field;
	if (GETPOST('format', 'alpha') === 'xlsx') {
		$excelRows = (function () use ($rows) {
			foreach ($rows as $row) {
				$rate = (float) $row->balance_qty ? (float) $row->balance_value / (float) $row->balance_qty : 0;
				yield array($row->product_ref, $row->product_label, $row->warehouse_ref, $row->opening_qty, $row->opening_value, $row->in_qty, $row->in_value, $row->out_qty, $row->out_value, $row->balance_qty, $rate, $row->balance_value, $row->last_movement);
			}
		})();
		$qty = array('type' => 'number', 'format' => '#,##0.########');
		$money = array('type' => 'number', 'format' => '#,##0.00');
		piExcelOutput('stock-balance-'.$start.'-'.$end.'.xlsx', 'Stock Balance', array('Item Code', 'Item Name', 'Warehouse', 'Opening Qty', 'Opening Value', 'In Qty', 'In Value', 'Out Qty', 'Out Value', 'Balance Qty', 'Valuation Rate', 'Balance Value', 'Last Movement'), $excelRows, array(
			1 => array('width' => 24), 2 => array('width' => 40), 3 => array('width' => 20), 4 => $qty, 5 => $money, 6 => $qty, 7 => $money, 8 => $qty, 9 => $money, 10 => $qty, 11 => $money, 12 => $money, 13 => array('type' => 'datetime', 'format' => 'dd/mm/yyyy hh:mm:ss', 'width' => 20),
		));
	}
} catch (DomainException $e) {
	piHeader('Stock Balance'); print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>'; piEnd(); exit;
}

list($products, $warehouses) = piMasters();
piHeader('Stock Balance');
print '<p class="pi-report-intro">Ringkasan posisi stok per barang dan gudang. Saldo akhir = saldo awal + masuk − keluar, dihitung sampai akhir tanggal laporan.</p>';
print '<form method="get" class="pi-report-filter">';
print '<label for="start">Dari tanggal<input type="date" id="start" name="start" value="'.piEscape($start).'" required></label><label for="end">Sampai tanggal<input type="date" id="end" name="end" value="'.piEscape($end).'" required></label>';
print '<label for="product">Barang'; piProductSelect('product', $products, $product, false); print '</label><label for="warehouse">Gudang'; piSelect('warehouse', $warehouses, $warehouse, false); print '</label>';
print '<label class="pi-check"><input type="checkbox" name="include_zero" value="1"'.($includeZero ? ' checked' : '').'> Tampilkan saldo nol</label><button class="button" type="submit">Tampilkan</button>';
$query = array('start' => $start, 'end' => $end, 'product' => $product, 'warehouse' => $warehouse, 'include_zero' => $includeZero ? 1 : 0, 'format' => 'xlsx');
print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.http_build_query($query)).'">Export Excel (.xlsx)</a></form>';
piReportCards(array('Nilai Awal' => $totals['opening_value'], 'Nilai Masuk' => $totals['in_value'], 'Nilai Keluar' => $totals['out_value'], 'Nilai Akhir' => $totals['balance_value']));
print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-stock-balance"><thead><tr class="liste_titre"><th>Barang</th><th>Gudang</th><th class="right">Opening Qty</th><th class="right">Opening Value</th><th class="right">In Qty</th><th class="right">In Value</th><th class="right">Out Qty</th><th class="right">Out Value</th><th class="right">Balance Qty</th><th class="right">Valuation Rate</th><th class="right">Balance Value</th><th>Pergerakan Terakhir</th></tr></thead><tbody>';
foreach ($rows as $row) {
	$rate = (float) $row->balance_qty ? (float) $row->balance_value / (float) $row->balance_qty : 0;
	$link = piUrl('ledger.php?'.http_build_query(array('start' => $start, 'end' => $end, 'product' => $row->fk_product, 'warehouse' => $row->fk_warehouse)));
	print '<tr class="oddeven"><td><a href="'.piEscape($link).'"><b>'.piEscape($row->product_ref).'</b></a><br><small>'.piEscape($row->product_label).'</small></td><td>'.piEscape($row->warehouse_ref).'</td><td class="right">'.piQty($row->opening_qty).'</td><td class="right">'.piReportMoney($row->opening_value).'</td><td class="right">'.piQty($row->in_qty).'</td><td class="right">'.piReportMoney($row->in_value).'</td><td class="right">'.piQty($row->out_qty).'</td><td class="right">'.piReportMoney($row->out_value).'</td><td class="right">'.piQty($row->balance_qty).'</td><td class="right">'.piReportMoney($rate).'</td><td class="right">'.piReportMoney($row->balance_value).'</td><td class="nowraponall">'.piEscape(dol_print_date($db->jdate($row->last_movement), 'dayhour')).'</td></tr>';
}
if (!$rows) print '<tr><td colspan="12" class="center pi-muted">Tidak ada saldo atau pergerakan stok pada filter ini.</td></tr>';
print '<tr class="pi-report-grand"><td colspan="3">TOTAL NILAI</td><td class="right">'.piReportMoney($totals['opening_value']).'</td><td></td><td class="right">'.piReportMoney($totals['in_value']).'</td><td></td><td class="right">'.piReportMoney($totals['out_value']).'</td><td></td><td></td><td class="right">'.piReportMoney($totals['balance_value']).'</td><td></td></tr>';
print '</tbody></table></div><p class="pi-muted">Kuantitas tidak dijumlahkan antarbarang karena satuannya dapat berbeda. Klik kode barang untuk membuka rincian Stock Ledger dengan filter yang sama.</p>';
piEnd();
