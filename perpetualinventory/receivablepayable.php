<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pifinancialreport.class.php';
require_once __DIR__.'/lib/financial.php';

try {
	list($start, $end) = piReportDates();
	$kind = GETPOST('kind', 'alpha') ?: 'receivable';
	if (!in_array($kind, array('receivable', 'payable'), true)) {
		throw new DomainException('Jenis laporan hutang/piutang tidak valid.');
	}
	$financial = new PiFinancialReport($pi);
	$accounts = $financial->thirdPartyAccounts($kind);
	$account = GETPOST('account', 'alphanohtml');
	if ($account === '') {
		$configured = getDolGlobalString($kind === 'receivable' ? 'ACCOUNTING_ACCOUNT_CUSTOMER' : 'ACCOUNTING_ACCOUNT_SUPPLIER');
		$account = isset($accounts[$configured]) ? $configured : (string) array_key_first($accounts);
	}
	if (!$accounts) {
		throw new DomainException('Belum ada akun '.($kind === 'receivable' ? 'piutang pelanggan' : 'hutang pemasok').' yang terkonfigurasi atau memiliki jurnal buku pembantu.');
	}
	if (!isset($accounts[$account])) {
		throw new DomainException('Akun yang dipilih tidak tersedia untuk jenis laporan ini.');
	}
	$rows = $financial->receivablePayable($kind, $account, $start, $end);
	$totals = array('opening' => 0.0, 'increase' => 0.0, 'reduction' => 0.0, 'closing' => 0.0);
	foreach ($rows as $row) {
		$totals['opening'] += $row->opening_balance;
		$totals['increase'] += $row->increases;
		$totals['reduction'] += $row->reductions;
		$totals['closing'] += $row->closing_balance;
	}
	if (GETPOST('format', 'alpha') === 'xlsx') {
		$excelRows = array();
		foreach ($rows as $row) {
			$excelRows[] = array($row->party_code, $row->party_name, $row->opening_balance, $row->increases, $row->reductions, $row->closing_balance);
		}
		$excelRows[] = array('', 'TOTAL', $totals['opening'], $totals['increase'], $totals['reduction'], $totals['closing']);
		$money = array('type' => 'number', 'format' => '#,##0.00', 'width' => 20);
		piExcelOutput(($kind === 'receivable' ? 'piutang' : 'hutang').'-'.$start.'-'.$end.'.xlsx', $kind === 'receivable' ? 'Laporan Piutang' : 'Laporan Hutang', array('Kode Buku Pembantu', $kind === 'receivable' ? 'Nama Customer' : 'Nama Supplier', 'Saldo Awal', 'Penambahan', 'Pengurangan', 'Saldo Akhir'), $excelRows, array(1 => array('width' => 24), 2 => array('width' => 42), 3 => $money, 4 => $money, 5 => $money, 6 => $money));
	}
} catch (DomainException $e) {
	piHeader('Laporan Hutang Piutang');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

$partyLabel = $kind === 'receivable' ? 'Nama Customer' : 'Nama Supplier';
piHeader('Laporan Hutang Piutang');
print '<form method="get" class="pi-report-filter">';
print '<label for="kind">Jenis laporan<select id="kind" name="kind" onchange="this.form.account.value=\'\';this.form.submit()"><option value="receivable"'.($kind === 'receivable' ? ' selected' : '').'>Piutang Pelanggan</option><option value="payable"'.($kind === 'payable' ? ' selected' : '').'>Hutang Pemasok</option></select></label>';
print '<label for="account">Akun buku besar<select id="account" name="account">';
foreach ($accounts as $number => $label) {
	print '<option value="'.piEscape($number).'"'.($number === $account ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label>';
print '<label for="start">Dari tanggal<input type="date" id="start" name="start" value="'.piEscape($start).'" required></label>';
print '<label for="end">Sampai tanggal<input type="date" id="end" name="end" value="'.piEscape($end).'" required></label>';
print '<button class="button" type="submit">Tampilkan</button>';
$exportQuery = http_build_query(array('kind' => $kind, 'account' => $account, 'start' => $start, 'end' => $end, 'format' => 'xlsx'));
print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.$exportQuery).'">Export Excel (.xlsx)</a>';
print '</form>';
print '<p class="pi-report-intro">Akun: <b>'.piEscape($accounts[$account]).'</b>. Saldo awal mencakup seluruh jurnal sebelum tanggal awal; penambahan dan pengurangan berasal dari mutasi buku besar dalam periode terpilih.</p>';
piReportCards(array('Saldo Awal' => $totals['opening'], 'Penambahan' => $totals['increase'], 'Pengurangan' => $totals['reduction'], 'Saldo Akhir' => array($totals['closing'], $totals['closing'] >= 0 ? 'pi-positive' : 'pi-negative')));
print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-rp-table"><thead><tr class="liste_titre"><th>No.</th><th>Kode Buku Pembantu</th><th>'.piEscape($partyLabel).'</th><th class="right">Saldo Awal</th><th class="right">Penambahan</th><th class="right">Pengurangan</th><th class="right">Saldo Akhir</th></tr></thead><tbody>';
foreach ($rows as $index => $row) {
	print '<tr class="oddeven"><td>'.($index + 1).'</td><td>'.piEscape($row->party_code).'</td><td>'.piEscape($row->party_name).'</td><td class="right nowraponall">'.piReportMoney($row->opening_balance).'</td><td class="right nowraponall">'.piReportMoney($row->increases).'</td><td class="right nowraponall">'.piReportMoney($row->reductions).'</td><td class="right nowraponall">'.piReportMoney($row->closing_balance).'</td></tr>';
}
if (!$rows) {
	print '<tr><td colspan="7" class="center pi-muted">Tidak ada saldo atau mutasi pada akun dan periode ini.</td></tr>';
}
print '<tr class="pi-report-grand"><td colspan="3">TOTAL</td><td class="right">'.piReportMoney($totals['opening']).'</td><td class="right">'.piReportMoney($totals['increase']).'</td><td class="right">'.piReportMoney($totals['reduction']).'</td><td class="right">'.piReportMoney($totals['closing']).'</td></tr>';
print '</tbody></table></div>';
print '<p class="pi-muted">Piutang dihitung sebagai debit dikurangi kredit. Hutang dihitung sebagai kredit dikurangi debit. Data dikelompokkan berdasarkan akun pembantu pada jurnal akuntansi Dolibarr.</p>';
piEnd();
