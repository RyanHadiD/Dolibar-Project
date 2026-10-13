<?php

define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require dirname(__DIR__, 2).'/main.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($user->id) || !isModEnabled('printformat')) {
	http_response_code(403);
	exit('Akses export ditolak.');
}
$token = GETPOST('token', 'alpha');
if ($token === '' || !hash_equals((string) ($_SESSION['token'] ?? ''), $token)) {
	http_response_code(403);
	exit('Token keamanan export tidak valid. Muat ulang halaman dan coba lagi.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8 * 1024 * 1024) {
	http_response_code(413);
	exit('Data terlalu besar untuk diexport langsung.');
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['rows']) || !is_array($payload['rows'])) {
	http_response_code(400);
	exit('Data tabel export tidak valid.');
}
$rows = array_slice($payload['rows'], 0, 10000);
$columnCount = 0;
foreach ($rows as $index => $row) {
	if (!is_array($row)) {
		http_response_code(400);
		exit('Format baris export tidak valid.');
	}
	$rows[$index] = array_slice(array_map(function ($value) {
		if (!is_scalar($value) && $value !== null) return '';
		return mb_substr((string) $value, 0, 32767);
	}, array_values($row)), 0, 100);
	$columnCount = max($columnCount, count($rows[$index]));
}
if (!$columnCount) {
	http_response_code(400);
	exit('Tabel export kosong.');
}

require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
require_once PHPEXCELNEW_PATH.'Spreadsheet.php';
if (!class_exists('ZipArchive')) {
	http_response_code(500);
	exit('Ekstensi PHP zip diperlukan untuk export Excel.');
}

$workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $workbook->getActiveSheet();
$title = trim(strip_tags((string) ($payload['title'] ?? 'Export')));
$title = preg_replace('~[\\\\/:*?\[\]]~', '-', $title);
$title = trim((string) $title, " \t\n\r\0\x0B'");
$sheet->setTitle(mb_substr($title !== '' ? $title : 'Export', 0, 31));

foreach ($rows as $rowIndex => $row) {
	foreach ($row as $columnIndex => $value) {
		$sheet->setCellValueExplicitByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
	}
}
$lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
$lastRow = count($rows);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
$sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray(array(
	'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
	'fill' => array('fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => array('rgb' => '4472C4')),
));
$sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
for ($column = 1; $column <= $columnCount; $column++) {
	$sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
}

$filename = dol_sanitizeFileName((string) ($payload['filename'] ?? 'export.xlsx'));
if (!preg_match('/\.xlsx$/i', $filename)) $filename .= '.xlsx';
$temporaryFile = tempnam(sys_get_temp_dir(), 'printformat-xlsx-');
if ($temporaryFile === false) {
	http_response_code(500);
	exit('File sementara untuk export tidak dapat dibuat.');
}
try {
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook);
	$writer->save($temporaryFile);
} catch (Throwable $exception) {
	@unlink($temporaryFile);
	$workbook->disconnectWorksheets();
	http_response_code(500);
	exit('Workbook Excel gagal dibuat: '.$exception->getMessage());
}
$workbook->disconnectWorksheets();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($temporaryFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($temporaryFile);
@unlink($temporaryFile);
exit;
