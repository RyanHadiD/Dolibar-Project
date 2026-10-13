<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modOpeningBalance extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 529420;
		$this->rights_class = 'openingbalance';
		$this->family = 'financial';
		$this->name = 'OpeningBalance';
		$this->description = 'Controlled opening balances and cutover migration';
		$this->version = '0.3.0';
		$this->const_name = 'MAIN_MODULE_OPENINGBALANCE';
		$this->picto = 'accounting';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array('modSociete', 'modProduct', 'modStock', 'modFacture', 'modFournisseur', 'modAccounting', 'modPerpetualInventory');
		$this->langfiles = array('openingbalance@openingbalance');
		$this->dirs = array();

		$this->rights = array(
			array($this->numero + 1, 'Read opening balance batches', 'r', 0, 'read'),
			array($this->numero + 2, 'Create and edit opening balance drafts', 'w', 0, 'write'),
			array($this->numero + 3, 'Validate opening balance batches', 'w', 0, 'validate'),
			array($this->numero + 4, 'Post opening balance batches', 'w', 0, 'post'),
		);

		$this->menu = array(
			array('fk_menu' => '', 'type' => 'top', 'titre' => 'OBOpeningBalances', 'mainmenu' => 'openingbalance', 'leftmenu' => '', 'url' => '/openingbalance/index.php', 'langs' => 'openingbalance@openingbalance', 'position' => 1270, 'enabled' => 'isModEnabled("openingbalance")', 'perms' => '$user->hasRight("openingbalance", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=openingbalance', 'type' => 'left', 'titre' => 'OBBatches', 'mainmenu' => 'openingbalance', 'leftmenu' => 'obbatches', 'url' => '/openingbalance/index.php', 'langs' => 'openingbalance@openingbalance', 'position' => 1271, 'enabled' => 'isModEnabled("openingbalance")', 'perms' => '$user->hasRight("openingbalance", "read")', 'target' => '', 'user' => 0),
		);
	}

	public function init($options = '')
	{
		if ($this->_load_tables('/openingbalance/sql/') < 0) {
			return -1;
		}
		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		// Migration and audit data are intentionally preserved when the module is disabled.
		return $this->_remove(array(), $options);
	}
}
