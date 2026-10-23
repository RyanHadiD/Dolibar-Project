<?php
/** CLI only. Creates EMPTY, randomly prefixed fixture tables, and drops only those tables. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
define('NOSESSION', 1);
require dirname(__DIR__, 3).'/master.inc.php';
require_once __DIR__.'/../class/perpetualinventory.class.php';
require_once __DIR__.'/../class/pistockentry.class.php';
require_once __DIR__.'/../class/pifinancialreport.class.php';
require_once __DIR__.'/../class/pistockreport.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

if ($db->type !== 'mysqli') {
	throw new RuntimeException('Integration fixture runner requires MariaDB/MySQL.');
}
$sourceDb = $db;
$fixturePrefix = 'pitest_'.bin2hex(random_bytes(5)).'_';
class PiFixtureDB extends DoliDBMysqli
{
	public $fixturePrefix;
	public $failJournalInsert = 0;
	public function query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		if ($this->fixturePrefix) {
			$query = str_replace(MAIN_DB_PREFIX, $this->fixturePrefix, $query);
			if ($this->failJournalInsert && stripos($query, 'INSERT INTO '.$this->fixturePrefix.'accounting_bookkeeping ') !== false) {
				$this->failJournalInsert--;
				if (!$this->failJournalInsert) {
					$this->error = 'Injected journal failure';
					return false;
				}
			}
		}
		return parent::query($query, $usesavepoint, $type, $result_mode);
	}
}
$db = new PiFixtureDB('mysqli', $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port);
$db->fixturePrefix = $fixturePrefix;
$db->prefix_db = $fixturePrefix;
$tables = array();
$checks = 0;
function check($condition, $label)
{
	global $checks;
	if (!$condition) {
		throw new RuntimeException('FAIL: '.$label);
	}
	$checks++;
}
function near($a, $b, $label)
{
	check(abs($a - $b) < 0.000001, $label.' ['.$a.' / '.$b.']');
}
function fails($callback, $label)
{
	try {
		$callback();
	} catch (DomainException $e) {
		check(true, $label);
		return;
	}
	throw new RuntimeException('Expected rejection: '.$label);
}
try {
	// No customer rows are copied. The driver rewrites even core MAIN_DB_PREFIX queries.
	foreach (array('product', 'product_extrafields', 'product_stock', 'product_batch', 'product_lot', 'product_association', 'entrepot', 'entrepot_extrafields', 'stock_mouvement', 'accounting_account', 'accounting_system', 'accounting_journal', 'accounting_bookkeeping', 'accounting_fiscalyear', 'accounting_bookkeeping_piece', 'facture_fourn', 'facture_fourn_det', 'facture', 'facturedet', 'commande', 'commandedet', 'reception', 'expedition', 'expeditiondet', 'societe', 'user', 'extrafields', 'element_element', 'events', 'const', 'c_units', 'c_country', 'c_revenuestamp', 'overwrite_trans') as $name) {
		$target = $fixturePrefix.$name;
		if (!$sourceDb->query('CREATE TABLE '.$target.' LIKE '.MAIN_DB_PREFIX.$name)) {
			throw new RuntimeException('Cannot create empty fixture '.$name.': '.$sourceDb->lasterror());
		}
		$tables[] = $target;
	}
	foreach (glob(__DIR__.'/../sql/*.sql') as $path) {
		$sql = str_replace('llx_', $fixturePrefix, file_get_contents($path));
		if (!$db->query($sql)) {
			throw new RuntimeException($db->lasterror());
		}
		$tables[] = $fixturePrefix.basename($path, '.sql');
		$tables[count($tables) - 1] = str_replace($fixturePrefix.'llx_', $fixturePrefix, end($tables));
	}
	// Runtime-only configuration. Nothing is persisted in the live configuration.
	foreach (get_object_vars($conf) as $module => $value) {
		if (is_object($value) && property_exists($value, 'enabled')) {
			$value->enabled = 0;
		}
	}
	foreach (array('stock', 'accounting', 'perpetualinventory', 'product') as $module) {
		$conf->$module = (object) array('enabled' => 1);
	}
	$conf->modules = array_fill_keys(array('stock', 'accounting', 'perpetualinventory', 'product'), 1);
	$conf->entity = 1;
	$conf->modules_parts['triggers'] = array('/perpetualinventory/core/triggers');
	$conf->modules_parts['hooks'] = array();
	$conf->global->STOCK_CALCULATE_ON_RECEPTION = 1;
	$conf->global->STOCK_CALCULATE_ON_SHIPMENT = 1;
	foreach (array('STOCK_CALCULATE_ON_BILL', 'STOCK_CALCULATE_ON_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_SHIPMENT_CLOSE', 'STOCK_CALCULATE_ON_SUPPLIER_BILL', 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_RECEPTION_CLOSE', 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH', 'PRODUIT_SOUSPRODUITS', 'STOCK_DISALLOW_NEGATIVE_TRANSFER', 'SYSLOG_FILE', 'MAIN_LOGEVENTS_USER_LOGIN') as $key) {
		$conf->global->$key = 0;
	}
	$conf->global->ACCOUNTANCY_FISCAL_PERIOD_MODE = 'blockedonclosed';
	$conf->global->BOOKKEEPING_ADDON = 'mod_bookkeeping_neon';
	$conf->cache = array();
	$conf->global->MAIN_MAX_DECIMALS_TOT = 2;
	$conf->global->CHARTOFACCOUNTS = 1;
	$hookmanager = new HookManager($db);
	$user = new User($db);
	$user->id = 1;
	$user->admin = 1;
	$user->rights->perpetualinventory = (object) array('read' => 1, 'write' => 1, 'match' => 1);
	$pi = new PerpetualInventory($db, 1);
	$pi->q('INSERT INTO '.$pi->table('accounting_system')." (rowid,pcg_version,label,active) VALUES (1,'TEST','Fixture',1)");
	foreach (array('1000', '1100', '2000', '2100', '5000', '5001', '5100', '3000') as $n) {
		$pi->q('INSERT INTO '.$pi->table('accounting_account')." (entity,fk_pcg_version,pcg_type,account_number,label,active) VALUES (1,'TEST','TEST',".$pi->str($n).",'Fixture',1)");
	}
	$pi->q('INSERT INTO '.$pi->table('accounting_journal')." (entity,code,label,active) VALUES (1,'PI','Inventory',1)");
	$pi->q('INSERT INTO '.$pi->table('product')." (rowid,entity,ref,label,fk_product_type,stockable_product,tobatch) VALUES (1,1,'TEST-A','Fixture A',0,1,0),(2,2,'OTHER-ENTITY','Other',0,1,0),(10,1,'ROLLBACK','Rollback fixture',0,1,0)");
	$pi->q('INSERT INTO '.$pi->table('entrepot')." (rowid,entity,ref,statut) VALUES (1,1,'MAIN',1),(2,1,'SECOND',1),(3,2,'OTHER',1)");
	$pi->q('INSERT INTO '.$pi->table('reception')." (rowid,entity,ref,fk_soc) VALUES (1,1,'REC-TEST',1),(10,1,'REC-ROLLBACK',1),(11,1,'REC-AFTER',1)");
	$pi->q('INSERT INTO '.$pi->table('expedition')." (rowid,entity,ref,fk_soc) VALUES (1,1,'SHIP-TEST',1)");
	$pi->saveConfig(array('stock_account' => '1000', 'grni_account' => '2000', 'cogs_account' => '5000', 'adjustment_account' => '5100', 'opening_account' => '3000', 'journal_code' => 'PI', 'lock_date' => ''), $user);
	$pi->start($user);
	$pi->q('UPDATE '.$pi->table('pi_config').' SET start_date=DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY) WHERE entity=1');
	$pi->q('UPDATE '.$pi->table('reception').' SET date_reception=DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) WHERE rowid=1');
	$pi->q('UPDATE '.$pi->table('expedition').' SET date_expedition=DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) WHERE rowid=1');
	check($pi->config()->enabled == 1, 'Start only with reconciled balance');
	fails(function () use ($pi, $user) { $pi->recordDocumentAction('shipment', 1, 'SHIP-TEST', 'amend', 'no', $user); }, 'Document action requires a meaningful reason');
	$pi->recordDocumentAction('shipment', 1, 'SHIP-TEST', 'amend', 'Correction requested by warehouse', $user);
	check($pi->one('SELECT reason FROM '.$pi->table('pi_document_action').' WHERE element_type='.$pi->str('shipment').' AND fk_element=1')->reason === 'Correction requested by warehouse', 'Document action reason is persisted');
	$pi->recordDocumentAction('accounting_journal', 77, 'JRN-TEST', 'amend', 'Correction requested by accounting', $user, '{"version":1}');
	check($pi->one('SELECT snapshot_json FROM '.$pi->table('pi_document_action').' WHERE element_type='.$pi->str('accounting_journal').' AND fk_element=77')->snapshot_json === '{"version":1}', 'Accounting journal revision snapshot is accepted by audit');
	function nativeMovement($qty, $cost, $origin = 'reception', $product = 1, $warehouse = 1, $originId = 1)
	{
		global $db, $user;
		$m = new MouvementStock($db);
		if ($origin !== '') {
			$m->setOrigin($origin, $originId);
		}
		$id = $m->_create($user, $product, $warehouse, $qty, $qty > 0 ? 3 : 2, $cost, 'Fixture movement');
		if ($id <= 0) {
			throw new DomainException('Native stock failed: '.$m->error.' '.implode('; ', $m->errors));
		}
		return $m;
	}
	$m = nativeMovement(10, 100000);
	$r = $pi->reconciliation();
	near($r['ledger'], 1000000, 'Receipt ledger');
	near($r['gl'], 1000000, 'Receipt GL');
	check(!$r['quantity_errors'], 'Native stock equals ledger');
	$first = $pi->one('SELECT * FROM '.$pi->table('pi_entry').' WHERE fk_movement='.(int) $m->id);
	check(substr($first->date_post, 0, 10) === date('Y-m-d', strtotime('-1 day')), 'Receipt stock ledger uses Reception date');
	$receiptJournal = $pi->one('SELECT doc_type,doc_ref,fk_doc,fk_docdet,label_operation,doc_date FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='reception' AND fk_docdet=".(int) $first->rowid.' ORDER BY rowid LIMIT 1');
	check($receiptJournal->doc_type === 'reception' && $receiptJournal->doc_ref === 'REC-TEST', 'Receipt journal uses Reception voucher identity');
	check(substr($receiptJournal->doc_date, 0, 10) === date('Y-m-d', strtotime('-1 day')), 'Receipt GL uses Reception date');
	check($receiptJournal->fk_doc == 1 && $receiptJournal->fk_docdet == $first->rowid, 'Receipt voucher links document and stock ledger detail');
	check(strpos($receiptJournal->label_operation, '[PI-'.$first->rowid.']') !== false, 'Receipt journal retains PI audit id');
	$pi->transaction(function () use ($pi, $m, $user) { $pi->onMovement($m, $user); });
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n, 1, 'Duplicate movement idempotent');
	$pi->q('UPDATE '.$pi->table('product')." SET accountancy_code_buy='5001' WHERE rowid=1");
	$shipment = nativeMovement(-3, 150000, 'shipping');
	$r = $pi->reconciliation();
	near($r['ledger'], 700000, 'Shipment uses cost, not selling price');
	near($r['difference'], 0, 'Shipment reconciliation');
	$delivery = $pi->one('SELECT * FROM '.$pi->table('pi_entry').' WHERE fk_movement='.(int) $shipment->id);
	check(substr($delivery->date_post, 0, 10) === date('Y-m-d', strtotime('-1 day')), 'Shipment stock ledger uses Shipment date');
	check($delivery->contra_account === '5001', 'Shipment uses product purchase accounting code as COGS');
	near($pi->one('SELECT SUM(debit) amount FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='shipment' AND fk_docdet=".(int) $delivery->rowid." AND numero_compte='5001'")->amount, 300000, 'Product COGS account receives shipment value');
	$shipmentJournal = $pi->one('SELECT doc_type,doc_ref,fk_doc,fk_docdet,doc_date FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='shipment' AND fk_docdet=".(int) $delivery->rowid.' ORDER BY rowid LIMIT 1');
	check($shipmentJournal->doc_type === 'shipment' && $shipmentJournal->doc_ref === 'SHIP-TEST', 'Shipment journal uses Shipment voucher identity');
	check(substr($shipmentJournal->doc_date, 0, 10) === date('Y-m-d', strtotime('-1 day')), 'Shipment GL uses Shipment date');
	check($shipmentJournal->fk_doc == 1 && $shipmentJournal->fk_docdet == $delivery->rowid, 'Shipment voucher links document and stock ledger detail');
	$pi->unvalidateShipment((object) array('id' => 1, 'ref' => 'SHIP-TEST'), $user);
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry')." WHERE origin_type='shipping' AND origin_id=1")->n, 0, 'Shipment cancellation deletes PI ledger rows');
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='shipment' AND fk_doc=1")->n, 0, 'Shipment cancellation deletes GL rows');
	near($pi->balance(1, 1)->qty, 10, 'Shipment cancellation restores PI quantity');
	near($pi->one('SELECT reel FROM '.$pi->table('product_stock').' WHERE fk_product=1 AND fk_entrepot=1')->reel, 10, 'Shipment cancellation restores native quantity');
	$shipment = nativeMovement(-3, 150000, 'shipping');
	$delivery = $pi->one('SELECT * FROM '.$pi->table('pi_entry').' WHERE fk_movement='.(int) $shipment->id);
	$receiptRollback = nativeMovement(5, 25000, 'reception', 10, 1, 10);
	$laterReceipt = nativeMovement(2, 40000, 'reception', 10, 1, 11);
	nativeMovement(-5, 0, 'reception', 10, 1, 10);
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry')." WHERE origin_type='reception' AND origin_id=10")->n, 0, 'Reception cancellation deletes PI ledger rows');
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='reception' AND fk_doc=10")->n, 0, 'Reception cancellation deletes GL rows');
	$rolledBackStock = $pi->one('SELECT reel FROM '.$pi->table('product_stock').' WHERE fk_product=10 AND fk_entrepot=1');
	near($rolledBackStock ? $rolledBackStock->reel : 0, 2, 'Historical Reception cancellation preserves later native quantity');
	near($pi->balance(10, 1)->qty, 2, 'Historical Reception cancellation replays later PI quantity');
	near($pi->balance(10, 1)->stock_value, 80000, 'Historical Reception cancellation replays later valuation');
	nativeMovement(-2, 0, 'reception', 10, 1, 11);
	near($pi->balance(10, 1)->qty, 0, 'Later Reception fixture cleanup restores zero quantity');
	$pi->q('INSERT INTO '.$pi->table('societe')." (rowid,entity,nom) VALUES (1,1,'Fixture Customer')");
	$pi->q('INSERT INTO '.$pi->table('commande')." (rowid,entity,ref,fk_soc,date_commande,fk_statut) VALUES (1,1,'SO-TEST',1,CURRENT_DATE,1)");
	$pi->q('INSERT INTO '.$pi->table('commandedet')." (rowid,fk_commande,fk_product,qty,subprice,total_ht,product_type) VALUES (1,1,1,3,200000,600000,0)");
	$pi->q('INSERT INTO '.$pi->table('expeditiondet')." (rowid,fk_expedition,fk_elementdet,element_type,fk_product,qty,fk_entrepot) VALUES (1,1,1,'commande',1,3,1)");
	$pi->q('INSERT INTO '.$pi->table('facture')." (rowid,entity,ref,fk_soc,datef,fk_statut,type) VALUES (1,1,'INV-CUSTOMER-TEST',1,CURRENT_DATE,1,0)");
	$pi->q('INSERT INTO '.$pi->table('facturedet')." (rowid,fk_facture,fk_product,qty,subprice,total_ht,product_type) VALUES (1,1,1,3,200000,600000,0)");
	$pi->q('INSERT INTO '.$pi->table('element_element')." (sourcetype,targettype,fk_source,fk_target) VALUES ('shipping','facture',1,1)");
	$financial = new PiFinancialReport($pi);
	$reportStart = date('Y-m-01');
	$reportEnd = date('Y-m-t');
	$profitLoss = $financial->profitLoss($reportStart, $reportEnd);
	near(array_sum($profitLoss['totals']['expense']), 300000, 'Profit and Loss includes product COGS');
	near(array_sum($profitLoss['net']), -300000, 'Profit and Loss calculates period loss');
	$balanceSheet = $financial->balanceSheet($reportStart, $reportEnd);
	$reportPeriod = array_key_last($balanceSheet['periods']);
	near($balanceSheet['totals']['asset'][$reportPeriod], 700000, 'Balance Sheet accumulates inventory asset');
	near($balanceSheet['totals']['liability'][$reportPeriod], 1000000, 'Balance Sheet accumulates GRNI liability');
	near($balanceSheet['profit_loss'][$reportPeriod], -300000, 'Balance Sheet includes fiscal-year provisional loss');
	near($balanceSheet['difference'][$reportPeriod], 0, 'Balance Sheet equation balances');
	$grossProfit = $financial->grossProfit($reportStart, $reportEnd);
	check(count($grossProfit) === 1 && $grossProfit[0]->sales_invoice === 'INV-CUSTOMER-TEST' && $grossProfit[0]->delivery_note === 'SHIP-TEST', 'Gross Profit links invoice and delivery note');
	near($grossProfit[0]->selling_amount, 600000, 'Gross Profit uses average invoice selling rate');
	near($grossProfit[0]->buying_amount, 300000, 'Gross Profit uses actual perpetual shipment cost');
	near($grossProfit[0]->gross_profit, 300000, 'Gross Profit calculates item margin');
	$dayBeforeReport = (new DateTimeImmutable($reportStart))->modify('-1 day')->format('Y-m-d');
	$bookkeepingTable = $pi->table('accounting_bookkeeping');
	$baseBookkeeping = " (entity,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,thirdparty_code,subledger_account,subledger_label,numero_compte,label_compte,debit,credit,fk_user_author,code_journal) VALUES ";
	$pi->q('INSERT INTO '.$bookkeepingTable.$baseBookkeeping."(1,901,'".$dayBeforeReport."','fixture','AR-OPEN',901,901,'CUST-A','CUST-A','Customer A','1100','Receivable',100,0,1,'PI'),(1,902,'".$reportStart."','fixture','AR-MOVE',902,902,'CUST-A','CUST-A','Customer A','1100','Receivable',50,20,1,'PI')");
	$pi->q('INSERT INTO '.$bookkeepingTable.$baseBookkeeping."(1,903,'".$dayBeforeReport."','fixture','AP-OPEN',903,903,'SUP-A','SUP-A','Supplier A','2100','Payable',0,200,1,'PI'),(1,904,'".$reportStart."','fixture','AP-MOVE',904,904,'SUP-A','SUP-A','Supplier A','2100','Payable',25,75,1,'PI')");
	$receivables = $financial->receivablePayable('receivable', '1100', $reportStart, $reportEnd);
	check(count($receivables) === 1 && $receivables[0]->party_name === 'Customer A', 'Receivables grouped by customer auxiliary account');
	near($receivables[0]->opening_balance, 100, 'Receivables opening balance');
	near($receivables[0]->increases, 50, 'Receivables debit additions');
	near($receivables[0]->reductions, 20, 'Receivables credit reductions');
	near($receivables[0]->closing_balance, 130, 'Receivables closing balance');
	$payables = $financial->receivablePayable('payable', '2100', $reportStart, $reportEnd);
	check(count($payables) === 1 && $payables[0]->party_name === 'Supplier A', 'Payables grouped by supplier auxiliary account');
	near($payables[0]->opening_balance, 200, 'Payables opening balance');
	near($payables[0]->increases, 75, 'Payables credit additions');
	near($payables[0]->reductions, 25, 'Payables debit reductions');
	near($payables[0]->closing_balance, 250, 'Payables closing balance');
	check(isset($financial->thirdPartyAccounts('receivable')['1100']), 'Receivable account is selectable');
	check(isset($financial->thirdPartyAccounts('payable')['2100']), 'Payable account is selectable');
	$generalLedger = $financial->generalLedger($reportStart, $reportEnd, array('account' => '1100', 'group_by' => 'none', 'show_opening' => true));
	near($generalLedger['opening'], 100, 'General Ledger opening balance respects account filter');
	near($generalLedger['debit'], 50, 'General Ledger period debit');
	near($generalLedger['credit'], 20, 'General Ledger period credit');
	near($generalLedger['closing'], 130, 'General Ledger running closing balance');
	check(count($generalLedger['rows']) === 2 && $generalLedger['rows'][0]->is_opening, 'General Ledger opening and movement rows');
	near($generalLedger['rows'][1]->balance, 130, 'General Ledger final row balance');
	$pi->q('INSERT INTO '.$bookkeepingTable.$baseBookkeeping."(1,905,CURRENT_DATE,'customer_invoice','INV-CUSTOMER-TEST',1,1,NULL,NULL,NULL,'1100','Receivable',600000,0,1,'PI'),(1,905,CURRENT_DATE,'customer_invoice','INV-CUSTOMER-TEST',1,1,NULL,NULL,NULL,'5000','Sales',0,600000,1,'PI')");
	$revisionInvoice = new class {
		public $element = 'facture';
		public $id = 1;
		public $status = 1;
		public $error = '';
		public $errors = array();
		public function getSommePaiement() { return 0; }
		public function is_last_in_cycle() { return true; }
		public function setDraft($user, $warehouse) { $this->status = 0; return 1; }
	};
	$pi->reviseInvoiceWithLedger($revisionInvoice, $user);
	near($pi->one('SELECT COUNT(*) n FROM '.$bookkeepingTable." WHERE doc_type='customer_invoice' AND fk_doc=1")->n, 0, 'Invoice revision removes transferred bookkeeping');
	check($revisionInvoice->status === 0, 'Invoice revision returns document to draft');
	$before = $pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n;
	fails(function () { nativeMovement(-100, 0, 'shipping'); }, 'Negative stock rejected');
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n, $before, 'Negative stock rolled back');
	near($pi->one('SELECT reel FROM '.$pi->table('product_stock').' WHERE fk_product=1 AND fk_entrepot=1')->reel, 7, 'Native quantity rolled back');
	$key = bin2hex(random_bytes(16));
	$pi->operate('transfer', 1, 1, 2, null, 2, 0, 'Transfer fixture', $key, $user);
	$pi->operate('transfer', 1, 1, 2, null, 2, 0, 'Transfer fixture', $key, $user);
	near($pi->balance(1, 2)->qty, 2, 'Transfer idempotent');
	near($pi->reconciliation()['difference'], 0, 'Transfer preserves total GL');
	$stockReport = new PiStockReport($pi);
	$stockRows = $stockReport->stockBalance(date('Y-m-01'), date('Y-m-t'));
	check(count($stockRows) === 2, 'Stock Balance groups by item and warehouse');
	near(array_sum(array_map(function ($row) { return (float) $row->balance_qty; }, $stockRows)), 7, 'Stock Balance closing quantity');
	near(array_sum(array_map(function ($row) { return (float) $row->balance_value; }, $stockRows)), 700000, 'Stock Balance closing value');
	$warehouseBalance = $stockReport->stockBalance(date('Y-m-01'), date('Y-m-t'), 1, 2);
	check(count($warehouseBalance) === 1 && $warehouseBalance[0]->warehouse_ref === 'SECOND', 'Stock Balance item and warehouse filters');
	near($warehouseBalance[0]->balance_qty, 2, 'Stock Balance filtered quantity');
	$stockLedger = $stockReport->ledger(date('Y-m-01'), date('Y-m-t'), 1, 1);
	check(count($stockLedger) === 3 && $stockLedger[0]->voucher_ref === 'REC-TEST', 'Stock Ledger chronology and voucher reference');
	$batchKey = bin2hex(random_bytes(16));
	$beforeMain = (float) $pi->balance(1, 1)->qty;
	$beforeSecond = (float) $pi->balance(1, 2)->qty;
	$batchItems = array(
		array('product' => 1, 'warehouse' => 1, 'target' => 2, 'qty' => 1),
		array('product' => 1, 'warehouse' => 2, 'target' => 1, 'qty' => 1),
	);
	$batchIds = $pi->operateBatch('material_transfer', $batchItems, 'Round-trip multi item', $batchKey, $user);
	check(count($batchIds) === 2, 'Multi-item Stock Entry posts every row');
	near($pi->balance(1, 1)->qty, $beforeMain, 'Multi-item transfer preserves first warehouse balance');
	near($pi->balance(1, 2)->qty, $beforeSecond, 'Multi-item transfer preserves second warehouse balance');
	$entriesAfterBatch = (int) $pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n;
	$pi->operateBatch('material_transfer', $batchItems, 'Round-trip multi item', $batchKey, $user);
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n, $entriesAfterBatch, 'Multi-item Stock Entry retry is idempotent');
	fails(function () use ($pi, $user) {
		$pi->operateBatch('material_transfer', array(
			array('product' => 1, 'warehouse' => 1, 'target' => 2, 'qty' => 1),
			array('product' => 1, 'warehouse' => 2, 'target' => 2, 'qty' => 1),
		), 'Rollback multi item', bin2hex(random_bytes(16)), $user);
	}, 'Invalid row rolls back whole multi-item Stock Entry');
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n, $entriesAfterBatch, 'Atomic multi-item rollback leaves no partial ledger');
	$stockEntryService = new PiStockEntry($pi);
	$maskTimestamp = dol_mktime(12, 0, 0, 5, 6, 2025);
	check(PiStockEntry::formatReference('ST-{YYYY}{MM}{DD}-{ID:5}', 12, $maskTimestamp) === 'ST-20250506-00012', 'Stock Entry numbering mask expands date and padded ID tokens');
	fails(function () { PiStockEntry::validateNumberMask('STE-{YYYY}'); }, 'Stock Entry numbering mask requires a unique ID token');
	$receiptDocumentId = $stockEntryService->saveDraft(0, 'material_receipt', 'Multi item receipt document', array(
		array('product' => 1, 'warehouse' => 1, 'target' => 0, 'qty' => 1, 'cost' => 100000, 'account' => '5100', 'source' => 0, 'note' => 'Main receipt'),
		array('product' => 1, 'warehouse' => 2, 'target' => 0, 'qty' => 1, 'cost' => 100000, 'account' => '5100', 'source' => 0, 'note' => 'Second receipt'),
	), $user);
	$receiptDocument = $stockEntryService->fetch($receiptDocumentId);
	check(strpos($receiptDocument->ref, 'STE-'.substr($receiptDocument->date_post, 0, 4).'-') === 0 && (int) $receiptDocument->status === 0 && count($receiptDocument->lines) === 2, 'Stock Entry persists as a numbered multi-item Draft');
	$savedPostingStamp = $receiptDocument->date_post;
	$pi->postStockEntry($receiptDocumentId, $user);
	$receiptDocument = $stockEntryService->fetch($receiptDocumentId);
	check((int) $receiptDocument->status === 1 && $receiptDocument->lines[0]->fk_pi_entry > 0, 'Stock Entry validation links document lines to stock ledger');
	$postedLedgerDate = $pi->one('SELECT date_post FROM '.$pi->table('pi_entry').' WHERE entity=1 AND rowid='.(int) $receiptDocument->lines[0]->fk_pi_entry)->date_post;
	check($receiptDocument->date_post === $savedPostingStamp && $postedLedgerDate === $savedPostingStamp, 'Selected Stock Entry date and time are preserved during posting');
	$stockEntryJournal = $pi->one('SELECT doc_type,doc_ref,numero_compte FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='stock_entry' AND fk_doc=".(int) $receiptDocumentId." AND numero_compte='5100' LIMIT 1");
	check($stockEntryJournal && $stockEntryJournal->doc_ref === $receiptDocument->ref, 'Stock Entry item Difference Account posts under the document voucher');
	$draftLifecycleId = $stockEntryService->saveDraft(0, 'material_transfer', 'Draft lifecycle original', array(
		array('product' => 1, 'warehouse' => 1, 'target' => 2, 'qty' => 1, 'cost' => null, 'account' => '', 'source' => 0, 'note' => 'Original row'),
	), $user);
	$stockEntryService->saveDraft($draftLifecycleId, 'material_transfer', 'Draft lifecycle edited', array(
		array('product' => 1, 'warehouse' => 2, 'target' => 1, 'qty' => 1, 'cost' => null, 'account' => '', 'source' => 0, 'note' => 'Edited row'),
	), $user);
	$draftLifecycle = $stockEntryService->fetch($draftLifecycleId);
	check($draftLifecycle->note === 'Draft lifecycle edited' && count($draftLifecycle->lines) === 1 && (int) $draftLifecycle->lines[0]->fk_warehouse === 2, 'Stock Entry Draft can be edited without duplicating lines');
	$stockEntryService->deleteDraft($draftLifecycleId, $user);
	check(!$pi->one('SELECT rowid FROM '.$pi->table('pi_stock_entry').' WHERE entity=1 AND rowid='.(int) $draftLifecycleId), 'Stock Entry Draft and its lines can be deleted');
	$invalidDocumentId = $stockEntryService->saveDraft(0, 'material_transfer', 'Invalid atomic document', array(
		array('product' => 1, 'warehouse' => 1, 'target' => 2, 'qty' => 1, 'cost' => null, 'account' => '', 'source' => 0, 'note' => 'Valid first row'),
		array('product' => 1, 'warehouse' => 2, 'target' => 2, 'qty' => 1, 'cost' => null, 'account' => '', 'source' => 0, 'note' => 'Invalid second row'),
	), $user);
	$entriesBeforeInvalidDocument = (int) $pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n;
	fails(function () use ($pi, $invalidDocumentId, $user) { $pi->postStockEntry($invalidDocumentId, $user); }, 'Invalid persistent Stock Entry cannot be posted');
	$invalidDocument = $stockEntryService->fetch($invalidDocumentId);
	check((int) $invalidDocument->status === 0 && !$invalidDocument->lines[0]->fk_pi_entry, 'Failed Stock Entry validation keeps Draft and clears partial ledger links');
	near($pi->one('SELECT COUNT(*) n FROM '.$pi->table('pi_entry'))->n, $entriesBeforeInvalidDocument, 'Failed Stock Entry validation rolls back all stock ledger rows');
	$stockEntryService->deleteDraft($invalidDocumentId, $user);
	$issueDocumentId = $stockEntryService->saveDraft(0, 'material_issue', 'Multi item issue document', array(
		array('product' => 1, 'warehouse' => 1, 'target' => 0, 'qty' => 1, 'cost' => null, 'account' => '5100', 'source' => 0, 'note' => 'Main issue'),
		array('product' => 1, 'warehouse' => 2, 'target' => 0, 'qty' => 1, 'cost' => null, 'account' => '5100', 'source' => 0, 'note' => 'Second issue'),
	), $user);
	$pi->postStockEntry($issueDocumentId, $user);
	near($pi->balance(1, 1)->qty, $beforeMain, 'Posted receipt and issue documents restore main warehouse quantity');
	near($pi->balance(1, 2)->qty, $beforeSecond, 'Posted receipt and issue documents restore second warehouse quantity');
	near($pi->reconciliation()['difference'], 0, 'Stock Entry item journals reconcile with stock value');
	$pi->cancelStockEntry($receiptDocumentId, 'Wrong receipt Stock Entry', $user);
	$cancelDocument = $stockEntryService->fetch($receiptDocumentId);
	check((int) $cancelDocument->status === 2, 'Canceled Stock Entry receives Cancelled status');
	near($pi->balance(1, 1)->qty, $beforeMain - 1, 'Historical Stock Entry cancellation restores its main-warehouse quantity');
	near($pi->balance(1, 2)->qty, $beforeSecond - 1, 'Historical Stock Entry cancellation restores its second-warehouse quantity');
	check(!$pi->one('SELECT rowid FROM '.$pi->table('pi_entry')." WHERE origin_type='pistockentry' AND origin_id=".(int) $receiptDocumentId), 'Stock Entry cancellation removes PI ledger rows');
	check(!$pi->one('SELECT rowid FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='stock_entry' AND fk_doc=".(int) $receiptDocumentId), 'Stock Entry cancellation removes GL rows');
	near($pi->reconciliation()['difference'], 0, 'Historical Stock Entry cancellation replays later valuation and GL');
	check((int) $pi->one('SELECT COUNT(*) n FROM '.$pi->table('accounting_bookkeeping')." WHERE doc_type='stock_entry' AND fk_doc=".(int) $issueDocumentId)->n === 4, 'Historical replay replaces rather than duplicates later Stock Entry journals');
	check((bool) $pi->one('SELECT rowid FROM '.$pi->table('pi_document_action')." WHERE element_type='stock_entry' AND fk_element=".(int) $receiptDocumentId." AND action_code='cancel'"), 'Stock Entry cancellation records audit reason and snapshot');
	$pi->cancelStockEntry($issueDocumentId, 'Cancel dependent issue document', $user);
	near($pi->balance(1, 1)->qty, $beforeMain, 'Canceling both Stock Entries restores original main-warehouse quantity');
	near($pi->balance(1, 2)->qty, $beforeSecond, 'Canceling both Stock Entries restores original second-warehouse quantity');
	$stockEntryService->deleteDraft($receiptDocumentId, $user);
	$stockEntryService->deleteDraft($issueDocumentId, $user);
	check(!$pi->one('SELECT rowid FROM '.$pi->table('pi_stock_entry').' WHERE rowid IN ('.(int) $receiptDocumentId.','.(int) $issueDocumentId.')'), 'Canceled Stock Entries can be deleted after stock and GL reversal');
	$tomorrow = date('Y-m-d', strtotime('+1 day'));
	$futureBalance = $stockReport->stockBalance($tomorrow, $tomorrow);
	near(array_sum(array_map(function ($row) { return (float) $row->opening_value; }, $futureBalance)), 700000, 'Stock Balance opening value before period');
	$pi->operate('return', 0, 0, 1, null, 0, $delivery->rowid, 'Customer return', bin2hex(random_bytes(16)), $user);
	near($pi->reconciliation()['ledger'], 800000, 'Customer return at original cost');
	$pi->operate('count', 1, 2, 0, null, 0, 0, 'Count empty warehouse', bin2hex(random_bytes(16)), $user);
	near($pi->balance(1, 2)->stock_value, 0, 'Count to zero');
	$before = $pi->reconciliation()['ledger'];
	$db->failJournalInsert = 2;
	fails(function () { nativeMovement(1, 50000); }, 'Second journal line failure');
	near($pi->reconciliation()['ledger'], $before, 'Ledger rollback after GL failure');
	near($pi->reconciliation()['difference'], 0, 'GL rollback after second-line failure');
	check(!$pi->reconciliation()['quantity_errors'], 'Native stock rollback after GL failure');
	$db->failJournalInsert = 0;
	fails(function () use ($pi) { $pi->productWarehouse(2, 1); }, 'Cross-entity product rejected');
	fails(function () use ($pi) { $pi->productWarehouse(1, 3); }, 'Cross-entity warehouse rejected');
	$pi->q('INSERT INTO '.$pi->table('facture_fourn')." (rowid,entity,ref,ref_supplier,fk_soc,fk_statut,type,datef,multicurrency_tx) VALUES (1,1,'INV-TEST','SUP-TEST',1,0,0,CURRENT_DATE,1)");
	$pi->q('INSERT INTO '.$pi->table('facture_fourn_det')." (rowid,fk_facture_fourn,fk_product,qty,pu_ht,total_ht) VALUES (1,1,1,4,100000,400000)");
	$pi->q('INSERT INTO '.$pi->table('facture_fourn')." (rowid,entity,ref,ref_supplier,fk_soc,fk_statut,type,datef,multicurrency_tx) VALUES (2,1,'INV-AUTO','SUP-AUTO',1,0,0,CURRENT_DATE,1)");
	$pi->q('INSERT INTO '.$pi->table('facture_fourn_det')." (rowid,fk_facture_fourn,fk_product,qty,pu_ht,total_ht) VALUES (2,2,1,2,100000,200000)");
	$pi->q('INSERT INTO '.$pi->table('element_element')." (sourcetype,targettype,fk_source,fk_target) VALUES ('reception','invoice_supplier',1,2)");
	// A back-entered Reception and its invoice matching both use the business date.
	$pi->q('UPDATE '.$pi->table('reception').' SET date_reception=DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) WHERE rowid=1');
	$pi->q('UPDATE '.$pi->table('facture_fourn').' SET datef=DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) WHERE rowid=2');
	$autoCount = $pi->transaction(function () use ($pi, $user) {
		$count = $pi->autoMatchInvoice(2, $user);
		$pi->checkInvoice(2, true);
		return $count;
	});
	check($autoCount === 1, 'Invoice validation automatically matches linked receipt');
	check(substr($first->date_post, 0, 10) === date('Y-m-d', strtotime('-1 day')), 'Regression fixture posts ledger on Reception business date');
	near($pi->one('SELECT SUM(qty) qty FROM '.$pi->table('pi_match').' WHERE fk_invoice=2')->qty, 2, 'Automatic match allocates invoice quantity');
	near($pi->one('SELECT SUM(amount) amount FROM '.$pi->table('pi_match').' WHERE fk_invoice=2')->amount, 200000, 'Automatic match allocates receipt value');
	$pi->q('DELETE FROM '.$pi->table('pi_match').' WHERE fk_invoice=2');
	$pi->q('DELETE FROM '.$pi->table('element_element').' WHERE fk_target=2 AND targettype='.$pi->str('invoice_supplier'));
	$pi->q('DELETE FROM '.$pi->table('facture_fourn_det').' WHERE fk_facture_fourn=2');
	$pi->q('DELETE FROM '.$pi->table('facture_fourn').' WHERE rowid=2');
	$pi->matchInvoice($first->rowid, 1, 4, $user);
	$pi->transaction(function () use ($pi) { $pi->checkInvoice(1, true); });
	near($pi->one('SELECT SUM(amount) amount FROM '.$pi->table('pi_match'))->amount, 400000, 'Partial receipt invoicing');
	fails(function () use ($pi, $first, $user) { $pi->matchInvoice($first->rowid, 1, 1, $user); }, 'Invoice overmatching rejected');
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET total_ht=410000 WHERE rowid=1');
	fails(function () use ($pi) { $pi->transaction(function () use ($pi) { $pi->checkInvoice(1); }); }, 'Changed invoice price rejected');
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET total_ht=400000 WHERE rowid=1');
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET fk_code_ventilation=0 WHERE rowid=1');
	fails(function () use ($pi) { $pi->transaction(function () use ($pi) { $pi->checkInvoice(1); }); }, 'Wrong invoice account rejected');
	$pi->transaction(function () use ($pi) { $pi->checkInvoice(1, true); });
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET fk_product=NULL WHERE rowid=1');
	fails(function () use ($pi) { $pi->transaction(function () use ($pi) { $pi->checkInvoice(1); }); }, 'Matched stock line changed to free text');
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET fk_product=1 WHERE rowid=1');
	$pi->q('UPDATE '.$pi->table('facture_fourn').' SET fk_soc=999 WHERE rowid=1');
	fails(function () use ($pi) { $pi->transaction(function () use ($pi) { $pi->checkInvoice(1); }); }, 'Changed supplier rejected');
	$pi->q('UPDATE '.$pi->table('facture_fourn').' SET fk_soc=1 WHERE rowid=1');
	$before = $pi->reconciliation()['ledger'];
	fails(function () { nativeMovement(1, 1, 'mrp'); }, 'Unsupported stock origin rejected');
	near($pi->reconciliation()['ledger'], $before, 'Unsupported origin rolled back');
	fails(function () use ($pi, $first, $user) { $pi->operate('return', 0, 0, 7, null, 0, $first->rowid, 'Excess supplier return', bin2hex(random_bytes(16)), $user); }, 'Return cannot consume invoiced quantity');
	fails(function () use ($pi, $first, $user) { $pi->operate('reversal', 0, 0, 1, null, 0, $first->rowid, 'Old reversal', bin2hex(random_bytes(16)), $user); }, 'Reversal of old movement rejected');
	$pi->operate('adjustment', 1, 1, 1, 50000, 0, 0, 'Extra stock', bin2hex(random_bytes(16)), $user);
	$last = $pi->one('SELECT rowid FROM '.$pi->table('pi_entry').' ORDER BY rowid DESC LIMIT 1');
	$pi->operate('reversal', 0, 0, 1, null, 0, $last->rowid, 'Reverse latest adjustment', bin2hex(random_bytes(16)), $user);
	near($pi->reconciliation()['ledger'], $before, 'Reversal restores exact previous value');
	near($pi->reconciliation()['difference'], 0, 'Reversal journals reconcile');
	$conf->global->STOCK_CALCULATE_ON_BILL = 1;
	fails(function () { nativeMovement(1, 1); }, 'Incompatible workflow rejected');
	$conf->global->STOCK_CALCULATE_ON_BILL = 0;
	$conf->global->PRODUIT_SOUSPRODUITS = 1;
	$pi->validateWorkflow();
	$pi->productWarehouse(1, 1);
	$pi->q('INSERT INTO '.$pi->table('product_association').' (fk_product_pere,fk_product_fils,qty,incdec,rang) VALUES (1,2,1,1,1)');
	fails(function () use ($pi) { $pi->productWarehouse(1, 1); }, 'Actual kit product rejected while kit option may stay enabled');
	$pi->q('DELETE FROM '.$pi->table('product_association').' WHERE fk_product_pere=1 AND fk_product_fils=2');
	$conf->global->PRODUIT_SOUSPRODUITS = 0;
	$closed = $pi->config();
	$pi->transaction(function () use ($pi, $closed, $user) {
		$b = $pi->balance(1, 1);
		fails(function () use ($pi, $closed, $b, $user) { $pi->record($closed, $b, 'adjustment', 1, 1, dol_now() - 86400, 'backdated', '5100', $user); }, 'Backdated posting rejected');
	});
	$missingAccount = (array) $pi->config();
	$missingAccount['stock_account'] = 'MISSING';
	fails(function () use ($pi, $missingAccount, $user) { $pi->saveConfig($missingAccount, $user); }, 'Invalid account rejected');
	$nativeRow = $pi->one('SELECT rowid FROM '.$pi->table('stock_mouvement').' ORDER BY rowid DESC LIMIT 1');
	$stockObject = new MouvementStock($db);
	$stockObject->id = $nativeRow->rowid;
	check($stockObject->call_trigger('MOUVEMENTSTOCK_DELETE', $user) < 0, 'Native movement deletion blocked');
	$matchedRow = $pi->one('SELECT rowid FROM '.$pi->table('pi_match').' LIMIT 1');
	$pi->unmatch($matchedRow->rowid, $user);
	near($pi->one('SELECT fk_code_ventilation n FROM '.$pi->table('facture_fourn_det').' WHERE rowid=1')->n, 0, 'Unmatch clears stale account binding');
	$pi->matchInvoice($first->rowid, 1, 4, $user);
	$pi->q('UPDATE '.$pi->table('facture_fourn').' SET fk_statut=1 WHERE rowid=1');
	near($pi->reconciliation()['grni_difference'], -400000, 'Validated invoice pending journal is visible');
	$wrongBinding = $pi->account('5000');
	$pi->q('UPDATE '.$pi->table('facture_fourn_det').' SET fk_code_ventilation='.(int) $wrongBinding->rowid.' WHERE rowid=1');
	$pi->repairInvoiceAccount(1, $user);
	$grniBinding = $pi->account('2000');
	near($pi->one('SELECT fk_code_ventilation n FROM '.$pi->table('facture_fourn_det').' WHERE rowid=1')->n, $grniBinding->rowid, 'Validated matched invoice binding repaired to GRNI before journal transfer');
	$pi->transaction(function () use ($pi, $db, $user) {
		foreach (array(array('2000', 400000), array('2100', -400000)) as $line) {
			$j = new BookKeeping($db);
			$j->doc_date = dol_now();
			$j->doc_type = 'supplier_invoice';
			$j->doc_ref = 'INV-TEST';
			$j->fk_doc = 1;
			$j->fk_docdet = 0;
			$j->numero_compte = $line[0];
			$j->label_compte = 'Fixture invoice account';
			$j->label_operation = 'Fixture invoice journal';
			$j->debit = max(0, $line[1]);
			$j->credit = max(0, -$line[1]);
			$j->montant = abs($line[1]);
			$j->sens = $line[1] > 0 ? 'D' : 'C';
			$j->code_journal = 'PI';
			$j->fk_user_author = 1;
			$j->entity = 1;
			$result = $j->create($user);
			check($result >= 0 && $j->id > 0, 'Native supplier invoice journal created: '.$result.' '.$j->error.' '.implode('; ', $j->errors));
		}
	});
	near($pi->reconciliation()['grni_difference'], 0, 'Native invoice journal clears allocated GRNI');
	fails(function () use ($pi, $user) { $pi->repairInvoiceAccount(1, $user); }, 'Posted invoice binding cannot be repaired');
	fails(function () use ($pi, $matchedRow, $user) { $pi->unmatch($matchedRow->rowid, $user); }, 'Cannot unmatch validated invoice');
	$readonly = new User($db);
	$readonly->id = 2;
	fails(function () use ($pi, $readonly) { $pi->operate('adjustment', 1, 1, 1, 1, 0, 0, 'Forbidden', bin2hex(random_bytes(16)), $readonly); }, 'Posting permission enforced');
	$c = $pi->config();
	$c->lock_date = date('Y-m-d');
	$pi->saveConfig((array) $c, $user);
	fails(function () { nativeMovement(1, 100000); }, 'Closed date rejected');
	check(!$pi->reconciliation()['quantity_errors'], 'Final quantities reconcile');
	near($pi->reconciliation()['difference'], 0, 'Final GL reconciles');
	// Test opening stock in a separate fixture entity without touching native quantity.
	$conf->entity = 3;
	$opening = new PerpetualInventory($db, 3);
	$opening->q('INSERT INTO '.$opening->table('accounting_account')." (entity,fk_pcg_version,pcg_type,account_number,label,active) SELECT 3,fk_pcg_version,pcg_type,account_number,label,active FROM ".$opening->table('accounting_account').' WHERE entity=1');
	$opening->q('INSERT INTO '.$opening->table('accounting_journal')." (entity,code,label,active) VALUES (3,'PI','Fixture',1)");
	$opening->q('INSERT INTO '.$opening->table('product')." (rowid,entity,ref,label,fk_product_type,stockable_product,tobatch) VALUES (3,3,'OPENING','Opening fixture',0,1,0)");
	$opening->q('INSERT INTO '.$opening->table('entrepot')." (rowid,entity,ref,statut) VALUES (4,3,'OPENING',1)");
	$opening->q('INSERT INTO '.$opening->table('product_stock').' (fk_product,fk_entrepot,reel) VALUES (3,4,10)');
	$openingConfig = (array) $pi->config();
	$openingConfig['lock_date'] = '';
	$opening->saveConfig($openingConfig, $user);
	fails(function () use ($opening, $user) { $opening->start($user); }, 'Cannot start with unvalued opening quantity');
	$opening->opening(3, 4, 500, false, $user);
	near($opening->reconciliation()['ledger'], 5000, 'Opening ledger value');
	near($opening->reconciliation()['gl'], 5000, 'Opening journal value');
	near($opening->one('SELECT reel FROM '.$opening->table('product_stock').' WHERE fk_product=3 AND fk_entrepot=4')->reel, 10, 'Opening import does not duplicate stock');
	fails(function () use ($opening, $user) { $opening->opening(3, 4, 500, false, $user); }, 'Opening cannot be duplicated');
	$opening->start($user);
	check($opening->config()->enabled == 1, 'Opening reconciled before activation');
	fails(function () use ($opening, $user) { $opening->opening(3, 4, 500, false, $user); }, 'Opening unavailable after activation');
	$opening->q('INSERT INTO '.$opening->table('product')." (rowid,entity,ref,label,fk_product_type,stockable_product,tobatch) VALUES (4,3,'OPENING-NEW','Opening from zero',0,1,0),(5,3,'OPENING-LATE','Late opening',0,1,0)");
	$opening->q('INSERT INTO '.$opening->table('entrepot')." (rowid,entity,ref,statut) VALUES (5,3,'OPENING-NEW',1)");
	$opening->operate('opening', 4, 5, 2, 100, 0, 0, 'Fresh opening stock', bin2hex(random_bytes(16)), $user);
	$openingEntry = $opening->one('SELECT * FROM '.$opening->table('pi_entry')." WHERE entity=3 AND kind='opening' AND fk_product=4");
	check($openingEntry && $openingEntry->contra_account === '3000', 'Opening-from-zero uses opening balance account');
	near($opening->one('SELECT reel FROM '.$opening->table('product_stock').' WHERE fk_product=4 AND fk_entrepot=5')->reel, 2, 'Opening-from-zero creates native stock');
	fails(function () use ($opening, $user) { $opening->operate('opening', 4, 5, 1, 100, 0, 0, 'Duplicate opening', bin2hex(random_bytes(16)), $user); }, 'Duplicate opening for product warehouse rejected');
	$opening->operate('adjustment', 4, 5, 1, 100, 0, 0, 'Operational adjustment', bin2hex(random_bytes(16)), $user);
	fails(function () use ($opening, $user) { $opening->operate('opening', 5, 5, 1, 100, 0, 0, 'Late opening', bin2hex(random_bytes(16)), $user); }, 'Opening period closes after operational transaction');
	echo "PASS: $checks integration assertions (native Dolibarr stock + accounting + trigger)\n";
} finally {
	$db->rollback();
	foreach (array_reverse($tables) as $table) {
		if (strpos($table, $fixturePrefix) !== 0 || !preg_match('/^pitest_[a-f0-9]+_[a-z0-9_]+$/', $table)) {
			throw new RuntimeException('Unsafe fixture cleanup table');
		}
		$sourceDb->query('DROP TABLE IF EXISTS '.$table);
	}
	$db->close();
	$sourceDb->close();
}
