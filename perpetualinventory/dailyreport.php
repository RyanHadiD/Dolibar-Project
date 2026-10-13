<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pidailyreport.class.php';
require_once __DIR__.'/lib/financial.php';

$reports = array(
	'ar-summary' => array('kind' => 'receivable', 'mode' => 'summary', 'title' => 'Accounts Receivable Summary'),
	'ap-summary' => array('kind' => 'payable', 'mode' => 'summary', 'title' => 'Accounts Payable Summary'),
	'sales-register' => array('kind' => 'receivable', 'mode' => 'register', 'title' => 'Sales Register'),
	'purchase-register' => array('kind' => 'payable', 'mode' => 'register', 'title' => 'Purchase Register'),
);

try {
	$reportKey = GETPOST('report', 'alphanohtml') ?: 'ar-summary';
	if (!isset($reports[$reportKey])) {
		throw new DomainException('Daily Report yang dipilih tidak tersedia.');
	}
	$report = $reports[$reportKey];
	$daily = new PiDailyReport($pi);
	$parties = $daily->parties($report['kind']);
	$partyId = GETPOST('party', 'int');
	if ($partyId && !isset($parties[$partyId])) {
		throw new DomainException('Customer atau supplier yang dipilih tidak tersedia.');
	}

	if ($report['mode'] === 'summary') {
		$reportDate = GETPOST('report_date', 'alphanohtml') ?: date('Y-m-d');
		piDailyValidateDate($reportDate);
		$rows = $daily->summary($report['kind'], $reportDate, $partyId);
		$totals = piDailySummaryTotals($rows);
		if (GETPOST('format', 'alpha') === 'xlsx') {
			piDailySummaryExcel($report, $reportDate, $rows, $totals);
		}
	} else {
		$start = GETPOST('start', 'alphanohtml') ?: date('Y-m-d');
		$end = GETPOST('end', 'alphanohtml') ?: date('Y-m-d');
		piDailyValidateDate($start);
		piDailyValidateDate($end);
		if ($start > $end) {
			throw new DomainException('Tanggal awal tidak boleh setelah tanggal akhir.');
		}
		$rows = $daily->register($report['kind'], $start, $end, $partyId);
		$totals = piDailyRegisterTotals($rows);
		if (GETPOST('format', 'alpha') === 'xlsx') {
			piDailyRegisterExcel($report, $start, $end, $rows, $totals);
		}
	}
} catch (DomainException $e) {
	piHeader('Daily Report');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

piHeader($report['title']);
print '<form method="get" class="pi-report-filter">';
print '<input type="hidden" name="report" value="'.piEscape($reportKey).'">';
if ($report['mode'] === 'summary') {
	print '<label for="report_date">Report Date<input type="date" id="report_date" name="report_date" value="'.piEscape($reportDate).'" required></label>';
} else {
	print '<label for="start">From Date<input type="date" id="start" name="start" value="'.piEscape($start).'" required></label>';
	print '<label for="end">To Date<input type="date" id="end" name="end" value="'.piEscape($end).'" required></label>';
}
$partyLabel = $report['kind'] === 'receivable' ? 'Customer' : 'Supplier';
print '<label for="party">'.piEscape($partyLabel).'<select id="party" name="party"><option value="0">Semua '.piEscape($partyLabel).'</option>';
foreach ($parties as $id => $name) {
	print '<option value="'.(int) $id.'"'.($partyId === (int) $id ? ' selected' : '').'>'.piEscape($name).'</option>';
}
print '</select></label><button class="button" type="submit">Tampilkan</button>';
$export = array('report' => $reportKey, 'party' => $partyId, 'format' => 'xlsx');
if ($report['mode'] === 'summary') {
	$export['report_date'] = $reportDate;
} else {
	$export['start'] = $start;
	$export['end'] = $end;
}
print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.http_build_query($export)).'">Export Excel (.xlsx)</a></form>';

if ($report['mode'] === 'summary') {
	print '<p class="pi-report-intro">Outstanding invoice tervalidasi per '.strtolower($partyLabel).' sampai <b>'.piEscape($reportDate).'</b>. Ageing dihitung dari due date seperti Accounts Receivable/Payable Summary di ERPNext.</p>';
	piReportCards(array('Invoiced Amount' => $totals['invoiced'], 'Paid Amount' => $totals['paid'], $report['kind'] === 'receivable' ? 'Credit Notes' : 'Debit Notes' => $totals['credit_note'], 'Outstanding Amount' => array($totals['outstanding'], $totals['outstanding'] >= 0 ? 'pi-positive' : 'pi-negative')));
	piDailySummaryTable($report, $rows, $totals);
} else {
	print '<p class="pi-report-intro">Register invoice tervalidasi untuk periode <b>'.piEscape($start).'</b> sampai <b>'.piEscape($end).'</b>. Nilai outstanding memperhitungkan pembayaran dan alokasi credit/debit note sampai tanggal akhir.</p>';
	piReportCards(array('Net Total' => $totals['net_total'], 'Tax Total' => $totals['tax_total'], 'Grand Total' => $totals['grand_total'], 'Outstanding Amount' => array($totals['outstanding'], $totals['outstanding'] >= 0 ? 'pi-positive' : 'pi-negative')));
	piDailyRegisterTable($report, $rows, $totals);
}
print '<p class="pi-muted">Angka menggunakan mata uang dasar invoice Dolibarr. Draft dan invoice yang dibatalkan tidak masuk laporan.</p>';
piEnd();

