<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}

define('NOSESSION', 1);
require dirname(__DIR__, 3).'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformatgenerator.class.php';

class PrintFormatSmokeInvoice
{
	public $id = 999999;
	public $entity;
	public $element = 'facture';
	public $ref = 'PF-SMOKE';
	public $ref_client = 'PO-TEST';
	public $date;
	public $date_lim_reglement;
	public $status = 1;
	public $type = 0;
	public $note_public = 'Smoke test';
	public $note_private = '';
	public $total_ht = 100000;
	public $total_tva = 11000;
	public $total_ttc = 111000;
	public $multicurrency_code = '';
	public $multicurrency_tx = 1;
	public $multicurrency_total_ht = 100000;
	public $multicurrency_total_tva = 11000;
	public $multicurrency_total_ttc = 111000;
	public $cond_reglement_doc = '30 days';
	public $mode_reglement_code = 'VIR';
	public $lines = array();
	public $thirdparty;
	public $array_options = array();
	public $specimen = 0;

	public function fetch_thirdparty() { return 1; }
	public function fetch_lines() { return 1; }
	public function getSommePaiement() { return 10000; }
	public function getSumCreditNotesUsed() { return 0; }
	public function getSumDepositsUsed() { return 0; }
	public function getLibStatut() { return 'Validated'; }
}

class PrintFormatSmokeSupplierInvoice extends PrintFormatSmokeInvoice
{
	public $id = 11;
	public $element = 'invoice_supplier';
	public $ref = 'PF-SUPPLIER-SMOKE';
}

$invoice = new PrintFormatSmokeInvoice();
$invoice->entity = (int) $conf->entity;
$invoice->date = dol_now();
$invoice->date_lim_reglement = dol_now() + 86400 * 30;
$invoice->thirdparty = (object) array(
	'id' => 1, 'name' => 'Pelanggan Uji', 'name_alias' => '', 'address' => 'Jl. Contoh 1',
	'zip' => '10110', 'town' => 'Jakarta', 'state' => '', 'country' => 'Indonesia',
	'country_code' => 'ID', 'email' => 'test@example.test', 'phone' => '021',
	'tva_intra' => '', 'code_client' => 'C-TEST', 'array_options' => array(),
);
$invoice->lines = array((object) array(
	'id' => 1, 'fk_product' => 1, 'product_ref' => 'ITEM-1', 'product_label' => 'Barang Uji',
	'desc' => 'Barang uji aman', 'qty' => 2, 'subprice' => 50000, 'remise_percent' => 0,
	'tva_tx' => 11, 'total_ht' => 100000, 'total_tva' => 11000, 'total_ttc' => 111000,
	'multicurrency_subprice' => 50000, 'multicurrency_total_ht' => 100000,
	'multicurrency_total_tva' => 11000, 'multicurrency_total_ttc' => 111000, 'array_options' => array(),
));

$temporaryRoot = sys_get_temp_dir().'/pf_smoke_'.bin2hex(random_bytes(5));
$oldOutput = $conf->facture->dir_output;
$hadMultiOutput = isset($conf->facture->multidir_output[$conf->entity]);
$oldMultiOutput = $hadMultiOutput ? $conf->facture->multidir_output[$conf->entity] : null;
$file = '';

try {
	if (!mkdir($temporaryRoot, 0700, true)) {
		throw new RuntimeException('Cannot create temporary output directory.');
	}
	$conf->facture->dir_output = $temporaryRoot;
	$conf->facture->multidir_output[$conf->entity] = $temporaryRoot;
	$generator = new PrintFormatDocumentGenerator($db);
	$result = $generator->write($invoice, $langs, 'invoice');
	if ($result <= 0) {
		throw new RuntimeException('PDF generator failed: '.$generator->error);
	}
	$file = $generator->result['fullpath'];
	$expectedFile = $temporaryRoot.'/'.$invoice->ref.'/'.$invoice->ref.' - Invoice Standard.pdf';
	if ($file !== $expectedFile) {
		throw new RuntimeException('PDF filename does not contain the document reference and print format name.');
	}
	if (!is_file($file) || file_get_contents($file, false, null, 0, 4) !== '%PDF') {
		throw new RuntimeException('Generator output is not a PDF.');
	}
	echo 'Print Format PDF: valid ('.filesize($file)." bytes)\n";
	if ($generator->preview($invoice, $langs, 'invoice') <= 0 || strncmp($generator->result['content'], '%PDF', 4) !== 0) {
		throw new RuntimeException('In-memory PDF preview failed: '.$generator->error);
	}
	echo "Print Format preview: valid\n";

	$supplierInvoice = new PrintFormatSmokeSupplierInvoice();
	$supplierInvoice->entity = (int) $conf->entity;
	$supplierInvoice->date = $invoice->date;
	$supplierInvoice->date_lim_reglement = $invoice->date_lim_reglement;
	$supplierInvoice->thirdparty = $invoice->thirdparty;
	$supplierInvoice->lines = $invoice->lines;
	$oldSupplierOutput = $conf->supplier_invoice->dir_output;
	$hadSupplierMultiOutput = isset($conf->supplier_invoice->multidir_output[$conf->entity]);
	$oldSupplierMultiOutput = $hadSupplierMultiOutput ? $conf->supplier_invoice->multidir_output[$conf->entity] : null;
	$conf->supplier_invoice->dir_output = $temporaryRoot;
	$conf->supplier_invoice->multidir_output[$conf->entity] = $temporaryRoot;
	$result = $generator->write($supplierInvoice, $langs, 'supplier_invoice');
	if ($result <= 0) {
		throw new RuntimeException('Supplier invoice PDF generator failed: '.$generator->error);
	}
	$supplierFile = $generator->result['fullpath'];
	$expectedSupplierFile = $temporaryRoot.'/1/1/'.$supplierInvoice->ref.'/'.$supplierInvoice->ref.' - Supplier Invoice Standard.pdf';
	if ($supplierFile !== $expectedSupplierFile || !is_file($supplierFile)) {
		throw new RuntimeException('Supplier invoice PDF was not written to the native Dolibarr directory.');
	}
	echo "Supplier invoice path: valid\n";
} finally {
	$conf->facture->dir_output = $oldOutput;
	if ($hadMultiOutput) {
		$conf->facture->multidir_output[$conf->entity] = $oldMultiOutput;
	} else {
		unset($conf->facture->multidir_output[$conf->entity]);
	}
	if ($file && is_file($file)) {
		unlink($file);
	}
	if (!empty($supplierFile) && is_file($supplierFile)) {
		unlink($supplierFile);
	}
	if (isset($oldSupplierOutput)) {
		$conf->supplier_invoice->dir_output = $oldSupplierOutput;
		if ($hadSupplierMultiOutput) {
			$conf->supplier_invoice->multidir_output[$conf->entity] = $oldSupplierMultiOutput;
		} else {
			unset($conf->supplier_invoice->multidir_output[$conf->entity]);
		}
	}
	$supplierDirectory = $temporaryRoot.'/1/1/'.$supplierInvoice->ref;
	if (is_dir($supplierDirectory)) {
		rmdir($supplierDirectory);
	}
	if (is_dir($temporaryRoot.'/1/1')) {
		rmdir($temporaryRoot.'/1/1');
	}
	if (is_dir($temporaryRoot.'/1')) {
		rmdir($temporaryRoot.'/1');
	}
	$outputDirectory = $temporaryRoot.'/'.$invoice->ref;
	if (is_dir($outputDirectory)) {
		rmdir($outputDirectory);
	}
	if (is_dir($temporaryRoot)) {
		rmdir($temporaryRoot);
	}
}
