<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}

define('NOSESSION', 1);
require dirname(__DIR__, 3).'/master.inc.php';
require_once __DIR__.'/../class/printformat.class.php';
require_once __DIR__.'/../class/printformatgenerator.class.php';

$checks = 0;
function pfCheck($condition, $message)
{
	global $checks;
	if (!$condition) {
		throw new RuntimeException('FAIL: '.$message);
	}
	$checks++;
}

$renderer = new PrintFormatRenderer();
$customDimensions = PrintFormatDocumentGenerator::pageDimensions((object) array('page_format' => 'CUSTOM', 'custom_width' => 100.5, 'custom_height' => 200.25));
pfCheck($customDimensions === array(100.5, 200.25), 'custom PDF page dimensions are preserved');
foreach (PrintFormatDoctypeRegistry::all() as $doctype => $definition) {
	$default = PrintFormat::defaultData($doctype);
	PrintFormatRenderer::validateSyntax($default['template_html']);
}
pfCheck(true, 'all native default templates have valid syntax');

$context = array(
	'doc' => array('ref' => 'INV<001>', 'currency' => $conf->currency, 'note_public' => ''),
	'lines' => array(
		array('description' => 'A&B', 'qty' => 2),
		array('description' => 'Second', 'qty' => 1),
	),
);
$output = $renderer->render('{% if doc.ref %}{{ doc.ref }}{% endif %}:{% for line in lines %}{{ loop.index }}={{ line.description }};{% endfor %}', $context, $langs);
pfCheck(strpos($output, 'INV&lt;001&gt;') !== false, 'values are HTML escaped');
pfCheck(strpos($output, '1=A&amp;B;2=Second;') !== false, 'loops and indexes render');

$pdfHtml = PrintFormatPdfStyler::prepare(
	'<table class="sheet"><thead><tr><th>Header</th></tr></thead><tbody><tr><td class="center" style="color:#123456">Cell</td></tr></tbody></table>',
	'.sheet tbody tr td { border: solid 1px black; padding: 3px; } .sheet th { font-weight: bold; } .center { text-align: center !important; color: red; }'
);
pfCheck(strpos($pdfHtml, 'border:solid 1px black') !== false && strpos($pdfHtml, 'padding:3px') !== false && strpos($pdfHtml, 'text-align:center') !== false && strrpos($pdfHtml, 'color:#123456') > strrpos($pdfHtml, 'color:red'), 'PDF styler inlines descendant CSS and preserves inline precedence');
pfCheck((bool) preg_match('/<th[^>]*style="[^"]*font-weight:\s*bold;/i', $pdfHtml), 'PDF styler inlines table header CSS');

$fixedTable = PrintFormatPdfStyler::prepare(
	'<table><thead><tr><th width="20%">A</th><th width="30%">B</th><th width="50%">C</th></tr></thead><tbody><tr><td>Short</td><td>Very long value that must not resize the PDF column</td><td>Last</td></tr><tr><td colspan="2">Combined</td><td>Last</td></tr></tbody></table>',
	'table td { padding: 2px; }'
);
pfCheck((bool) preg_match('/<td[^>]*width="30%"[^>]*>Very long value/i', $fixedTable), 'PDF styler copies header widths to body cells');
pfCheck((bool) preg_match('/<td[^>]*width="50%"[^>]*>Combined/i', $fixedTable), 'PDF styler combines widths for colspan cells');

$normalizedTable = PrintFormatPdfStyler::prepare(
	'<table><tbody><tr><td width="20%">Left</td><td width="30%">Middle</td><td width="30%">Right</td></tr></tbody></table>',
	'table { width: 100%; }'
);
pfCheck(strpos($normalizedTable, 'width="25%"') !== false && substr_count($normalizedTable, 'width="37.5%"') === 2, 'PDF styler normalizes incomplete percentage grids like a browser');

$unitResult = $db->query('SELECT rowid,code,label,short_label FROM '.$db->prefix().'c_units WHERE active=1 ORDER BY rowid LIMIT 1');
$unitRow = $unitResult ? $db->fetch_object($unitResult) : null;
if ($unitRow) {
	$fieldObject = (object) array(
		'id' => 123,
		'entity' => (int) $conf->entity,
		'ref' => 'FIELD-TEST',
		'date' => dol_now(),
		'custom_document_value' => 'DOC-VALUE',
		'warehouse_id' => 0,
		'array_options' => array(),
		'lines' => array((object) array(
			'id' => 1,
			'fk_unit' => (int) $unitRow->rowid,
			'product_ref' => 'SKU-TEST',
			'desc' => 'Line test',
			'qty' => 2,
			'custom_line_value' => 'LINE-VALUE',
			'array_options' => array(),
		)),
	);
	$fieldContext = $renderer->contextForObject($fieldObject, $langs, 'invoice');
	pfCheck($fieldContext['lines'][0]['unit'] !== '' && $fieldContext['lines'][0]['unit_code'] === (string) $unitRow->code, 'line unit id resolves through the units table');
	pfCheck($fieldContext['doc']['custom_document_value'] === 'DOC-VALUE' && $fieldContext['lines'][0]['custom_line_value'] === 'LINE-VALUE', 'doctype object scalar fields are exposed to templates');
	$defaultOutput = $renderer->render('{{ missing | default(doc.ref) }}|{{ missing | default("None") }}', $fieldContext, $langs);
	pfCheck($defaultOutput === 'FIELD-TEST|None', 'default filter supports field and literal fallbacks');
}

try {
	PrintFormatRenderer::validateSyntax('{% for line in lines %}missing close');
	throw new RuntimeException('FAIL: invalid syntax was accepted');
} catch (DomainException $e) {
	pfCheck(true, 'invalid syntax is rejected');
}

echo 'Print Format renderer: '.$checks." checks passed\n";