function piDailyValidateDate($date)
{
	$value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
	if (!$value || $value->format('Y-m-d') !== $date) {
		throw new DomainException('Tanggal Daily Report tidak valid.');
	}
}

function piDailySummaryTotals($rows)
{
	$totals = array_fill_keys(array('invoiced', 'paid', 'credit_note', 'outstanding', 'not_due', 'range_1', 'range_2', 'range_3', 'range_4'), 0.0);
	foreach ($rows as $row) {
		foreach ($totals as $key => $unused) {
			$totals[$key] += $row->{$key};
		}
	}
	return $totals;
}

function piDailyRegisterTotals($rows)
{
	$totals = array_fill_keys(array('net_total', 'tax_total', 'other_tax', 'grand_total', 'paid_amount', 'outstanding'), 0.0);
	foreach ($rows as $row) {
		foreach (array('net_total', 'tax_total', 'other_tax', 'grand_total', 'paid_amount') as $key) {
			$totals[$key] += $row->{$key};
		}
		$totals['outstanding'] += $row->outstanding_amount;
	}
	return $totals;
}

function piDailySummaryTable($report, $rows, $totals)
{
	$noteLabel = $report['kind'] === 'receivable' ? 'Credit Note' : 'Debit Note';
	print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-daily-summary"><thead><tr class="liste_titre"><th>Party</th><th>Nama</th><th class="right">Invoiced</th><th class="right">Paid</th><th class="right">'.piEscape($noteLabel).'</th><th class="right">Outstanding</th><th class="right">Not Due</th><th class="right">0–30</th><th class="right">31–60</th><th class="right">61–90</th><th class="right">&gt;90</th></tr></thead><tbody>';
	foreach ($rows as $row) {
		$link = DOL_URL_ROOT.'/societe/card.php?socid='.(int) $row->party_id;
		print '<tr class="oddeven"><td><a href="'.piEscape($link).'">'.piEscape($row->party_code).'</a></td><td>'.piEscape($row->party_name).'</td>';
		foreach (array('invoiced', 'paid', 'credit_note', 'outstanding', 'not_due', 'range_1', 'range_2', 'range_3', 'range_4') as $key) {
			print '<td class="right nowraponall">'.piReportMoney($row->{$key}).'</td>';
		}
		print '</tr>';
	}
	if (!$rows) {
		print '<tr><td colspan="11" class="center pi-muted">Tidak ada outstanding pada filter ini.</td></tr>';
	}
	print '<tr class="pi-report-grand"><td colspan="2">TOTAL</td>';
	foreach (array('invoiced', 'paid', 'credit_note', 'outstanding', 'not_due', 'range_1', 'range_2', 'range_3', 'range_4') as $key) {
		print '<td class="right nowraponall">'.piReportMoney($totals[$key]).'</td>';
	}
	print '</tr></tbody></table></div>';
}

function piDailyRegisterTable($report, $rows, $totals)
{
	$isSales = $report['kind'] === 'receivable';
	print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-daily-register"><thead><tr class="liste_titre"><th>Voucher</th><th>Posting Date</th><th>Due Date</th><th>'.($isSales ? 'Customer' : 'Supplier').'</th>'.($isSales ? '' : '<th>Supplier Invoice</th>').'<th>Type</th><th>Payment Mode</th><th>Project</th><th>Currency</th><th class="right">Net Total</th><th class="right">Tax</th><th class="right">Other Tax</th><th class="right">Grand Total</th><th class="right">Paid / Allocated</th><th class="right">Outstanding</th></tr></thead><tbody>';
	foreach ($rows as $row) {
		$link = $isSales ? DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $row->rowid : DOL_URL_ROOT.'/fourn/facture/card.php?facid='.(int) $row->rowid;
		print '<tr class="oddeven"><td><a href="'.piEscape($link).'">'.piEscape($row->ref).'</a></td><td>'.piEscape($row->posting_date).'</td><td>'.piEscape($row->due_date).'</td><td>'.piEscape($row->party_name).'</td>'.($isSales ? '' : '<td>'.piEscape($row->supplier_ref).'</td>').'<td>'.piEscape(piDailyInvoiceType($row->type)).'</td><td>'.piEscape($row->payment_mode).'</td><td>'.piEscape($row->project_ref).'</td><td>'.piEscape($row->currency).'</td>';
		foreach (array('net_total', 'tax_total', 'other_tax', 'grand_total', 'paid_amount', 'outstanding_amount') as $key) {
			print '<td class="right nowraponall">'.piReportMoney($row->{$key}).'</td>';
		}
		print '</tr>';
	}
	if (!$rows) {
		print '<tr><td colspan="'.($isSales ? 14 : 15).'" class="center pi-muted">Tidak ada invoice tervalidasi pada filter ini.</td></tr>';
	}
	print '<tr class="pi-report-grand"><td colspan="'.($isSales ? 8 : 9).'">TOTAL</td><td class="right">'.piReportMoney($totals['net_total']).'</td><td class="right">'.piReportMoney($totals['tax_total']).'</td><td class="right">'.piReportMoney($totals['other_tax']).'</td><td class="right">'.piReportMoney($totals['grand_total']).'</td><td class="right">'.piReportMoney($totals['paid_amount']).'</td><td class="right">'.piReportMoney($totals['outstanding']).'</td></tr></tbody></table></div>';
}

