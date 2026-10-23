<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pifinancialreport.class.php';
require_once __DIR__.'/lib/financial.php';

try {
	list($start, $end) = piReportDates();
	$financial = new PiFinancialReport($pi);
	$accounts = $financial->generalLedgerAccounts();
	$filterOptions = $financial->generalLedgerFilters();
	$account = trim(GETPOST('account', 'alphanohtml'));
	$journal = trim(GETPOST('journal', 'alphanohtml'));
	$voucherType = trim(GETPOST('voucher_type', 'alphanohtml'));
	$voucher = trim(GETPOST('voucher', 'alphanohtml'));
	$party = trim(GETPOST('party', 'alphanohtml'));
	$groupBy = GETPOST('group_by', 'alpha') ?: 'voucher';
	$filtered = GETPOST('filtered', 'int');
	$showOpening = !$filtered || (bool) GETPOST('show_opening', 'int');
	$showRemarks = (bool) GETPOST('show_remarks', 'int');
	if ($account !== '' && !isset($accounts[$account])) {
		throw new DomainException('Akun General Ledger tidak tersedia.');
	}
	if ($journal !== '' && !isset($filterOptions['journals'][$journal])) {
		throw new DomainException('Journal yang dipilih tidak tersedia.');
	}
	if ($voucherType !== '' && !isset($filterOptions['voucher_types'][$voucherType])) {
		throw new DomainException('Voucher Type yang dipilih tidak tersedia.');
	}
	if ($party !== '' && !isset($filterOptions['parties'][$party])) {
		throw new DomainException('Party yang dipilih tidak tersedia.');
	}
	$result = $financial->generalLedger($start, $end, array(
		'account' => $account,
		'journal' => $journal,
		'voucher_type' => $voucherType,
		'voucher' => $voucher,
		'party' => $party,
		'group_by' => $groupBy,
		'show_opening' => $showOpening,
	));
	if (GETPOST('format', 'alpha') === 'xlsx') {
		piGeneralLedgerExcel($financial, $result['rows'], $start, $end, $showRemarks);
	}
} catch (DomainException $e) {
	piHeader('General Ledger');
	print '<div class="pi-error">'.piEscape($e->getMessage()).'</div>';
	piEnd();
	exit;
}

