<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformat.class.php';

/** Shared PDF writer for every registered native CommonObject doctype. */
class PrintFormatDocumentGenerator
{
	public $db;
	public $error = '';
	public $errors = array();
	public $warnings = array();
	public $result = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function write($object, $outputlangs, $doctype, $formatId = 0)
	{
		global $conf, $langs, $hookmanager, $action;

		try {
			$prepared = $this->prepareDocument($object, $outputlangs, $doctype, $formatId);
			$definition = $prepared['definition'];
			$format = $prepared['format'];
			$html = $prepared['html'];
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			return -1;
		}

		$outputRoot = $this->outputRoot($object, $doctype);
		if (!$outputRoot || strpos($outputRoot, 'error-') === 0) {
			$this->error = 'Direktori dokumen native tidak tersedia untuk '.$definition['label'].'.';
			return -1;
		}
		$reference = $prepared['reference'];
		$formatName = dol_sanitizeFileName(trim((string) $format->name));
		$documentName = $reference.($formatName !== '' ? ' - '.$formatName : '');
		$baseDirectory = rtrim($outputRoot, '/');
		if (!empty($object->specimen)) {
			$dir = $baseDirectory;
			$documentName = 'SPECIMEN'.($formatName !== '' ? ' - '.$formatName : '');
		} elseif ($doctype === 'supplier_invoice') {
			// Dolibarr distributes supplier invoices in two numeric levels
			// (for example 1/1/SI2610-0002) before the reference directory.
			$baseDirectory .= '/'.get_exdir($object->id, 2, 0, 1, $object, 'invoice_supplier');
			$dir = $baseDirectory.'/'.$reference;
		} elseif ($doctype === 'stock_transfer') {
			$baseDirectory .= '/'.dol_sanitizeFileName((string) $object->element);
			$dir = $baseDirectory.'/'.$reference;
		} else {
			$dir = $baseDirectory.'/'.$reference;
		}
		$file = $dir.'/'.$documentName.'.pdf';
		if (!is_dir($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return -1;
		}
		if (!is_writable($dir)) {
			$this->error = 'Direktori dokumen tidak dapat ditulis oleh web server: '.$dir;
			return -1;
		}
		if (is_file($file) && !is_writable($file)) {
			$this->error = 'File PDF tidak dapat ditimpa oleh web server: '.$file;
			return -1;
		}

		if (!is_object($hookmanager)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		try {
			$this->createPdf($html, $format, $definition, $reference, $outputlangs, 'F', $file);
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			return -1;
		}
		clearstatcache(true, $file);
		if (!is_file($file) || !is_readable($file) || filesize($file) <= 0) {
			$this->error = 'PDF gagal ditulis atau tidak dapat dibaca: '.$file;
			return -1;
		}

		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		$this->warnings = $hookmanager->warnings;
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}
		dolChmod($file);
		$this->result = array('fullpath' => $file);
		return 1;
	}

	/** Build the exact PDF used by generation without writing into document storage. */
	public function preview($object, $outputlangs, $doctype, $formatId = 0)
	{
		try {
			$prepared = $this->prepareDocument($object, $outputlangs, $doctype, $formatId, true);
			$content = $this->createPdf(
				$prepared['html'],
				$prepared['format'],
				$prepared['definition'],
				$prepared['reference'],
				$outputlangs,
				'S'
			);
			if (!is_string($content) || strncmp($content, '%PDF', 4) !== 0) {
				throw new RuntimeException('Preview PDF gagal dibuat.');
			}
			$this->result = array(
				'content' => $content,
				'filename' => $prepared['reference'].' - '.dol_sanitizeFileName((string) $prepared['format']->name).'.pdf',
			);
			return 1;
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			return -1;
		}
	}

	private function prepareDocument($object, $outputlangs, $doctype, $formatId, $allowInactive = false)
	{
		global $conf;

		$definition = PrintFormatDoctypeRegistry::get($doctype);
		$repository = new PrintFormat($this->db, $object->entity ?? $conf->entity);
		$format = $formatId ? $repository->get($formatId, !$allowInactive) : $repository->getDefault($doctype);
		if ($format->doctype !== $doctype) {
			throw new DomainException('Print format tidak sesuai dengan doctype '.$definition['label'].'.');
		}
		$renderer = new PrintFormatRenderer();
		$context = $renderer->contextForObject($object, $outputlangs, $doctype);
		$rendered = $renderer->render((string) $format->template_html, $context, $outputlangs);
		$reference = (string) ($object->ref ?? $object->code ?? $object->login ?? $object->label ?? $object->id);
		$reference = dol_sanitizeFileName($reference !== '' ? $reference : (string) $object->id);

		return array(
			'definition' => $definition,
			'format' => $format,
			'html' => PrintFormatPdfStyler::prepare($rendered, (string) $format->template_css),
			'reference' => $reference,
		);
	}

	private function createPdf($html, $format, $definition, $reference, $outputlangs, $destination, $file = '')
	{
		global $mysoc, $user;

		$dimensions = self::pageDimensions($format);
		$orientation = $format->orientation === 'L' ? 'L' : 'P';
		$pdf = pdf_getInstance($dimensions, 'mm', $orientation);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins((float) $format->margin_left, (float) $format->margin_top, (float) $format->margin_right);
		$pdf->SetAutoPageBreak(true, (float) $format->margin_bottom);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$pdf->SetTitle($outputlangs->convToOutputCharset($reference));
		$pdf->SetSubject($outputlangs->convToOutputCharset($definition['label']));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION.' / Print Format');
		$pdf->SetAuthor($mysoc->name.($user->id > 0 ? ' - '.$user->getAnonymisableFullName($outputlangs) : ''));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}
		$pdf->AddPage($orientation);
		$pdf->writeHTML($html, true, false, true, false, '');
		$pdf->Close();
		return $pdf->Output($destination === 'F' ? $file : $reference.'.pdf', $destination);
	}

	public static function pageDimensions($format)
	{
		$pageFormat = strtoupper((string) ($format->page_format ?? 'A4'));
		if ($pageFormat === 'LETTER') {
			return array(215.9, 279.4);
		}
		if ($pageFormat === 'CUSTOM') {
			$width = (float) ($format->custom_width ?? 0);
			$height = (float) ($format->custom_height ?? 0);
			if ($width < 20 || $width > 2000 || $height < 20 || $height > 2000) {
				throw new DomainException('Ukuran halaman custom tidak valid. Simpan ulang print format.');
			}
			return array($width, $height);
		}
		return array(210, 297);
	}

	private function outputRoot($object, $doctype)
	{
		global $conf;
		$entity = (int) ($object->entity ?? $conf->entity);
		if ($doctype === 'stock_movement') {
			$root = !empty($conf->stock->multidir_output[$entity]) ? $conf->stock->multidir_output[$entity] : $conf->stock->dir_output;
			return rtrim((string) $root, '/').'/movement';
		}
		if ($doctype === 'manufacturing_order') {
			return !empty($conf->mrp->multidir_output[$entity]) ? $conf->mrp->multidir_output[$entity] : $conf->mrp->dir_output;
		}
		if ($doctype === 'supplier_payment') {
			return !empty($conf->fournisseur->payment->multidir_output[$entity]) ? $conf->fournisseur->payment->multidir_output[$entity] : $conf->fournisseur->payment->dir_output;
		}
		if ($doctype === 'stock_transfer') {
			return !empty($conf->stocktransfer->multidir_output[$entity]) ? $conf->stocktransfer->multidir_output[$entity] : $conf->stocktransfer->dir_output;
		}
		if ($doctype === 'evaluation') {
			$root = !empty($conf->hrm->multidir_output[$entity]) ? $conf->hrm->multidir_output[$entity] : $conf->hrm->dir_output;
			return rtrim((string) $root, '/').'/evaluation';
		}
		if ($doctype === 'asset') {
			$root = !empty($conf->asset->multidir_output[$entity]) ? $conf->asset->multidir_output[$entity] : $conf->asset->dir_output;
			return rtrim((string) $root, '/').'/asset';
		}
		return getMultidirOutput($object);
	}
}
