<?php

function piReportDates()
{
	$year = (int) date('Y');
	$start = GETPOST('start', 'alphanohtml') ?: $year.'-01-01';
	$end = GETPOST('end', 'alphanohtml') ?: date('Y-m-d');
	foreach (array($start, $end) as $date) {
		$d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		if (!$d || $d->format('Y-m-d') !== $date) {
			throw new DomainException('Tanggal laporan tidak valid.');
		}
	}
	if ($start > $end) {
		throw new DomainException('Tanggal awal tidak boleh setelah tanggal akhir.');
	}
	return array($start, $end);
}

function piReportView()
{
	$view = GETPOST('view', 'alpha') ?: 'summary';
	return in_array($view, array('summary', 'monthly'), true) ? $view : 'summary';
}

function piReportFilter($start, $end, $view = null)
{
	print '<form method="get" class="pi-report-filter">';
	print '<label for="start">Dari tanggal</label><input type="date" id="start" name="start" value="'.piEscape($start).'" required>';
	print '<label for="end">Sampai tanggal</label><input type="date" id="end" name="end" value="'.piEscape($end).'" required>';
	if ($view !== null) {
		print '<label for="view">Tampilan</label><select id="view" name="view"><option value="summary"'.($view === 'summary' ? ' selected' : '').'>Ringkas</option><option value="monthly"'.($view === 'monthly' ? ' selected' : '').'>Bulanan</option></select>';
	}
	print '<button class="button" type="submit">Tampilkan</button>';
	$query = http_build_query(array('start' => $start, 'end' => $end, 'view' => $view, 'format' => 'xlsx'));
	print '<a class="button button-save" href="'.piEscape($_SERVER['PHP_SELF'].'?'.$query).'">Export Excel (.xlsx)</a>';
	print '</form>';
}

function piReportMoney($amount)
{
	return price((float) $amount);
}

function piReportCards($cards)
{
	print '<div class="pi-report-cards">';
	foreach ($cards as $label => $data) {
		$amount = is_array($data) ? $data[0] : $data;
		$class = is_array($data) && !empty($data[1]) ? ' '.$data[1] : '';
		$suffix = is_array($data) && !empty($data[2]) ? $data[2] : '';
		print '<div class="pi-report-card"><span>'.piEscape($label).'</span><strong class="'.$class.'">'.piReportMoney($amount).piEscape($suffix).'</strong></div>';
	}
	print '</div>';
}

function piReportCollapseNodes($nodes, $mode)
{
	$result = array();
	foreach ($nodes as $node) {
		$node['values'] = array('summary' => $mode === 'last' ? end($node['values']) : array_sum($node['values']));
		$node['children'] = piReportCollapseNodes($node['children'], $mode);
		$result[] = $node;
	}
	return $result;
}

function piReportPresentation($report, $sections, $view, $totalMode)
{
	if ($view === 'monthly') {
		return array($report, true);
	}
	$report['periods'] = array('summary' => array('label' => $totalMode === 'last' ? 'Saldo Akhir' : 'Total Periode'));
	foreach ($sections as $section) {
		$report['sections'][$section] = piReportCollapseNodes($report['sections'][$section], $totalMode);
		$report['totals'][$section] = array('summary' => $totalMode === 'last' ? end($report['totals'][$section]) : array_sum($report['totals'][$section]));
	}
	foreach (array('net', 'profit_loss', 'difference') as $key) {
		if (isset($report[$key])) {
			$report[$key] = array('summary' => $totalMode === 'last' ? end($report[$key]) : array_sum($report[$key]));
		}
	}
	return array($report, false);
}

function piReportNodeRows($node, $periods, $depth = 0, $totalMode = 'sum', $showTotal = true)
{
	$hasChildren = !empty($node['children']);
	$total = $totalMode === 'last' ? end($node['values']) : array_sum($node['values']);
	print '<tr class="'.($hasChildren ? 'pi-report-group' : 'oddeven').'"><td style="--pi-depth:'.(int) $depth.'">'.($hasChildren ? '<span class="pi-caret">&#9662;</span>' : '').piEscape($node['account']->account_number.' — '.$node['account']->label).'</td>';
	foreach ($periods as $period => $unused) {
		print '<td class="right nowraponall">'.piReportMoney($node['values'][$period]).'</td>';
	}
	if ($showTotal) {
		print '<td class="right nowraponall">'.piReportMoney($total).'</td>';
	}
	print '</tr>';
	foreach ($node['children'] as $child) {
		piReportNodeRows($child, $periods, $depth + 1, $totalMode, $showTotal);
	}
}

