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
		$rendered = PrintFormatRemoteImageLoader::embed($rendered);
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

/** Download HTTPS images once and embed them so TCPDF does not depend on URL wrappers. */
class PrintFormatRemoteImageLoader
{
	const MAX_BYTES = 2000000;
	const MAX_REDIRECTS = 3;

	public static function embed($html)
	{
		$cache = array();
		return preg_replace_callback('/(\s+src\s*=\s*)(["\'])(https:\/\/.*?)\2/is', function ($match) use (&$cache) {
			$url = html_entity_decode(trim($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if (!isset($cache[$url])) {
				$cache[$url] = self::download($url);
			}
			return $match[1].$match[2].$cache[$url].$match[2];
		}, (string) $html);
	}

	private static function download($url)
	{
		if (!function_exists('curl_init')) {
			throw new RuntimeException('Ekstensi PHP cURL diperlukan untuk memuat gambar HTTPS pada Print Format.');
		}
		for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
			$target = self::validatePublicHttpsUrl($url);
			$body = '';
			$headers = array();
			$ch = curl_init($url);
			$options = array(
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 15,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
				CURLOPT_USERAGENT => 'Dolibarr Print Format Image Loader',
				CURLOPT_RESOLVE => array($target['resolve']),
				CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
					$length = strlen($line);
					if (strpos($line, ':') !== false) {
						list($name, $value) = explode(':', $line, 2);
						$headers[strtolower(trim($name))] = trim($value);
					}
					return $length;
				},
				CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$body) {
					if (strlen($body) + strlen($chunk) > self::MAX_BYTES) return 0;
					$body .= $chunk;
					return strlen($chunk);
				},
			);
			curl_setopt_array($ch, $options);
			$ok = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			$error = curl_error($ch);
			curl_close($ch);

			if ($status >= 300 && $status < 400 && !empty($headers['location'])) {
				if ($redirects === self::MAX_REDIRECTS) throw new RuntimeException('Redirect gambar terlalu banyak: '.$url);
				$url = self::redirectUrl($url, $headers['location']);
				continue;
			}
			if (!$ok || $status < 200 || $status >= 300) {
				throw new RuntimeException('Gambar Print Format gagal dimuat (HTTP '.$status.'): '.$url.($error !== '' ? ' '.$error : ''));
			}
			if ($body === '' || strlen($body) > self::MAX_BYTES) {
				throw new RuntimeException('Gambar Print Format kosong atau melebihi batas 2 MB: '.$url);
			}
			$mime = self::imageMime($body);
			return 'data:'.$mime.';base64,'.base64_encode($body);
		}
		throw new RuntimeException('Gambar Print Format gagal dimuat: '.$url);
	}

	private static function validatePublicHttpsUrl($url)
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) throw new DomainException('URL gambar Print Format tidak valid: '.$url);
		$parts = parse_url($url);
		if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass']) || (!empty($parts['port']) && (int) $parts['port'] !== 443)) {
			throw new DomainException('Gambar Print Format hanya boleh memakai URL HTTPS publik pada port 443.');
		}
		$host = strtolower(rtrim((string) $parts['host'], '.'));
		$ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : gethostbynamel($host);
		if (!$ips) throw new RuntimeException('Host gambar Print Format tidak dapat ditemukan: '.$host);
		foreach ($ips as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return array('resolve' => $host.':443:'.$ip);
			}
		}
		throw new DomainException('URL gambar Print Format harus mengarah ke server internet publik.');
	}

	private static function redirectUrl($base, $location)
	{
		$location = trim((string) $location);
		if (preg_match('#^https://#i', $location)) return $location;
		if (strpos($location, '//') === 0) return 'https:'.$location;
		$parts = parse_url($base);
		$origin = 'https://'.$parts['host'];
		if (substr($location, 0, 1) === '/') return $origin.$location;
		$path = (string) ($parts['path'] ?? '/');
		return $origin.substr($path, 0, strrpos($path, '/') + 1).$location;
	}

	private static function imageMime($body)
	{
		$info = @getimagesizefromstring($body);
		$mime = strtolower((string) ($info['mime'] ?? ''));
		if (!in_array($mime, array('image/png', 'image/jpeg', 'image/gif'), true)) {
			throw new DomainException('URL Print Format bukan gambar PNG, JPEG, atau GIF yang valid.');
		}
		return $mime;
	}
}