piHeader('General Ledger');
print '<form method="get" class="pi-report-filter pi-gl-filter">';
print '<input type="hidden" name="filtered" value="1">';
print '<label for="start">From Date<input type="date" id="start" name="start" value="'.piEscape($start).'" required></label>';
print '<label for="end">To Date<input type="date" id="end" name="end" value="'.piEscape($end).'" required></label>';
print '<label for="account">Account<select id="account" name="account"><option value="">Semua Account</option>';
foreach ($accounts as $number => $label) {
	print '<option value="'.piEscape($number).'"'.($account === $number ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label>';
print '<label for="voucher">Voucher No<input type="text" id="voucher" name="voucher" value="'.piEscape($voucher).'" placeholder="Cari nomor voucher"></label>';
print '<label for="voucher_type">Voucher Type<select id="voucher_type" name="voucher_type"><option value="">Semua Type</option>';
foreach ($filterOptions['voucher_types'] as $key => $label) {
	print '<option value="'.piEscape($key).'"'.($voucherType === $key ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label>';
print '<label for="journal">Finance Book / Journal<select id="journal" name="journal"><option value="">Semua Journal</option>';
foreach ($filterOptions['journals'] as $key => $label) {
	print '<option value="'.piEscape($key).'"'.($journal === $key ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label>';
print '<label for="party">Party<select id="party" name="party"><option value="">Semua Party</option>';
foreach ($filterOptions['parties'] as $key => $label) {
	print '<option value="'.piEscape($key).'"'.($party === $key ? ' selected' : '').'>'.piEscape($label).'</option>';
}
print '</select></label>';
print '<label for="group_by">Group By<select id="group_by" name="group_by"><option value="voucher"'.($groupBy === 'voucher' ? ' selected' : '').'>Voucher (Consolidated)</option><option value="none"'.($groupBy === 'none' ? ' selected' : '').'>No Grouping</option><option value="account"'.($groupBy === 'account' ? ' selected' : '').'>Account</option></select></label>';
print '<label class="pi-check"><input type="checkbox" name="show_opening" value="1"'.($showOpening ? ' checked' : '').'> Show Opening Balance</label>';
print '<label class="pi-check"><input type="checkbox" name="show_remarks" value="1"'.($showRemarks ? ' checked' : '').'> Show Remarks</label>';
print '<button class="button" type="submit">Tampilkan</button>';
$exportQuery = http_build_query(array('filtered' => 1, 'start' => $start, 'end' => $end, 'account' => $account, 'voucher' => $voucher, 'voucher_type' => $voucherType, 'journal' => $journal, 'party' => $party, 'group_by' => $groupBy, 'show_opening' => $showOpening ? 1 : 0, 'show_remarks' => $showRemarks ? 1 : 0, 'format' => 'xlsx'));
print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.$exportQuery).'">Export Excel (.xlsx)</a></form>';

print '<p class="pi-report-intro">Jejak detail jurnal Dolibarr dari <b>'.piEscape($start).'</b> sampai <b>'.piEscape($end).'</b>. Balance dihitung sebagai debit dikurangi credit dan berjalan sesuai urutan tampilan.</p>';
piReportCards(array(
	'Opening Balance' => array($result['opening'], $result['opening'] >= 0 ? 'pi-positive' : 'pi-negative'),
	'Total Debit' => $result['debit'],
	'Total Credit' => $result['credit'],
	'Closing Balance' => array($result['closing'], $result['closing'] >= 0 ? 'pi-positive' : 'pi-negative'),
));

print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table pi-gl-table"><thead><tr class="liste_titre"><th>No.</th><th>Posting Date</th><th>Account</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th><th>Voucher Type</th><th>Voucher No</th><th>Against Account</th><th>Journal</th><th>Party</th>'.($showRemarks ? '<th>Remarks</th>' : '').'</tr></thead><tbody>';
$number = 0;
foreach ($result['rows'] as $row) {
	if ($row->is_opening) {
		print '<tr class="pi-gl-opening"><td></td><td></td><td>'.piEscape($row->numero_compte ? $row->numero_compte.' — '.$row->label_compte : 'Opening').'</td><td class="right">0.00</td><td class="right">0.00</td><td class="right nowraponall">'.piReportMoney($row->balance).'</td><td colspan="'.($showRemarks ? '6' : '5').'">Opening Balance</td></tr>';
		continue;
	}
	$number++;
	$partyText = trim($row->party_code.($row->party_name && $row->party_name !== $row->party_code ? ' — '.$row->party_name : ''));
	print '<tr class="oddeven"><td>'.(int) $number.'</td><td>'.piEscape($row->doc_date).'</td><td>'.piEscape($row->numero_compte.' — '.$row->label_compte).'</td><td class="right nowraponall">'.piReportMoney($row->debit).'</td><td class="right nowraponall">'.piReportMoney($row->credit).'</td><td class="right nowraponall">'.piReportMoney($row->balance).'</td><td>'.piEscape($financial->voucherTypeLabel($row->doc_type ?: 'Manual Journal')).'</td><td>'.piGeneralLedgerVoucherLink($row).'</td><td>'.piEscape($row->against_account).'</td><td>'.piEscape($row->code_journal).'</td><td>'.piEscape($partyText).'</td>'.($showRemarks ? '<td>'.piEscape($row->remarks).'</td>' : '').'</tr>';
}
if (!$result['rows']) {
	print '<tr><td colspan="'.($showRemarks ? '12' : '11').'" class="center pi-muted">Tidak ada jurnal pada filter ini.</td></tr>';
}
print '<tr class="pi-report-grand"><td colspan="3">TOTAL MOVEMENT</td><td class="right">'.piReportMoney($result['debit']).'</td><td class="right">'.piReportMoney($result['credit']).'</td><td class="right">'.piReportMoney($result['closing']).'</td><td colspan="'.($showRemarks ? '6' : '5').'"></td></tr></tbody></table></div>';
print '<p class="pi-muted">Against Account berasal dari akun pasangan pada accounting piece yang sama. Gunakan filter Account atau Party saat menelusuri saldo tertentu; total debit dan credit dapat berbeda ketika hanya satu sisi voucher yang difilter.</p>';
piEnd();

function piGeneralLedgerVoucherLink($row)
{
	$paths = array(
		'customer_invoice' => '/compta/facture/card.php?facid=',
		'supplier_invoice' => '/fourn/facture/card.php?facid=',
		'reception' => '/reception/card.php?id=',
		'shipment' => '/expedition/card.php?id=',
		'stock_entry' => '/custom/perpetualinventory/stockentry_card.php?id=',
		'bank' => '/compta/bank/line.php?rowid=',
		'expense_report' => '/expensereport/card.php?id=',
		'loan' => '/loan/card.php?id=',
	);
	$label = piEscape($row->doc_ref);
	return !empty($paths[$row->doc_type]) && $row->fk_doc ? '<a href="'.piEscape(DOL_URL_ROOT.$paths[$row->doc_type].(int) $row->fk_doc).'">'.$label.'</a>' : $label;
}

function piGeneralLedgerExcel($financial, $rows, $start, $end, $showRemarks)
{
	$headers = array('Posting Date', 'Account', 'Account Label', 'Debit', 'Credit', 'Balance', 'Voucher Type', 'Voucher No', 'Against Account', 'Journal', 'Party');
	if ($showRemarks) {
		$headers[] = 'Remarks';
	}
	$data = array();
	foreach ($rows as $row) {
		$line = array($row->doc_date, $row->numero_compte, $row->label_compte, $row->debit, $row->credit, $row->balance, $row->is_opening ? 'Opening' : $financial->voucherTypeLabel($row->doc_type ?: 'Manual Journal'), $row->doc_ref, $row->against_account, $row->code_journal, trim($row->party_code.($row->party_name && $row->party_name !== $row->party_code ? ' — '.$row->party_name : '')));
		if ($showRemarks) {
			$line[] = $row->remarks;
		}
		$data[] = $line;
	}
	$columns = array(1 => array('type' => 'date', 'format' => 'yyyy-mm-dd', 'width' => 14), 2 => array('width' => 18), 3 => array('width' => 35), 4 => array('type' => 'number', 'format' => '#,##0.00', 'width' => 18), 5 => array('type' => 'number', 'format' => '#,##0.00', 'width' => 18), 6 => array('type' => 'number', 'format' => '#,##0.00', 'width' => 18), 7 => array('width' => 20), 8 => array('width' => 24), 9 => array('width' => 40), 10 => array('width' => 14), 11 => array('width' => 30));
	if ($showRemarks) {
		$columns[12] = array('width' => 55);
	}
	piExcelOutput('general-ledger-'.$start.'-'.$end.'.xlsx', 'General Ledger', $headers, $data, $columns);
}
