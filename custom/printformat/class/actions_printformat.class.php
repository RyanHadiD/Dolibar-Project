<?php

require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformat.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformatgenerator.class.php';

class ActionsPrintFormat
{
	public $db;
	public $resprints = '';
	public $error = '';
	public $errors = array();
	public $warnings = array();
	public $results = array();
	private $productUnitCache = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Add a one-click Excel export to data tables on private pages. */
	public function printCommonFooter($parameters, &$object, &$action)
	{
		global $user;
		$this->resprints = '';
		if (($parameters['zone'] ?? 'private') !== 'private' || empty($user->id)) {
			return 0;
		}

		$config = array(
			'endpoint' => dol_buildpath('/printformat/export.php', 1),
			'token' => currentToken(),
			'label' => 'Export Excel',
			'workingLabel' => 'Membuat Excel...',
			'errorLabel' => 'Export Excel gagal.',
			'emptyLabel' => 'Tidak ada tabel data yang dapat diexport.',
		);
		$nonce = getNonce();
		$this->resprints = '<script nonce="'.$nonce.'">window.PrintFormatDirectExport='.json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>';
		$this->resprints .= '<script nonce="'.$nonce.'" src="'.dol_buildpath('/printformat/js/direct-export.js?v=0.5.1', 1).'" defer></script>';
		$journalPiece = GETPOSTINT('piece_num');
		if ($journalPiece > 0 && strpos((string) ($_SERVER['PHP_SELF'] ?? ''), '/accountancy/bookkeeping/card.php') !== false && $user->hasRight('accounting', 'mouvements', 'lire') && $user->hasRight('printformat', 'read')) {
			$controls = $this->directPrintControls('accounting_journal', $journalPiece);
			$this->resprints .= '<script nonce="'.$nonce.'">jQuery(function(){if(jQuery(".pf-print-form").length)return;var b=jQuery("<div class=\"tabsAction pf-print-actions\"></div>").html('.json_encode($controls).');var anchor=jQuery(".fichecenter").first();if(anchor.length){anchor.after(b);}else{jQuery("#id-right").prepend(b);}});</script>';
		}
		return 0;
	}

	/** Add direct print actions to records without a native build-document panel. */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $user;
		$this->resprints = '';
		if (!$user->hasRight('printformat', 'read') || !is_object($object)) return 0;

		$doctype = '';
		$id = 0;
		if (($object->element ?? '') === 'payment' && $user->hasRight('facture', 'lire')) {
			$doctype = 'customer_payment';
			$id = (int) ($object->id ?? 0);
		} elseif (($object->element ?? '') === 'accountingbookkeeping' && $user->hasRight('accounting', 'mouvements', 'lire')) {
			$doctype = 'accounting_journal';
			$id = (int) ($object->piece_num ?? 0);
		}
		if (!$doctype || $id <= 0) return 0;