function piDailyInvoiceType($type)
{
	$labels = array(0 => 'Standard', 1 => 'Replacement', 2 => 'Credit Note', 3 => 'Deposit', 4 => 'Proforma', 5 => 'Situation');
	return $labels[(int) $type] ?? ('Type '.(int) $type);
}

function piDailySummaryExcel($report, $reportDate, $rows, $totals)
{
	$noteLabel = $report['kind'] === 'receivable' ? 'Credit Note' : 'Debit Note';
	$data = array();
	foreach ($rows as $row) {
		$data[] = array($row->party_code, $row->party_name, $row->invoiced, $row->paid, $row->credit_note, $row->outstanding, $row->not_due, $row->range_1, $row->range_2, $row->range_3, $row->range_4);
	}
	$data[] = array('', 'TOTAL', $totals['invoiced'], $totals['paid'], $totals['credit_note'], $totals['outstanding'], $totals['not_due'], $totals['range_1'], $totals['range_2'], $totals['range_3'], $totals['range_4']);
	$columns = array(1 => array('width' => 20), 2 => array('width' => 35));
	for ($i = 3; $i <= 11; $i++) {
		$columns[$i] = array('type' => 'number', 'format' => '#,##0.00', 'width' => 18);
	}
	piExcelOutput(strtolower(str_replace(' ', '-', $report['title'])).'-'.$reportDate.'.xlsx', $report['title'], array('Party', 'Nama', 'Invoiced', 'Paid', $noteLabel, 'Outstanding', 'Not Due', '0-30', '31-60', '61-90', '>90'), $data, $columns);
}

function piDailyRegisterExcel($report, $start, $end, $rows, $totals)
{
	$isSales = $report['kind'] === 'receivable';
	$headers = array('Voucher', 'Posting Date', 'Due Date', $isSales ? 'Customer' : 'Supplier');
	if (!$isSales) {
		$headers[] = 'Supplier Invoice';
	}
	$headers = array_merge($headers, array('Type', 'Payment Mode', 'Project', 'Currency', 'Net Total', 'Tax', 'Other Tax', 'Grand Total', 'Paid / Allocated', 'Outstanding'));
	$data = array();
	foreach ($rows as $row) {
		$line = array($row->ref, $row->posting_date, $row->due_date, $row->party_name);
		if (!$isSales) {
			$line[] = $row->supplier_ref;
		}
		$data[] = array_merge($line, array(piDailyInvoiceType($row->type), $row->payment_mode, $row->project_ref, $row->currency, $row->net_total, $row->tax_total, $row->other_tax, $row->grand_total, $row->paid_amount, $row->outstanding_amount));
	}
	$prefixColumns = $isSales ? 8 : 9;
	$totalLine = array_fill(0, $prefixColumns, '');
	$totalLine[0] = 'TOTAL';
	$data[] = array_merge($totalLine, array($totals['net_total'], $totals['tax_total'], $totals['other_tax'], $totals['grand_total'], $totals['paid_amount'], $totals['outstanding']));
	$columns = array(1 => array('width' => 22), 2 => array('type' => 'date', 'format' => 'yyyy-mm-dd', 'width' => 14), 3 => array('type' => 'date', 'format' => 'yyyy-mm-dd', 'width' => 14), 4 => array('width' => 35));
	for ($i = $prefixColumns + 1; $i <= $prefixColumns + 6; $i++) {
		$columns[$i] = array('type' => 'number', 'format' => '#,##0.00', 'width' => 18);
	}
	piExcelOutput(strtolower(str_replace(' ', '-', $report['title'])).'-'.$start.'-'.$end.'.xlsx', $report['title'], $headers, $data, $columns);
}