function piReportSection($label, $nodes, $periods, $totals, $totalMode = 'sum', $showTotal = true)
{
	print '<tr class="pi-report-section"><th>'.piEscape($label).'</th>';
	foreach ($periods as $unused) {
		print '<th></th>';
	}
	if ($showTotal) {
		print '<th></th>';
	}
	print '</tr>';
	foreach ($nodes as $node) {
		piReportNodeRows($node, $periods, 0, $totalMode, $showTotal);
	}
	print '<tr class="pi-report-total"><td>Total '.piEscape($label).'</td>';
	foreach ($periods as $period => $unused) {
		print '<td class="right nowraponall">'.piReportMoney($totals[$period] ?? 0).'</td>';
	}
	if ($showTotal) {
		print '<td class="right nowraponall">'.piReportMoney($totalMode === 'last' ? end($totals) : array_sum($totals)).'</td>';
	}
	print '</tr>';
}

function piReportTableStart($periods, $showTotal = true)
{
	print '<div class="pi-scroll"><table class="noborder centpercent pi-report-table"><thead><tr class="liste_titre"><th>Akun</th>';
	foreach ($periods as $meta) {
		print '<th class="right nowraponall">'.piEscape($meta['label']).'</th>';
	}
	if ($showTotal) {
		print '<th class="right">Total</th>';
	}
	print '</tr></thead><tbody>';
}

function piReportTableEnd()
{
	print '</tbody></table></div>';
}

function piCsvOpen($filename, $headers)
{
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, $headers, ';');
	return $out;
}

function piCsvNumber($value, $decimals = 2)
{
	$parts = explode('.', number_format((float) $value, $decimals, '.', ''), 2);
	$fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';
	$number = $parts[0].($fraction === '' ? '' : '.'.$fraction);
	if ($number === '-0') {
		$number = '0';
	}
	// CSV memakai titik koma, sehingga koma aman dan dikenali Excel locale Indonesia sebagai desimal.
	return str_replace('.', ',', $number);
}

function piCsvText($value)
{
	$value = (string) $value;
	return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
}

/** Keep identifiers exact when Excel would otherwise remove leading zeroes or use scientific notation. */
function piCsvCode($value)
{
	$value = str_replace('"', '""', (string) $value);
	return '="'.$value.'"';
}

