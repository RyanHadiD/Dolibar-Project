<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
require_once __DIR__.'/../class/pivaluation.class.php';
$checks = 0;
function same($actual, $expected, $label)
{
	global $checks;
	if (abs($actual - $expected) > 0.00000001) {
		throw new RuntimeException($label.': '.$actual.' != '.$expected);
	}
	$checks++;
}
function rejected($callback, $label)
{
	global $checks;
	try {
		$callback();
	} catch (DomainException $e) {
		$checks++;
		return;
	} catch (InvalidArgumentException $e) {
		$checks++;
		return;
	}
	throw new RuntimeException('Expected rejection: '.$label);
}
$r = PiValuation::move(0, 0, 10, 100000);
same($r['value'], 1000000, 'Receipt value');
$r = PiValuation::move($r['qty'], $r['value'], -3);
same($r['delta'], -300000, 'COGS');
same($r['qty'], 7, 'Remaining quantity');
same($r['value'], 700000, 'Remaining value');
$r = PiValuation::move(10, 1000000, 10, 120000);
$r = PiValuation::move($r['qty'], $r['value'], -5);
same($r['cost'], 110000, 'Moving average');
same($r['value'], 1650000, 'Average remaining value');
$r = PiValuation::move(3, 1, -1);
$r = PiValuation::move($r['qty'], $r['value'], -1);
$r = PiValuation::move($r['qty'], $r['value'], -1);
same($r['value'], 0, 'Final issue consumes rounding residue');
$r = PiValuation::move(7, 700000, 3, 100000, 300000);
same($r['value'], 1000000, 'Delivery return at original cost');
$r = PiValuation::move(10, 1000000, -10, null, -1000000);
same($r['value'], 0, 'Exact reversal');
same(PiValuation::move(0, 0, 1, 0)['value'], 0, 'Free goods supported');
rejected(function () { PiValuation::move(2, 200, -3); }, 'Negative stock');
rejected(function () { PiValuation::move(0, 0, 1, null); }, 'Missing incoming cost');
rejected(function () { PiValuation::move(0, 0, 1, -1); }, 'Negative incoming cost');
rejected(function () { PiValuation::move(2, 200, 0); }, 'Zero movement');
rejected(function () { PiValuation::move(2, 200, -2, null, -150); }, 'Value without stock');
rejected(function () { PiValuation::move(2, 200, -1, null, 100); }, 'Wrong reversal sign');
rejected(function () { PiValuation::move(INF, 0, 1, 0); }, 'Infinite quantity');
rejected(function () { PiValuation::move(0, 20, 1, 0); }, 'Corrupted opening balance');
echo "PASS: $checks valuation assertions\n";
