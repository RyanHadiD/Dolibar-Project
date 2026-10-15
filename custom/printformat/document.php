<?php

define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require dirname(__DIR__, 2).'/main.inc.php';
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/printformatgenerator.class.php';

$doctype = GETPOST('doctype', 'aZ09');
$id = GETPOSTINT('id');
$allowed = false;
if ($doctype === 'customer_payment') {
	$allowed = $user->hasRight('facture', 'lire');
} elseif ($doctype === 'supplier_payment') {
	$allowed = $user->hasRight('fournisseur', 'facture', 'lire');
} elseif ($doctype === 'accounting_journal') {
	$allowed = $user->hasRight('accounting', 'mouvements', 'lire');
}
if (!$allowed || $id <= 0) accessforbidden();

try {
	$object = pfFetchObject($doctype, $id);
	$outputlangs = $langs;
	$outputlangs->loadLangs(array('main', 'bills', 'banks', 'accountancy'));
	$generator = new PrintFormatDocumentGenerator($db);
	if ($generator->preview($object, $outputlangs, $doctype, GETPOSTINT('format_id')) <= 0) {
		throw new RuntimeException($generator->error);
	}
	$content = $generator->result['content'];
	$filename = dol_sanitizeFileName($generator->result['filename']);
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="'.$filename.'"');
	header('Content-Length: '.strlen($content));
	header('Cache-Control: private, no-store');
	header('X-Content-Type-Options: nosniff');
	print $content;
} catch (Throwable $e) {
	http_response_code(400);
	header('Content-Type: text/plain; charset=UTF-8');
	print 'Print PDF gagal: '.$e->getMessage();
}

$db->close();
