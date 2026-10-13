<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modPerpetualInventory extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 529410;
		$this->rights_class = 'perpetualinventory';
		$this->family = 'financial';
		$this->name = 'PerpetualInventory';
		$this->description = 'Perpetual inventory with moving-average valuation and linked accounting';
		$this->version = '1.3.0';
		$this->const_name = 'MAIN_MODULE_PERPETUALINVENTORY';
		$this->picto = 'stock';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array('modStock', 'modAccounting', 'modReception', 'modExpedition');
		$this->module_parts = array('triggers' => 1, 'hooks' => array('invoicecard', 'invoicesuppliercard', 'expeditioncard', 'expeditionlist', 'receptioncard', 'receptionlist', 'ordercard', 'ordersuppliercard', 'paymentcard', 'supplierpaymentcard'));
		$this->config_page_url = array('setup.php@perpetualinventory');
		$this->langfiles = array('perpetualinventory@perpetualinventory');
		$this->dirs = array();
		$this->rights = array();
		foreach (array('read' => 'Read stock ledger and reconciliation', 'write' => 'Post stock adjustments and transfers', 'match' => 'Match supplier invoices to receipts', 'financial' => 'Read confidential financial reports') as $key => $label) {
			$i = count($this->rights);
			$this->rights[$i] = array($this->numero + $i + 1, $label, 'w', 0, $key);
		}
		$this->menu = array();
		foreach (array('index.php' => 'PIDashboard', 'operations.php' => 'PIOperations', 'matching.php' => 'PIMatching', 'reconcile.php' => 'PIReconcile', 'audit.php' => 'Riwayat Cancel/Revisi', 'admin/setup.php' => 'PISetup') as $url => $title) {
			$i = count($this->menu);
			$this->menu[] = array('fk_menu' => $i ? 'fk_mainmenu=perpetualinventory' : '', 'type' => $i ? 'left' : 'top', 'titre' => $title, 'mainmenu' => 'perpetualinventory', 'leftmenu' => 'pi'.$i, 'url' => '/perpetualinventory/'.$url, 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1100 + $i, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		}
		$this->menu[] = array('fk_menu' => '', 'type' => 'top', 'titre' => 'PIStockReports', 'mainmenu' => 'pistockreports', 'leftmenu' => '', 'url' => '/perpetualinventory/ledger.php', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1150, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		foreach (array('ledger.php' => 'PILedger', 'stockbalance.php' => 'PIStockBalance') as $url => $title) {
			$i = count($this->menu);
			$this->menu[] = array('fk_menu' => 'fk_mainmenu=pistockreports', 'type' => 'left', 'titre' => $title, 'mainmenu' => 'pistockreports', 'leftmenu' => 'pistockreports'.$i, 'url' => '/perpetualinventory/'.$url, 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1151 + $i, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		}
		$this->menu[] = array('fk_menu' => 'fk_mainmenu=products,fk_leftmenu=sendings', 'type' => 'left', 'titre' => 'PIGetItemsFromSalesOrder', 'mainmenu' => 'products', 'leftmenu' => 'sendings', 'url' => '/perpetualinventory/get_items_from.php?target=shipment', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 102, 'enabled' => 'isModEnabled("perpetualinventory") && isModEnabled("shipping")', 'perms' => '$user->hasRight("expedition", "creer")', 'target' => '', 'user' => 0);
		$this->menu[] = array('fk_menu' => 'fk_mainmenu=products,fk_leftmenu=receptions', 'type' => 'left', 'titre' => 'PIGetItemsFromPurchaseOrder', 'mainmenu' => 'products', 'leftmenu' => 'receptions', 'url' => '/perpetualinventory/get_items_from.php?target=reception', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 102, 'enabled' => 'isModEnabled("perpetualinventory") && isModEnabled("reception")', 'perms' => '$user->hasRight("reception", "creer")', 'target' => '', 'user' => 0);
		$this->menu[] = array('fk_menu' => '', 'type' => 'top', 'titre' => 'PIFinancialReports', 'mainmenu' => 'pifinancial', 'leftmenu' => '', 'url' => '/perpetualinventory/profitloss.php', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1200, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "financial")', 'target' => '', 'user' => 0);
		$financialReports = array(
			'generalledger.php' => array('PIGeneralLedger', 'pifinancialgl'),
			'profitloss.php' => array('PIProfitLoss', 'pifinancialpl'),
			'balancesheet.php' => array('PIBalanceSheet', 'pifinancialbs'),
			'grossprofit.php' => array('PIGrossProfit', 'pifinancialgp'),
			'receivablepayable.php' => array('PIReceivablePayable', 'pifinancialrp'),
		);
		$financialPosition = 1201;
		foreach ($financialReports as $url => $definition) {
			$this->menu[] = array('fk_menu' => 'fk_mainmenu=pifinancial', 'type' => 'left', 'titre' => $definition[0], 'mainmenu' => 'pifinancial', 'leftmenu' => $definition[1], 'url' => '/perpetualinventory/'.$url, 'langs' => 'perpetualinventory@perpetualinventory', 'position' => $financialPosition++, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "financial")', 'target' => '', 'user' => 0);
		}
		$this->menu[] = array('fk_menu' => '', 'type' => 'top', 'titre' => 'PIDailyReport', 'mainmenu' => 'pidailyreport', 'leftmenu' => '', 'url' => '/perpetualinventory/dailyreport.php?report=ar-summary', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1250, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "financial")', 'target' => '', 'user' => 0);
		$dailyReports = array('ar-summary' => 'PIAccountsReceivableSummary', 'ap-summary' => 'PIAccountsPayableSummary', 'sales-register' => 'PISalesRegister', 'purchase-register' => 'PIPurchaseRegister');
		foreach ($dailyReports as $report => $title) {
			$i = count($this->menu);
			$this->menu[] = array('fk_menu' => 'fk_mainmenu=pidailyreport', 'type' => 'left', 'titre' => $title, 'mainmenu' => 'pidailyreport', 'leftmenu' => 'pidailyreport'.$i, 'url' => '/perpetualinventory/dailyreport.php?report='.$report, 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1251 + $i, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "financial")', 'target' => '', 'user' => 0);
		}
	}

	public function init($options = '')
	{
		if ($this->_load_tables('/perpetualinventory/sql/') < 0) {
			return -1;
		}
		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		// Preserve ledgers. Refuse deactivation while automatic posting is enabled.
		global $conf;
		$r = $this->db->query('SELECT enabled FROM '.$this->db->prefix().'pi_config WHERE entity = '.((int) $conf->entity));
		if ($r && ($c = $this->db->fetch_object($r)) && $c->enabled) {
			$this->error = 'Posting perpetual aktif. Penonaktifan memerlukan migrasi saldo agar stok dan akuntansi tidak terputus.';
			return -1;
		}
		return $this->_remove(array(), $options);
	}
}
