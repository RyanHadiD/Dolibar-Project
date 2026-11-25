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
		$this->version = '1.7.0';
		$this->const_name = 'MAIN_MODULE_PERPETUALINVENTORY';
		$this->picto = 'stock';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(24, 0);
		$this->depends = array('modStock', 'modAccounting', 'modReception', 'modExpedition');
		$this->module_parts = array('triggers' => 1, 'hooks' => array('invoicecard', 'invoicesuppliercard', 'expeditioncard', 'expeditionlist', 'receptioncard', 'receptionlist', 'ordercard', 'ordersuppliercard', 'paymentcard', 'supplierpaymentcard', 'bookkeepingcard'));
		$this->config_page_url = array('setup.php@perpetualinventory');
		$this->langfiles = array('perpetualinventory@perpetualinventory');
		$this->dirs = array();
		$this->rights = array();
		foreach (array('read' => 'Read stock ledger and reconciliation', 'write' => 'Post stock adjustments and transfers', 'match' => 'Match supplier invoices to receipts', 'financial' => 'Read confidential financial reports') as $key => $label) {
			$i = count($this->rights);
			$this->rights[$i] = array($this->numero + $i + 1, $label, 'w', 0, $key);
		}
		$this->menu = array();
		foreach (array('index.php' => 'PIDashboard', 'matching.php' => 'PIMatching', 'reconcile.php' => 'PIReconcile', 'audit.php' => 'Riwayat Cancel/Revisi', 'admin/setup.php' => 'PISetup') as $url => $title) {
			$i = count($this->menu);
			$this->menu[] = array('fk_menu' => $i ? 'fk_mainmenu=perpetualinventory' : '', 'type' => $i ? 'left' : 'top', 'titre' => $title, 'mainmenu' => 'perpetualinventory', 'leftmenu' => 'pi'.$i, 'url' => '/perpetualinventory/'.$url, 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1100 + $i, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		}
		$this->menu[] = array('fk_menu' => '', 'type' => 'top', 'titre' => 'PIStockEntry', 'mainmenu' => 'pistockentry', 'leftmenu' => '', 'url' => '/perpetualinventory/stockentry_list.php', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1125, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		$this->menu[] = array('fk_menu' => 'fk_mainmenu=pistockentry', 'type' => 'left', 'titre' => 'PIStockEntryList', 'mainmenu' => 'pistockentry', 'leftmenu' => 'pistockentrylist', 'url' => '/perpetualinventory/stockentry_list.php', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1126, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "read")', 'target' => '', 'user' => 0);
		$this->menu[] = array('fk_menu' => 'fk_mainmenu=pistockentry', 'type' => 'left', 'titre' => 'PIStockEntryNew', 'mainmenu' => 'pistockentry', 'leftmenu' => 'pistockentrynew', 'url' => '/perpetualinventory/stockentry_card.php?action=create', 'langs' => 'perpetualinventory@perpetualinventory', 'position' => 1127, 'enabled' => 'isModEnabled("perpetualinventory")', 'perms' => '$user->hasRight("perpetualinventory", "write")', 'target' => '', 'user' => 0);
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
		if ($this->installPpnLineExtraFields() < 0) {
			return -1;
		}
		return $this->_init(array(), $options);
	}

	/**
	 * Install the PPN information carried by Shipment and Customer Invoice lines.
	 *
	 * The same technical names are deliberately used on both line types. This
	 * lets Dolibarr copy them in its native "create from Shipment" workflow and
	 * keeps custom import paths consistent with the native one.
	 *
	 * @return int <0 on error, >0 on success
	 */
	private function installPpnLineExtraFields()
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

		$extrafields = new ExtraFields($this->db);
		$definitions = array(
			array('pi_ppn_name', 'Nama PPN', 'varchar', 210, '255', array('css' => 'minwidth200')),
			array('pi_ppn_description', 'Deskripsi PPN', 'text', 220, '', array('css' => 'minwidth300')),
			array('pi_ppn_qty', 'Qty PPN', 'double', 230, '24,8', array('css' => 'width100 right')),
			array('pi_ppn_note', 'Catatan', 'text', 240, '', array('css' => 'minwidth300')),
			array('pi_ppn_uom', 'UOM PPN', 'varchar', 250, '50', array('css' => 'width100')),
		);

		foreach (array('expeditiondet', 'facturedet') as $elementType) {
			foreach ($definitions as $definition) {
				$result = $extrafields->addExtraField(
					$definition[0],
					$definition[1],
					$definition[2],
					$definition[3],
					$definition[4],
					$elementType,
					0,
					0,
					'',
					'',
					0,
					'',
					'1',
					'',
					'',
					'',
					'',
					'1',
					0,
					1,
					$definition[5]
				);
				if ($result < 0) {
					$this->error = 'Gagal membuat field '.$definition[1].' pada '.$elementType.': '.$extrafields->error;
					return -1;
				}
			}
		}

		return 1;
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
