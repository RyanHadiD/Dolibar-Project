<?php

require_once __DIR__.'/namingseries.class.php';
require_once __DIR__.'/documentrenumber.class.php';

class ActionsNamingSeries
{
	public $db;
	public $error = '';
	public $errors = array();
	public $resprints = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function doActions($parameters, &$object, &$action)
	{
		global $conf;
		if (!GETPOSTISSET('naming_series_code')) return 0;
		$type = NamingSeries::documentTypeForObject($object);
		if ($type === '') return 0;

		$code = GETPOST('naming_series_code', 'alphanohtml');
		$engine = new NamingSeries($this->db, $conf->entity);
		$probe = (object) array('naming_series_code' => $code);
		if ($code === '' || !$engine->resolveSeries($type, $probe)) {
			$this->error = 'Naming Series yang dipilih tidak aktif atau tidak sesuai dengan jenis dokumen.';
			$this->errors[] = $this->error;
			return -1;
		}
		$object->naming_series_code = $code;
		return 0;
	}

	public function formObjectOptions($parameters, &$object, &$action)
	{
		global $conf;
		if ($action !== 'create') {
			$button = $this->renameButton($object, $action);
			if ($button !== '') {
				$this->resprints = '<script>jQuery(function(){if(!jQuery("#ns-rename-document-action").length){var t=jQuery(".tabsAction").first();if(t.length)t.prepend('.json_encode($button).');}});</script>';
			}
			return 0;
		}
		$type = NamingSeries::documentTypeForObject($object);
		if ($type === '' || !NamingSeries::usesNamingSeriesModel($type)) return 0;

		$engine = new NamingSeries($this->db, $conf->entity);
		$series = array_values(array_filter($engine->listSeries($type), function ($row) {
			return !empty($row->active);
		}));
		if (count($series) < 1) return 0;

		$selected = GETPOST('naming_series_code', 'alphanohtml');
		if ($selected === '') {
			foreach ($series as $row) if (!empty($row->is_default)) { $selected = $row->code; break; }
		}
		if ($selected === '') $selected = $series[0]->code;

		$colspan = !empty($parameters['colspan']) ? $parameters['colspan'] : '';
		$html = '<tr class="ns-series-selector"><td class="fieldrequired"><label for="naming_series_code">Naming Series</label></td><td'.$colspan.'>';
		$html .= '<select class="flat minwidth300" id="naming_series_code" name="naming_series_code" required>';
		foreach ($series as $row) {
			try { $preview = $engine->preview($row, $object); } catch (Throwable $e) { $preview = $row->pattern; }
			$html .= '<option value="'.dol_escape_htmltag($row->code).'"'.($selected === $row->code ? ' selected' : '').'>'
				.dol_escape_htmltag($row->label.' — '.$preview).'</option>';
		}
		$html .= '</select> <span class="opacitymedium">Pilih seri nomor untuk dokumen ini.</span></td></tr>';
		$this->resprints = $html;
		return 0;
	}

	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		$button = $this->renameButton($object, $action);
		if ($button !== '') print $button;
		return 0;
	}

	private function renameButton($object, $action)
	{
		global $conf, $user;
		if (empty($object->id) || (!$user->admin && !$user->hasRight('namingseries', 'counter')) || ($action !== '' && $action !== 'view')) return '';
		$type = NamingSeries::documentTypeForObject($object);
		if ($type === '') return '';
		$engine = new NamingSeries($this->db, $conf->entity);
		$service = new NamingSeriesDocumentRenumber($this->db, $engine, $conf->entity);
		if (!$service->supportsType($type)) return '';
		$returnUrl = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$url = dol_buildpath('/namingseries/rename.php', 1).'?document_type='.urlencode($type).'&id='.(int) $object->id.'&return_url='.urlencode($returnUrl);
		return '<a id="ns-rename-document-action" class="butAction" href="'.dol_escape_htmltag($url).'">'.img_picto('', 'edit').' Rename Document</a>';
	}
}
