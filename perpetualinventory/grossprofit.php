<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pifinancialreport.class.php';
require_once __DIR__.'/lib/financial.php';

try {
	list($start, $end) = piReportDates();
	$rows = (new PiFinancialReport($pi))->grossProfit($start, $end);
	$totalSales = $totalBuying = $totalProfit = $totalQty = 0;
	foreach ($rows as $row) {
		$totalQty += $row->qty;
		$totalSales += $row->selling_amount;
		$totalBuying += $row->buying_amount;
		$totalProfit += $row->gross_profit;
	}
	$totalMargin = $totalSales ? $totalProfit / $totalSales * 100 : 0;
	if (GETPOST('format', 'alpha') === 'xlsx') {
		$excelRows = array();
		foreach ($rows as $row) {
			$excelRows[] = array($row->sales_invoice, $row->delivery_note, $row->sales_order, $row->item_code, $row->item_name, $row->customer, $row->posting_date, $row->warehouse, $row->qty, $row->selling_rate, $row->valuation_rate, $row->selling_amount, $row->buying_amount, $row->gross_profit, $row->gross_profit_percent / 100);
		}
		$excelRows[] = array('', '', '', '', 'TOTAL', '', '', '', $totalQty, '', '', $totalSales, $totalBuying, $totalProfit, $totalMargin / 100);
		$qty = array('type' => 'number', 'format' => '#,##0.########');
		$money = array('type' => 'number', 'format' => '#,##0.00');
		piExcelOutput('gross-profit-'.$start.'-'.$end.'.xlsx', 'Gross Profit', array('Sales Invoice', 'Delivery Note', 'Sales Order', 'Item Code', 'Item Name', 'Customer', 'Posting Date', 'Warehouse', 'Qty', 'Avg. Selling Rate', 'Valuation Rate', 'Selling Amount', 'Buying Amount', 'Gross Profit', 'Gross Profit %'), $excelRows, array(
			1 => array('width' => 20), 2 => array('width' => 20), 3 => array('width' => 20), 4 => array('width' => 24), 5 => array('width' => 40), 6 => array('width' => 30), 7 => array('type' => 'date', 'format' => 'dd/mm/yyyy', 'width' => 13), 8 => array('width' => 20), 9 => $qty, 10 => $money, 11 => $money, 12 => $money, 13 => $money, 14 => $money, 15 => array('type' => 'number', 'format' => '0.00%', 'width' => 16),
		));
	}
} catch (DomainException $e) {
	piHeader('Gross Profit');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

piHeader('Gross Profit');
piReportFilter($start, $end);
print '<p class="pi-report-intro">Profitabilitas per barang yang sudah dikirim dan terhubung ke Customer Invoice tervalidasi. Nilai modal memakai moving-average aktual pada saat shipment.</p>';
piReportCards(array(
	'Total Penjualan' => $totalSales,
	'Total Modal/HPP' => $totalBuying,
	'Gross Profit' => array($totalProfit, $totalProfit >= 0 ? 'pi-positive' : 'pi-negative'),
	'Gross Profit %' => array($totalMargin, $totalMargin >= 0 ? 'pi-positive' : 'pi-negative', ' %'),
));
print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-gp-table"><thead><tr class="liste_titre">';
foreach (array('Sales Invoice', 'Delivery Note', 'Sales Order', 'Item Code', 'Item Name', 'Customer', 'Posting Date', 'Warehouse', 'Qty', 'Avg. Selling Rate', 'Valuation Rate', 'Selling Amount', 'Buying Amount', 'Gross Profit', 'Gross Profit %') as $heading) {
	print '<th'.(in_array($heading, array('Qty', 'Avg. Selling Rate', 'Valuation Rate', 'Selling Amount', 'Buying Amount', 'Gross Profit', 'Gross Profit %'), true) ? ' class="right"' : '').'>'.piEscape($heading).'</th>';
}
print '</tr></thead><tbody>';
foreach ($rows as $row) {
	print '<tr class="oddeven"><td>'.piEscape($row->sales_invoice).'</td><td>'.piEscape($row->delivery_note).'</td><td>'.piEscape($row->sales_order).'</td><td>'.piEscape($row->item_code).'</td><td>'.piEscape($row->item_name).'</td><td>'.piEscape($row->customer).'</td><td>'.piEscape(dol_print_date($db->jdate($row->posting_date), 'day')).'</td><td>'.piEscape($row->warehouse).'</td>';
	print '<td class="right">'.price($row->qty, 0, '', 1, -1, -1, '').'</td><td class="right">'.piReportMoney($row->selling_rate).'</td><td class="right">'.piReportMoney($row->valuation_rate).'</td><td class="right">'.piReportMoney($row->selling_amount).'</td><td class="right">'.piReportMoney($row->buying_amount).'</td><td class="right '.($row->gross_profit >= 0 ? 'pi-positive' : 'pi-negative').'">'.piReportMoney($row->gross_profit).'</td><td class="right '.($row->gross_profit_percent >= 0 ? 'pi-positive' : 'pi-negative').'">'.price($row->gross_profit_percent).' %</td></tr>';
}
if (!$rows) {
	print '<tr><td colspan="15" class="center pi-muted">Belum ada shipment yang terhubung ke Customer Invoice tervalidasi pada periode ini.</td></tr>';
}
print '<tr class="pi-report-grand"><td colspan="8">TOTAL</td><td class="right">'.price($totalQty, 0, '', 1, -1, -1, '').'</td><td></td><td></td><td class="right">'.piReportMoney($totalSales).'</td><td class="right">'.piReportMoney($totalBuying).'</td><td class="right">'.piReportMoney($totalProfit).'</td><td class="right">'.price($totalMargin).' %</td></tr>';
print '</tbody></table></div>';
print '<p class="pi-muted">Selling Amount dialokasikan berdasarkan harga rata-rata baris invoice untuk barang yang sama. Buying Amount berasal dari nilai buku stok saat Delivery Note diposting. Customer Invoice draft dan shipment yang belum ditagih tidak dimasukkan.</p>';
piEnd();
