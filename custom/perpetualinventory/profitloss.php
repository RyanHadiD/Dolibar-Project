<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pifinancialreport.class.php';
require_once __DIR__.'/lib/financial.php';

try {
	list($start, $end) = piReportDates();
	$view = piReportView();
	$report = (new PiFinancialReport($pi))->profitLoss($start, $end);
	$income = array_sum($report['totals']['income']);
	$expense = array_sum($report['totals']['expense']);
	$profit = $income - $expense;
	list($display, $showTotal) = piReportPresentation($report, array('income', 'expense'), $view, 'sum');
	if (GETPOST('format', 'alpha') === 'xlsx') {
		piReportExcelStatement('profit-loss-'.$start.'-'.$end.'.xlsx', 'Profit and Loss', $display['periods'], array(
			array('label' => 'Pendapatan', 'nodes' => $display['sections']['income'], 'totals' => $display['totals']['income']),
			array('label' => 'Beban', 'nodes' => $display['sections']['expense'], 'totals' => $display['totals']['expense']),
		), array('Laba / Rugi Bersih' => $display['net']), 'sum', $showTotal);
	}
} catch (DomainException $e) {
	piHeader('Profit and Loss Statement');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

piHeader('Profit and Loss Statement');
piReportFilter($start, $end, $view);
piReportCards(array(
	'Total Pendapatan' => $income,
	'Total Beban' => $expense,
	'Laba / Rugi Bersih' => array($profit, $profit >= 0 ? 'pi-positive' : 'pi-negative'),
));
piReportTableStart($display['periods'], $showTotal);
piReportSection('Pendapatan (Kredit)', $display['sections']['income'], $display['periods'], $display['totals']['income'], 'sum', $showTotal);
piReportSection('Beban (Debit)', $display['sections']['expense'], $display['periods'], $display['totals']['expense'], 'sum', $showTotal);
print '<tr class="pi-report-grand"><td>Laba / Rugi Periode Berjalan</td>';
foreach ($display['periods'] as $period => $unused) {
	print '<td class="right nowraponall">'.piReportMoney($display['net'][$period]).'</td>';
}
if ($showTotal) {
	print '<td class="right nowraponall">'.piReportMoney($profit).'</td>';
}
print '</tr>';
piReportTableEnd();
print '<p class="pi-muted">Pendapatan ditampilkan sebagai kredit dikurangi debit; beban sebagai debit dikurangi kredit. Angka bersumber langsung dari buku besar Dolibarr pada periode terpilih.</p>';
piEnd();