/** Stream a native Excel workbook. Column definitions are 1-based. */
function piExcelOutput($filename, $sheetTitle, $headers, $rows, $columns = array())
{
	require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
	require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
	require_once PHPEXCELNEW_PATH.'Spreadsheet.php';

	if (!class_exists('ZipArchive')) {
		throw new DomainException('Ekstensi PHP zip diperlukan untuk membuat file Excel.');
	}

	$book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$book->getProperties()->setCreator(DOL_APPLICATION_TITLE)->setTitle($sheetTitle);
	$sheet = $book->getActiveSheet();
	$sheet->setTitle(substr($sheetTitle, 0, 31));
	foreach ($headers as $index => $label) {
		$sheet->setCellValueExplicitByColumnAndRow($index + 1, 1, (string) $label, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
	}

	$rowNumber = 2;
	foreach ($rows as $row) {
		foreach (array_values($row) as $index => $value) {
			$columnNumber = $index + 1;
			$definition = $columns[$columnNumber] ?? array();
			$type = $definition['type'] ?? 'text';
			if ($value === null || $value === '') {
				$sheet->setCellValueExplicitByColumnAndRow($columnNumber, $rowNumber, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			} elseif ($type === 'number') {
				$sheet->setCellValueByColumnAndRow($columnNumber, $rowNumber, (float) $value);
			} elseif (in_array($type, array('date', 'datetime', 'time'), true)) {
				$date = new DateTimeImmutable((string) $value);
				$sheet->setCellValueByColumnAndRow($columnNumber, $rowNumber, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date));
			} else {
				$sheet->setCellValueExplicitByColumnAndRow($columnNumber, $rowNumber, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			}
		}
		$rowNumber++;
	}

	$lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
	$lastRow = max(1, $rowNumber - 1);
	$sheet->freezePane('A2');
	$sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
	$sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray(array(
		'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
		'fill' => array('fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => array('rgb' => '4472C4')),
	));
	foreach ($headers as $index => $unused) {
		$columnNumber = $index + 1;
		$letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber);
		$definition = $columns[$columnNumber] ?? array();
		if (!empty($definition['format']) && $lastRow >= 2) {
			$sheet->getStyle($letter.'2:'.$letter.$lastRow)->getNumberFormat()->setFormatCode($definition['format']);
		}
		if (!empty($definition['width'])) {
			$sheet->getColumnDimension($letter)->setWidth((float) $definition['width']);
		} else {
			$sheet->getColumnDimension($letter)->setAutoSize(true);
		}
	}
	$sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
	$writer->save('php://output');
	$book->disconnectWorksheets();
	exit;
}

function piReportCsvNode($out, $node, $periods, $depth, $totalMode, $showTotal)
{
	$row = array('', piCsvText(str_repeat('  ', $depth).$node['account']->account_number.' — '.$node['account']->label));
	foreach ($periods as $period => $unused) {
		$row[] = piCsvNumber($node['values'][$period]);
	}
	if ($showTotal) {
		$row[] = piCsvNumber($totalMode === 'last' ? end($node['values']) : array_sum($node['values']));
	}
	fputcsv($out, $row, ';');
	foreach ($node['children'] as $child) {
		piReportCsvNode($out, $child, $periods, $depth + 1, $totalMode, $showTotal);
	}
}

function piReportCsvStatement($filename, $periods, $sections, $extraRows, $totalMode, $showTotal)
{
	$headers = array('Bagian', 'Akun');
	foreach ($periods as $meta) {
		$headers[] = $meta['label'];
	}
	if ($showTotal) {
		$headers[] = 'Total';
	}
	$out = piCsvOpen($filename, $headers);
	foreach ($sections as $section) {
		foreach ($section['nodes'] as $node) {
			piReportCsvNode($out, $node, $periods, 0, $totalMode, $showTotal);
		}
		$row = array($section['label'], 'Total '.$section['label']);
		foreach ($periods as $period => $unused) {
			$row[] = piCsvNumber($section['totals'][$period] ?? 0);
		}
		if ($showTotal) {
			$row[] = piCsvNumber($totalMode === 'last' ? end($section['totals']) : array_sum($section['totals']));
		}
		fputcsv($out, $row, ';');
	}
	foreach ($extraRows as $label => $values) {
		$row = array('Ringkasan', $label);
		foreach ($periods as $period => $unused) {
			$row[] = piCsvNumber($values[$period] ?? 0);
		}
		if ($showTotal) {
			$row[] = piCsvNumber($totalMode === 'last' ? end($values) : array_sum($values));
		}
		fputcsv($out, $row, ';');
	}
	fclose($out);
	exit;
}

function piReportExcelNodeRows(&$rows, $node, $periods, $depth, $totalMode, $showTotal)
{
	$row = array('', str_repeat('  ', $depth).$node['account']->account_number.' — '.$node['account']->label);
	foreach ($periods as $period => $unused) {
		$row[] = (float) $node['values'][$period];
	}
	if ($showTotal) {
		$row[] = (float) ($totalMode === 'last' ? end($node['values']) : array_sum($node['values']));
	}
	$rows[] = $row;
	foreach ($node['children'] as $child) {
		piReportExcelNodeRows($rows, $child, $periods, $depth + 1, $totalMode, $showTotal);
	}
}

function piReportExcelStatement($filename, $sheetTitle, $periods, $sections, $extraRows, $totalMode, $showTotal)
{
	$headers = array('Bagian', 'Akun');
	foreach ($periods as $meta) {
		$headers[] = $meta['label'];
	}
	if ($showTotal) {
		$headers[] = 'Total';
	}
	$rows = array();
	foreach ($sections as $section) {
		foreach ($section['nodes'] as $node) {
			piReportExcelNodeRows($rows, $node, $periods, 0, $totalMode, $showTotal);
		}
		$row = array($section['label'], 'Total '.$section['label']);
		foreach ($periods as $period => $unused) {
			$row[] = (float) ($section['totals'][$period] ?? 0);
		}
		if ($showTotal) {
			$row[] = (float) ($totalMode === 'last' ? end($section['totals']) : array_sum($section['totals']));
		}
		$rows[] = $row;
	}
	foreach ($extraRows as $label => $values) {
		$row = array('Ringkasan', $label);
		foreach ($periods as $period => $unused) {
			$row[] = (float) ($values[$period] ?? 0);
		}
		if ($showTotal) {
			$row[] = (float) ($totalMode === 'last' ? end($values) : array_sum($values));
		}
		$rows[] = $row;
	}
	$columns = array(1 => array('width' => 18), 2 => array('width' => 48));
	for ($column = 3; $column <= count($headers); $column++) {
		$columns[$column] = array('type' => 'number', 'format' => '#,##0.00', 'width' => 18);
	}
	piExcelOutput($filename, $sheetTitle, $headers, $rows, $columns);
}
