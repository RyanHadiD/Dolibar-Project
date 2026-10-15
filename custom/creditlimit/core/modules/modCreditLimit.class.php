<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modCreditLimit extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 529440;
		$this->rights_class = 'creditlimit';
		$this->family = 'financial';
		$this->name = 'CreditLimit';
		$this->description = 'Customer credit exposure, overdue controls and audited overrides';
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_CREDITLIMIT';
		$this->picto = 'payment';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array('modSociete', 'modCommande', 'modFacture');
		$this->module_parts = array('triggers' => 1, 'hooks' => array('thirdpartycard', 'ordercard', 'invoicecard', 'expeditioncard'));
		$this->config_page_url = array('setup.php@creditlimit');
		$this->langfiles = array('creditlimit@creditlimit');
		$this->dirs = array();
		$this->rights = array(
			array($this->numero + 1, 'Read customer credit control', 'r', 1, 'read'),
			array($this->numero + 2, 'Manage customer credit policies', 'w', 0, 'manage'),
			array($this->numero + 3, 'Approve credit-limit overrides', 'w', 0, 'approve'),
			array($this->numero + 4, 'Read credit-control audit trail', 'r', 0, 'audit'),
		);
		$this->menu = array(
			array('fk_menu' => '', 'type' => 'top', 'titre' => 'CLCreditControl', 'mainmenu' => 'creditlimit', 'leftmenu' => '', 'url' => '/creditlimit/index.php', 'langs' => 'creditlimit@creditlimit', 'position' => 1270, 'enabled' => 'isModEnabled("creditlimit")', 'perms' => '$user->hasRight("creditlimit", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=creditlimit', 'type' => 'left', 'titre' => 'CLDashboard', 'mainmenu' => 'creditlimit', 'leftmenu' => 'cldashboard', 'url' => '/creditlimit/index.php', 'langs' => 'creditlimit@creditlimit', 'position' => 1271, 'enabled' => 'isModEnabled("creditlimit")', 'perms' => '$user->hasRight("creditlimit", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=creditlimit', 'type' => 'left', 'titre' => 'CLAudit', 'mainmenu' => 'creditlimit', 'leftmenu' => 'claudit', 'url' => '/creditlimit/audit.php', 'langs' => 'creditlimit@creditlimit', 'position' => 1272, 'enabled' => 'isModEnabled("creditlimit")', 'perms' => '$user->hasRight("creditlimit", "audit")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=creditlimit', 'type' => 'left', 'titre' => 'CLBypassUsers', 'mainmenu' => 'creditlimit', 'leftmenu' => 'clbypassusers', 'url' => '/creditlimit/admin/bypass_users.php', 'langs' => 'creditlimit@creditlimit', 'position' => 1273, 'enabled' => 'isModEnabled("creditlimit")', 'perms' => '$user->admin', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=creditlimit', 'type' => 'left', 'titre' => 'CLSetup', 'mainmenu' => 'creditlimit', 'leftmenu' => 'clsetup', 'url' => '/creditlimit/admin/setup.php', 'langs' => 'creditlimit@creditlimit', 'position' => 1274, 'enabled' => 'isModEnabled("creditlimit")', 'perms' => '$user->admin', 'target' => '', 'user' => 0),
		);
	}

	public function init($options = '')
	{
		global $conf;
		if ($this->_load_tables('/creditlimit/sql/') < 0) return -1;
		$result = $this->_init(array(), $options);
		if ($result > 0) {
			$this->db->query('INSERT IGNORE INTO '.$this->db->prefix().'cl_config (entity) VALUES ('.((int) $conf->entity).')');
		}
		return $result;
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