		$button = $this->directPrintControls($doctype, $id);
		// addMoreActionsButtons callers print buttons directly; most do not render resPrint.
		print $button;
		return 0;
	}

	/** Build format selection and a PDF action for records without native builddoc UI. */
	private function directPrintControls($doctype, $id)
	{
		global $conf;
		$repository = new PrintFormat($this->db, $conf->entity);
		$formats = $repository->all($doctype, true);
		if (!$formats) {
			$repository->getDefault($doctype);
			$formats = $repository->all($doctype, true);
		}
		$options = '';
		foreach ($formats as $format) {
			$options .= '<option value="'.(int) $format->rowid.'"'.(!empty($format->is_default) ? ' selected' : '').'>'.dol_escape_htmltag($format->name).'</option>';
		}
		$url = dol_buildpath('/printformat/document.php', 1);
		$html = '<form class="inline-block pf-print-form" action="'.dol_escape_htmltag($url).'" method="get" target="_blank">';
		$html .= '<input type="hidden" name="doctype" value="'.dol_escape_htmltag($doctype).'">';
		$html .= '<input type="hidden" name="id" value="'.(int) $id.'">';
		$html .= '<label class="opacitymedium" for="pf-format-'.$doctype.'-'.$id.'">Print format</label> ';
		$html .= '<select class="flat minwidth200" id="pf-format-'.$doctype.'-'.$id.'" name="format_id">'.$options.'</select> ';
		$html .= '<button class="butAction pf-print-pdf" type="submit">'.img_picto('', 'file-pdf').' Print PDF</button>';
		$html .= '</form>';
		return $html;
	}

	/** Supply a meaningful fallback for legacy document lines with no stored unit. */
	public function pdf_getlineunit($parameters, &$object, &$action)
	{
		$this->resprints = '';
		$index = isset($parameters['i']) ? (int) $parameters['i'] : -1;
		if ($index < 0 || empty($object->lines[$index])) {
			return 0;
		}
		$line = $object->lines[$index];
		if (!empty($line->fk_unit)) {
			return 0;
		}

		$unitId = $this->resolveLineUnitId($line);
		if ($unitId <= 0) {
			return 0;
		}

		$outputlangs = $parameters['outputlangs'] ?? null;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
		$this->resprints = measuringUnitString($unitId, '', null, 1, $outputlangs);
		return $this->resprints !== '' ? 1 : 0;
	}

	/** Ensure PDF models that read fk_unit directly also receive the fallback. */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		if (!is_object($object) || empty($object->lines) || !is_array($object->lines)) {
			return 0;
		}
		foreach ($object->lines as $line) {
			if (is_object($line) && empty($line->fk_unit)) {
				$unitId = $this->resolveLineUnitId($line);
				if ($unitId > 0) $line->fk_unit = $unitId;
			}
		}
		return 0;
	}

	private function resolveLineUnitId($line)
	{
		$unitId = (int) ($line->fk_unit ?? 0);
		$productId = (int) ($line->fk_product ?? 0);
		if ($unitId <= 0 && $productId > 0) {
			if (!array_key_exists($productId, $this->productUnitCache)) {
				$result = $this->db->query('SELECT fk_unit FROM '.$this->db->prefix().'product WHERE rowid='.$productId.' LIMIT 1');
				$row = $result ? $this->db->fetch_object($result) : null;
				$this->productUnitCache[$productId] = (int) ($row->fk_unit ?? 0);
			}
			$unitId = $this->productUnitCache[$productId];
		}
		return $unitId > 0 ? $unitId : getDolGlobalInt('PRODUCT_USE_UNITS');
	}

	public function formBuilddocOptions($parameters, &$object, &$action)
	{
		global $conf, $user;
		$this->resprints = '';
		if (!$user->hasRight('printformat', 'read')) {
			return 0;
		}
		$doctype = PrintFormatDoctypeRegistry::resolve($object, '', $parameters['modulepart'] ?? '');
		if (!$doctype) {
			return 0;
		}
		$definition = PrintFormatDoctypeRegistry::get($doctype);
		$repository = new PrintFormat($this->db, $object->entity ?? $conf->entity);
		$formats = $repository->all($doctype, true);
		if (!$formats) {
			return 0;
		}
		$selected = GETPOSTINT('printformat_id');
		$options = '';
		foreach ($formats as $format) {
			if (!$selected && $format->is_default) {
				$selected = (int) $format->rowid;
			}
			$options .= '<option value="'.(int) $format->rowid.'"'.($selected === (int) $format->rowid ? ' selected' : '').'>'.dol_escape_htmltag($format->name).'</option>';
		}
		$this->resprints = '<tr id="printformat-options"><td colspan="'.(int) $parameters['colspan'].'" class="center"><label for="printformat_id">Print format '.$definition['label'].' </label><select class="flat minwidth200" name="printformat_id" id="printformat_id">'.$options.'</select> <a href="'.dol_buildpath('/printformat/index.php?doctype='.$doctype, 1).'">Kelola format</a></td></tr>';
		$this->resprints .= '<script nonce="'.getNonce().'">jQuery(function(){function pfToggle(){jQuery("#printformat-options").toggle(jQuery("select[name=model]").val()==="printformat");}jQuery("select[name=model]").on("change",pfToggle);pfToggle();jQuery("#builddoc_form").on("submit",function(){if(jQuery("select[name=model]",this).val()==="printformat"){var button=jQuery("#builddoc_generatebutton");button.prop("disabled",true).val("GENERATING PDF...");if(!jQuery("#pf-generating").length){button.after(" <span id=pf-generating class=opacitymedium><i class=\"fa fa-spinner fa-spin\"></i> Generating...</span>");}}});});</script>';
		return 0;
	}

	public function commonGenerateDocument($parameters, &$object, &$action, $hookmanager)
	{
		if (($parameters['modele'] ?? '') !== 'printformat') {
			return 0;
		}
		$doctype = PrintFormatDoctypeRegistry::resolve($object, $parameters['modelspath'] ?? '');
		if (!$doctype) {
			$this->error = 'Print Format belum memetakan model path '.($parameters['modelspath'] ?? '').'.';
			return -1;
		}
		$generator = new PrintFormatDocumentGenerator($this->db);
		$result = $generator->write($object, $parameters['outputlangs'], $doctype, GETPOSTINT('printformat_id'));
		if ($result <= 0) {
			$this->error = $generator->error;
			$this->errors = $generator->errors;
			$this->warnings = $generator->warnings;
			return -1;
		}

		$object->model_pdf = 'printformat';
		$file = $generator->result['fullpath'];
		if (is_file($file)) {
			$object->indexFile($file, 1);
		}
		if (function_exists('dol_delete_preview')) {
			dol_delete_preview($object);
		}
		if (function_exists('dol_meta_create') && !empty($object->table_element)) {
			dol_meta_create($object);
		}
		$this->results = array('fullpath' => $file);
		$this->warnings = $generator->warnings;
		return 1;
	}
}
