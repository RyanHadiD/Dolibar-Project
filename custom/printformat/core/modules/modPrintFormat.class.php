<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modPrintFormat extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 529430;
		$this->rights_class = 'printformat';
		$this->family = 'technic';
		$this->name = 'PrintFormat';
		$this->description = 'HTML and CSS print formats for Dolibarr documents';
		$this->version = '0.5.1';
		$this->const_name = 'MAIN_MODULE_PRINTFORMAT';
		$this->picto = 'file-pdf';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array();
		$this->module_parts = array(
			'models' => 1,
			'hooks' => array('all'),
		);
		$this->langfiles = array('printformat@printformat');
		$this->dirs = array('/printformat/temp');

		$this->rights = array(
			array($this->numero + 1, 'Read print formats', 'r', 1, 'read'),
			array($this->numero + 2, 'Create and edit print formats', 'w', 0, 'write'),
		);

		$this->menu = array(
			array('fk_menu' => '', 'type' => 'top', 'titre' => 'PFPrintFormats', 'mainmenu' => 'printformat', 'leftmenu' => '', 'url' => '/printformat/index.php', 'langs' => 'printformat@printformat', 'position' => 1280, 'enabled' => 'isModEnabled("printformat")', 'perms' => '$user->hasRight("printformat", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=printformat', 'type' => 'left', 'titre' => 'PFPrintFormats', 'mainmenu' => 'printformat', 'leftmenu' => 'printformatlist', 'url' => '/printformat/index.php', 'langs' => 'printformat@printformat', 'position' => 1281, 'enabled' => 'isModEnabled("printformat")', 'perms' => '$user->hasRight("printformat", "read")', 'target' => '', 'user' => 0),
		);
	}

	public function init($options = '')
	{
		global $conf;

		if ($this->_load_tables('/printformat/sql/') < 0) {
			return -1;
		}
		$result = $this->_init(array(), $options);
		if ($result <= 0) {
			return $result;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformat.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		foreach (PrintFormatDoctypeRegistry::all() as $definition) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."document_model WHERE nom='printformat' AND type='".$this->db->escape($definition['model_type'])."' AND entity=".((int) $conf->entity);
			$resql = $this->db->query($sql);
			if (!$resql || !$this->db->fetch_object($resql)) {
				if (addDocumentModel('printformat', $definition['model_type'], 'Print Format (HTML/CSS)') < 0) {
					$this->error = $this->db->lasterror();
					return -1;
				}
			} else {
				// Dolibarr treats a non-empty description as an ODT directory constant.
				$this->db->query('UPDATE '.MAIN_DB_PREFIX."document_model SET libelle='Print Format (HTML/CSS)', description=NULL WHERE nom='printformat' AND type='".$this->db->escape($definition['model_type'])."' AND entity=".((int) $conf->entity));
			}
		}

		$repository = new PrintFormat($this->db, $conf->entity);
		$repository->ensureCustomPageColumns();
		$repository->ensureDefaultFormats();
		$repository->ensureUnitsInProductDocumentFormats();

		// PRODUCT_USE_UNITS is both the feature flag and the default unit id.
		// Use "piece" as a safe default so every new product document line has a unit.
		$unitResult = $this->db->query("SELECT rowid FROM ".$this->db->prefix()."c_units WHERE code='P' AND active=1 ORDER BY rowid LIMIT 1");
		$pieceUnit = $unitResult ? $this->db->fetch_object($unitResult) : null;
		if ($pieceUnit) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
			if (dolibarr_set_const($this->db, 'PRODUCT_USE_UNITS', (string) ((int) $pieceUnit->rowid), 'chaine', 0, 'Default unit for product document lines', $conf->entity) <= 0) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return $result;
	}

	public function remove($options = '')
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformat.class.php';
		foreach (PrintFormatDoctypeRegistry::all() as $definition) {
			delDocumentModel('printformat', $definition['model_type']);
		}
		return $this->_remove(array(), $options);
	}
}
