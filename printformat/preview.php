<?php

require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/printformatgenerator.class.php';
$format = $printFormatRepository->get(GETPOSTINT('format_id'));
$object = pfFetchObject($format->doctype, GETPOSTINT('object_id'));
$generator = new PrintFormatDocumentGenerator($db);
if ($generator->preview($object, $langs, $format->doctype, (int) $format->rowid) <= 0) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=UTF-8');
	print 'Preview gagal: '.$generator->error;
	exit;
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.str_replace(array('"', "\r", "\n"), '', $generator->result['filename']).'"');
header('Content-Length: '.strlen($generator->result['content']));
print $generator->result['content'];
