<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modNamingSeries extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 529450;
		$this->rights_class = 'namingseries';
		$this->family = 'technic';
		$this->name = 'NamingSeries';
		$this->description = 'Flexible document naming series with period-scoped counters';
		$this->version = '0.4.0';
		$this->const_name = 'MAIN_MODULE_NAMINGSERIES';
		$this->picto = 'barcode';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array('modSociete');
		$this->module_parts = array('models' => 1);
		$this->config_page_url = array('setup.php@namingseries');
		$this->langfiles = array('namingseries@namingseries');
		$this->dirs = array();

		$this->rights = array(
			array($this->numero + 1, 'Read naming series', 'r', 1, 'read'),
			array($this->numero + 2, 'Manage naming series', 'w', 0, 'manage'),
			array($this->numero + 3, 'Manage naming series counters', 'w', 0, 'counter'),
			array($this->numero + 4, 'Read naming series audit log', 'r', 0, 'audit'),
		);

		$this->menu = array(
			array('fk_menu' => '', 'type' => 'top', 'titre' => 'NSNamingSeries', 'mainmenu' => 'namingseries', 'leftmenu' => '', 'url' => '/namingseries/index.php', 'langs' => 'namingseries@namingseries', 'position' => 1280, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->hasRight("namingseries", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=namingseries', 'type' => 'left', 'titre' => 'NSSeries', 'mainmenu' => 'namingseries', 'leftmenu' => 'nsseries', 'url' => '/namingseries/index.php', 'langs' => 'namingseries@namingseries', 'position' => 1281, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->hasRight("namingseries", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=namingseries', 'type' => 'left', 'titre' => 'NSCurrentCounter', 'mainmenu' => 'namingseries', 'leftmenu' => 'nscounters', 'url' => '/namingseries/counters.php', 'langs' => 'namingseries@namingseries', 'position' => 1282, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->hasRight("namingseries", "read")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=namingseries', 'type' => 'left', 'titre' => 'NSRenumberDocuments', 'mainmenu' => 'namingseries', 'leftmenu' => 'nsrenumber', 'url' => '/namingseries/renumber.php', 'langs' => 'namingseries@namingseries', 'position' => 1283, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->hasRight("namingseries", "counter")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=namingseries', 'type' => 'left', 'titre' => 'NSAudit', 'mainmenu' => 'namingseries', 'leftmenu' => 'nsaudit', 'url' => '/namingseries/audit.php', 'langs' => 'namingseries@namingseries', 'position' => 1284, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->hasRight("namingseries", "audit")', 'target' => '', 'user' => 0),
			array('fk_menu' => 'fk_mainmenu=namingseries', 'type' => 'left', 'titre' => 'NSSetup', 'mainmenu' => 'namingseries', 'leftmenu' => 'nssetup', 'url' => '/namingseries/admin/setup.php', 'langs' => 'namingseries@namingseries', 'position' => 1285, 'enabled' => 'isModEnabled("namingseries")', 'perms' => '$user->admin', 'target' => '', 'user' => 0),
		);
	}

	public function init($options = '')
	{
		global $conf, $user;
		if ($this->_load_tables('/namingseries/sql/') < 0) {
			return -1;
		}
		$result = $this->_init(array(), $options);
		if ($result >= 0) {
			require_once dol_buildpath('/namingseries/class/namingseries.class.php', 0);
			foreach (NamingSeries::documentTypes() as $type => $definition) {
				$sql = 'INSERT IGNORE INTO '.$this->db->prefix().'ns_series'
					.' (entity,code,label,document_type,pattern,reset_period,start_value,is_default,active,datec,fk_user_creat) VALUES ('
					.((int) $conf->entity).",'".$this->db->escape($definition['code'])."','".$this->db->escape($definition['label'])."','"
					.$this->db->escape($type)."','".$this->db->escape($definition['pattern'])."','".$this->db->escape($definition['reset'])."',1,1,1,'"
					.$this->db->idate(dol_now())."',".(!empty($user->id) ? (int) $user->id : 'NULL').')';
				$this->db->query($sql);
			}
			$this->db->query('UPDATE '.$this->db->prefix()."ns_series SET reset_period='daily' WHERE entity=".((int) $conf->entity)
				." AND code='POS_DAILY' AND pattern='POS-{YYYY}-{MM}-{DD}-{####}' AND reset_period='monthly'");
			$menuTable = $this->db->prefix().'menu';
			$this->db->query("UPDATE ".$menuTable." SET position=1284 WHERE entity=".((int) $conf->entity)." AND module='namingseries' AND url='/namingseries/audit.php'");
			$this->db->query("UPDATE ".$menuTable." SET position=1285 WHERE entity=".((int) $conf->entity)." AND module='namingseries' AND url='/namingseries/admin/setup.php'");
			$check = $this->db->query("SELECT rowid FROM ".$menuTable." WHERE entity=".((int) $conf->entity)." AND module='namingseries' AND url='/namingseries/counters.php'");
			if ($check && $this->db->num_rows($check) === 0) {
				$this->db->query("INSERT INTO ".$menuTable." (menu_handler,entity,module,type,mainmenu,leftmenu,fk_menu,fk_mainmenu,position,url,target,titre,langs,perms,enabled,usertype) VALUES ("
					."'all',".((int) $conf->entity).",'namingseries','left','namingseries','nscounters',-1,'namingseries',1282,'/namingseries/counters.php','','NSCurrentCounter','namingseries@namingseries','\$user->hasRight(\"namingseries\", \"read\")','isModEnabled(\"namingseries\")',0)");
			}
			$check = $this->db->query("SELECT rowid FROM ".$menuTable." WHERE entity=".((int) $conf->entity)." AND module='namingseries' AND url='/namingseries/renumber.php'");
			if ($check && $this->db->num_rows($check) === 0) {
				$this->db->query("INSERT INTO ".$menuTable." (menu_handler,entity,module,type,mainmenu,leftmenu,fk_menu,fk_mainmenu,position,url,target,titre,langs,perms,enabled,usertype) VALUES ("
					."'all',".((int) $conf->entity).",'namingseries','left','namingseries','nsrenumber',-1,'namingseries',1283,'/namingseries/renumber.php','','NSRenumberDocuments','namingseries@namingseries','\$user->hasRight(\"namingseries\", \"counter\")','isModEnabled(\"namingseries\")',0)");
			}
			$this->db->query("UPDATE ".$menuTable." SET titre='NSRenumberDocuments' WHERE entity=".((int) $conf->entity)." AND module='namingseries' AND url='/namingseries/renumber.php'");
		}
		return $result;
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
