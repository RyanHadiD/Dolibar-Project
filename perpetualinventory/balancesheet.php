<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pifinancialreport.class.php';
require_once __DIR__.'/lib/financial.php';

try {
	list($start, $end) = piReportDates();
	$view = piReportView();
	$report = (new PiFinancialReport($pi))->balanceSheet($start, $end);
	$last = array_key_last($report['periods']);
	$asset = $report['totals']['asset'][$last] ?? 0;
	$liability = $report['totals']['liability'][$last] ?? 0;
	$equity = $report['totals']['equity'][$last] ?? 0;
	$profit = $report['profit_loss'][$last] ?? 0;
	$difference = $report['difference'][$last] ?? 0;
	list($display, $showTotal) = piReportPresentation($report, array('asset', 'liability', 'equity'), $view, 'last');
	if (GETPOST('format', 'alpha') === 'xlsx') {
		$displayCombined = array();
		foreach ($display['periods'] as $period => $unused) {
			$displayCombined[$period] = $display['totals']['liability'][$period] + $display['totals']['equity'][$period] + $display['profit_loss'][$period];
		}
		piReportExcelStatement('balance-sheet-'.$start.'-'.$end.'.xlsx', 'Balance Sheet', $display['periods'], array(
			array('label' => 'Aset', 'nodes' => $display['sections']['asset'], 'totals' => $display['totals']['asset']),
			array('label' => 'Liabilitas', 'nodes' => $display['sections']['liability'], 'totals' => $display['totals']['liability']),
			array('label' => 'Ekuitas', 'nodes' => $display['sections']['equity'], 'totals' => $display['totals']['equity']),
		), array('Laba / Rugi Berjalan' => $display['profit_loss'], 'Total Liabilitas + Ekuitas + Laba/Rugi' => $displayCombined, 'Selisih Neraca' => $display['difference']), 'last', $showTotal);
	}
} catch (DomainException $e) {
	piHeader('Balance Sheet');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

piHeader('Balance Sheet');
piReportFilter($start, $end, $view);
piReportCards(array(
	'Total Aset' => $asset,
	'Total Liabilitas' => $liability,
	'Total Ekuitas' => $equity,
	'Laba / Rugi Berjalan' => array($profit, $profit >= 0 ? 'pi-positive' : 'pi-negative'),
	'Selisih Neraca' => array($difference, abs($difference) < 0.005 ? 'pi-positive' : 'pi-negative'),
));
piReportTableStart($display['periods'], $showTotal);
piReportSection('Aset', $display['sections']['asset'], $display['periods'], $display['totals']['asset'], 'last', $showTotal);
piReportSection('Liabilitas', $display['sections']['liability'], $display['periods'], $display['totals']['liability'], 'last', $showTotal);
piReportSection('Ekuitas', $display['sections']['equity'], $display['periods'], $display['totals']['equity'], 'last', $showTotal);
print '<tr class="pi-report-total"><td>Laba / Rugi Berjalan</td>';
foreach ($display['periods'] as $period => $unused) {
	print '<td class="right nowraponall">'.piReportMoney($display['profit_loss'][$period]).'</td>';
}
if ($showTotal) {
	print '<td class="right nowraponall">'.piReportMoney($profit).'</td>';
}
print '</tr>';
print '<tr class="pi-report-grand"><td>Total Liabilitas + Ekuitas + Laba/Rugi</td>';
foreach ($display['periods'] as $period => $unused) {
	$total = $display['totals']['liability'][$period] + $display['totals']['equity'][$period] + $display['profit_loss'][$period];
	print '<td class="right nowraponall">'.piReportMoney($total).'</td>';
}
if ($showTotal) {
	print '<td class="right nowraponall">'.piReportMoney($liability + $equity + $profit).'</td>';
}
print '</tr>';
piReportTableEnd();
print '<p class="'.(abs($difference) < 0.005 ? 'pi-ok' : 'pi-error').'"><b>'.(abs($difference) < 0.005 ? 'Neraca seimbang.' : 'Neraca belum seimbang sebesar '.piReportMoney($difference).'. Periksa klasifikasi/hierarki akun dan jurnal periode tersebut.').'</b></p>';
print '<p class="pi-muted">Saldo aset, liabilitas, dan ekuitas bersifat akumulatif sampai akhir setiap bulan. Laba/rugi berjalan dihitung sejak awal tahun buku sampai akhir setiap bulan.</p>';
piEnd();
