<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}

require_once __DIR__.'/../lib/financial.php';

$cases = array(
	array(350, 8, '350'),
	array(2300, 8, '2300'),
	array(13500, 8, '13500'),
	array(4725000, 2, '4725000'),
	array(350.125, 8, '350,125'),
	array(0, 8, '0'),
	array(-0.25, 8, '-0,25'),
);

foreach ($cases as $case) {
	$actual = piCsvNumber($case[0], $case[1]);
	if ($actual !== $case[2]) {
		throw new RuntimeException('CSV number format: '.$actual.' != '.$case[2]);
	}
}

$codes = array(
	'160442000000' => '="160442000000"',
	'0016030000057(001)' => '="0016030000057(001)"',
	'090105700000-3242-03.E' => '="090105700000-3242-03.E"',
);
foreach ($codes as $code => $expected) {
	if (piCsvCode($code) !== $expected) {
		throw new RuntimeException('CSV item code format failed for '.$code);
	}
}

echo 'PASS: '.(count($cases) + count($codes))." report format assertions\n";
