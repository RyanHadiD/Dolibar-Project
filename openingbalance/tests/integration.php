<?php

/** CLI only. Creates empty, randomly-prefixed fixtures and removes only those fixtures. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}

define('NOSESSION', 1);
require dirname(__DIR__, 3).'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/../class/openingbalance.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/perpetualinventory/class/perpetualinventory.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/perpetualinventory/class/pidailyreport.class.php';

if ($db->type !== 'mysqli') {
	throw new RuntimeException('Integration fixture requires MariaDB/MySQL.');
}

$sourceDb = $db;
$fixturePrefix = 'obtest_'.bin2hex(random_bytes(5)).'_';

class ObFixtureDB extends DoliDBMysqli
{
	public $fixturePrefix;

	public function query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		if ($this->fixturePrefix) {
			$query = str_replace(MAIN_DB_PREFIX, $this->fixturePrefix, $query);
		}
		return parent::query($query, $usesavepoint, $type, $result_mode);
	}
}

$db = new ObFixtureDB('mysqli', $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port);
$db->fixturePrefix = $fixturePrefix;
$db->prefix_db = $fixturePrefix;
$tables = array();
$checks = 0;

function obCheck($condition, $label)
{
	global $checks;
	if (!$condition) {
		throw new RuntimeException('FAIL: '.$label);
	}
	$checks++;
}

function obNear($actual, $expected, $label)
{
	obCheck(abs($actual - $expected) < 0.000001, $label.' ['.$actual.' / '.$expected.']');
}

function obFails($callback, $label)
{
	try {
		$callback();
	} catch (DomainException $e) {
		obCheck(true, $label);
		return;
	}
	throw new RuntimeException('Expected rejection: '.$label);
}

function obFixtureQuery($sql, $label)
{
	global $db;
	if (!$db->query($sql)) {
		throw new RuntimeException('Fixture insert failed ('.$label.'): '.$db->lasterror());
	}
}

try {
	foreach (array('product', 'product_extrafields', 'product_stock', 'product_batch', 'product_lot', 'product_association', 'entrepot', 'entrepot_extrafields', 'stock_mouvement', 'societe', 'societe_extrafields', 'societe_contacts', 'socpeople', 'societe_remise_except', 'projet', 'accounting_account', 'accounting_system', 'accounting_journal', 'accounting_bookkeeping', 'accounting_fiscalyear', 'facture', 'facturedet', 'facture_extrafields', 'facturedet_extrafields', 'facture_fourn', 'facture_fourn_det', 'facture_fourn_extrafields', 'facture_fourn_det_extrafields', 'element_element', 'events', 'extrafields', 'ecm_files', 'const', 'c_units', 'c_revenuestamp', 'c_country', 'c_typent', 'c_effectif', 'c_forme_juridique', 'c_departements', 'c_regions', 'c_stcomm', 'c_incoterms', 'c_type_contact', 'c_tva', 'c_payment_term', 'c_paiement', 'c_input_reason', 'paiement', 'paiement_facture', 'paiementfourn', 'paiementfourn_facturefourn', 'overwrite_trans', 'user') as $name) {
		$target = $fixturePrefix.$name;
		if (!$sourceDb->query('CREATE TABLE '.$target.' LIKE '.MAIN_DB_PREFIX.$name)) {
			throw new RuntimeException('Cannot create fixture '.$name.': '.$sourceDb->lasterror());
		}
		$tables[] = $target;
	}
	foreach (array_merge(glob(__DIR__.'/../../perpetualinventory/sql/*.sql'), glob(__DIR__.'/../sql/*.sql')) as $path) {
		$sql = str_replace('llx_', $fixturePrefix, file_get_contents($path));
		if (!$db->query($sql)) {
			throw new RuntimeException($db->lasterror());
		}
		if (!preg_match('/CREATE TABLE\s+('.preg_quote($fixturePrefix, '/').'[a-z0-9_]+)/i', $sql, $match)) {
			throw new RuntimeException('Cannot identify fixture table for '.$path);
		}
		$tables[] = $match[1];
	}

	$conf->entity = 1;
	$conf->global->CHARTOFACCOUNTS = 1;
	$conf->global->ACCOUNTING_ACCOUNT_CUSTOMER = '1100';
	$conf->global->ACCOUNTING_ACCOUNT_SUPPLIER = '2000';
	$conf->currency = 'IDR';
	$conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
	$conf->modules_parts['triggers'] = array();
	$conf->modules = array('societe' => 'societe', 'product' => 'product', 'stock' => 'stock', 'facture' => 'facture', 'fournisseur' => 'fournisseur', 'accounting' => 'accounting', 'openingbalance' => 'openingbalance');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."accounting_system (rowid,pcg_version,label,active) VALUES (1,'TEST','Test chart',1)", 'accounting system');
	foreach (array('9999', '1000', '1100', '1200', '2000', '3000') as $account) {
		obFixtureQuery("INSERT INTO ".$fixturePrefix."accounting_account (entity,fk_pcg_version,pcg_type,account_number,label,active) VALUES (1,'TEST','TEST','".$account."','Fixture ".$account."',1)", 'account '.$account);
	}
	obFixtureQuery("INSERT INTO ".$fixturePrefix."accounting_journal (entity,code,label,active) VALUES (1,'OB','Opening balance',1)", 'journal');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."accounting_fiscalyear (entity,label,date_start,date_end,statut,datec) VALUES (1,'Fixture year','".date('Y-01-01')."','".date('Y-12-31')."',0,NOW())", 'fiscal year');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."c_paiement (id,entity,code,libelle,type,active,position) VALUES (1,1,'VIR','Transfer',2,1,1)", 'payment method');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."product (rowid,entity,ref,label,fk_product_type,stockable_product) VALUES (1,1,'ITEM-1','Fixture product',0,1),(2,1,'ITEM-RUNNING','Running migration product',0,1)", 'product');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."entrepot (rowid,entity,ref,description,statut) VALUES (1,1,'WH-1','Fixture warehouse',1)", 'warehouse');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."societe (rowid,entity,nom,client,fournisseur,code_client,code_fournisseur,code_compta,code_compta_fournisseur,accountancy_code_customer_general,accountancy_code_supplier_general,statut) VALUES (1,1,'Fixture customer',1,0,'CUS-1',NULL,'CUS-AUX',NULL,'1100',NULL,1),(2,1,'Fixture supplier',0,1,NULL,'SUP-1',NULL,'SUP-AUX',NULL,'2000',1)", 'parties');
	obFixtureQuery("INSERT INTO ".$fixturePrefix."pi_config (entity,enabled,stock_account,grni_account,cogs_account,adjustment_account,opening_account,journal_code,lock_date) VALUES (1,0,'1000','2000','3000','1200','9999','OB',NULL)", 'perpetual inventory config');

	$service = new OpeningBalance($db, 1);
	$user = new User($db);
	$user->id = 1;
	$user->admin = 1;
	$user->rights = (object) array(
		'openingbalance' => (object) array('post' => 1),
		'perpetualinventory' => (object) array('read' => 1, 'write' => 1),
		'facture' => (object) array('creer' => 1, 'invoice_advance' => (object) array('validate' => 1)),
		'fournisseur' => (object) array('facture' => (object) array('creer' => 1)),
	);
	$cutover = date('Y-m-d', dol_now());
	$id = $service->createBatch($cutover, 'Fixture opening', $user);
	obCheck($id > 0 && $service->batch($id)->status == OpeningBalance::STATUS_DRAFT, 'Batch created as Draft');

	$service->saveHeader($id, array(
		'label' => 'Fixture opening', 'cutover_date' => $cutover, 'clearing_account' => '9999',
		'stock_account' => '1000', 'receivable_account' => '1100', 'payable_account' => '2000',
		'journal_code' => 'OB', 'note' => 'Fixture only',
	));
	obCheck($service->importCsvSection($id, 'stock', "\xEF\xBB\xBFproduct_ref;warehouse_ref;qty;unit_cost;note\nITEM-1;WH-1;10;10;CSV stock\n") === 1, 'Semicolon CSV stock imported with BOM');
	obCheck($service->stockLines($id)[0]->product_ref === 'ITEM-1', 'CSV stock resolves product reference');
	obCheck($service->importCsvSection($id, 'ar', "party_code,source_ref,invoice_date,due_date,original_amount,prior_paid,outstanding,currency,note\nCUS-1,AR-CSV,".$cutover.",".$cutover.",70,20,50,IDR,CSV AR\n") === 1, 'CSV receivable imported');
	obCheck($service->partyLines($id, 'ar')[0]->code_client === 'CUS-1', 'CSV receivable resolves customer code');
	obCheck($service->importCsvSection($id, 'ap', "party_code\tsource_ref\tinvoice_date\tdue_date\toriginal_amount\tprior_paid\toutstanding\tcurrency\tnote\nSUP-1\tAP-CSV\t".$cutover."\t".$cutover."\t30\t0\t30\tIDR\tCSV AP\n") === 1, 'Tab-separated supplier CSV imported');
	obCheck($service->partyLines($id, 'ap')[0]->code_fournisseur === 'SUP-1', 'CSV payable resolves supplier code');
	obCheck($service->importCsvSection($id, 'gl', "note,credit,account_number,debit\nCSV equity,120,3000,\n") === 1, 'CSV account import accepts reordered headers');
	obCheck($service->glLines($id)[0]->account_number === '3000', 'CSV account resolves account number');
	obFails(function () use ($service, $id) {
		$service->importCsvSection($id, 'gl', "account_number,debit,note\n3000,1,Missing header\n");
	}, 'CSV missing required header rejected');
	obCheck(count($service->glLines($id)) === 1, 'Rejected CSV leaves existing section unchanged');
	$service->saveStockLines($id, array(array('product' => 1, 'warehouse' => 1, 'qty' => '10', 'unit_cost' => '10', 'note' => 'Opening stock')));
	$service->savePartyLines($id, 'ar', array(array('party' => 1, 'source_ref' => 'AR-OLD', 'invoice_date' => $cutover, 'due_date' => $cutover, 'original_amount' => '70', 'prior_paid' => '20', 'outstanding' => '50', 'currency' => 'IDR', 'note' => 'Opening AR')));
	$service->savePartyLines($id, 'ap', array(array('party' => 2, 'source_ref' => 'AP-OLD', 'invoice_date' => $cutover, 'due_date' => $cutover, 'original_amount' => '30', 'prior_paid' => '0', 'outstanding' => '30', 'currency' => 'IDR', 'note' => 'Opening AP')));
	$service->saveGlLines($id, array(array('account' => '3000', 'debit' => '', 'credit' => '120', 'note' => 'Opening equity')));

	$summary = $service->summary($id);
	obNear($summary['stock'], 100, 'Stock value summarized');
	obNear($summary['ar'], 50, 'Receivable summarized');
	obNear($summary['ap'], 30, 'Payable summarized');
	obNear($summary['debit'], 150, 'Opening debits summarized');
	obNear($summary['credit'], 150, 'Opening credits summarized');
	obNear($summary['difference'], 0, 'Opening batch balances');

	$service->validateBatch($id, $user);
	obCheck($service->batch($id)->status == OpeningBalance::STATUS_VALIDATED, 'Balanced batch validated');
	obFails(function () use ($service, $id) { $service->saveStockLines($id, array()); }, 'Validated batch cannot be edited');
	$service->reopen($id);
	obCheck($service->batch($id)->status == OpeningBalance::STATUS_DRAFT, 'Validated batch reopened');

	obFails(function () use ($service, $id) {
		$service->saveStockLines($id, array(
			array('product' => 1, 'warehouse' => 1, 'qty' => '1', 'unit_cost' => '1', 'note' => ''),
			array('product' => 1, 'warehouse' => 1, 'qty' => '2', 'unit_cost' => '1', 'note' => ''),
		));
	}, 'Duplicate product and warehouse rejected');
	obFails(function () use ($service, $id, $cutover) {
		$service->savePartyLines($id, 'ar', array(array('party' => 1, 'source_ref' => 'BAD', 'invoice_date' => $cutover, 'due_date' => $cutover, 'original_amount' => '100', 'prior_paid' => '10', 'outstanding' => '80', 'currency' => 'IDR', 'note' => '')));
	}, 'Inconsistent outstanding rejected');
	obFails(function () use ($service, $id) {
		$service->saveGlLines($id, array(array('account' => '1000', 'debit' => '1', 'credit' => '', 'note' => 'Duplicate control')));
	}, 'Control account duplication rejected');

	$postId = $service->createBatch($cutover, 'Posting fixture', $user);
	$service->saveHeader($postId, array(
		'label' => 'Posting fixture', 'cutover_date' => $cutover, 'clearing_account' => '9999',
		'stock_account' => '1000', 'receivable_account' => '1100', 'payable_account' => '2000',
		'journal_code' => 'OB', 'note' => 'GL posting fixture',
	));
	$service->saveGlLines($postId, array(
		array('account' => '1200', 'debit' => '75', 'credit' => '', 'note' => 'Debit opening'),
		array('account' => '3000', 'debit' => '', 'credit' => '75', 'note' => 'Credit opening'),
	));
	$service->validateBatch($postId, $user);
	obCheck(!$service->postingProblems($postId), 'Validated GL-only batch is ready to post');
	$postResult = $service->finalizeBatch($postId, $user);
	obCheck($postResult['invoices'] === 0 && $postResult['stock'] === 0 && $postResult['journals'] === 4, 'Finalization result reports generated journals');
	obCheck($service->batch($postId)->status == OpeningBalance::STATUS_POSTED, 'Finalized batch is Posted');
	$posted = $service->one('SELECT COUNT(*) count,COALESCE(SUM(debit),0) debit,COALESCE(SUM(credit),0) credit,MIN(date_validated) validated FROM '.$service->table('accounting_bookkeeping')." WHERE doc_type='openingbalance' AND fk_doc=".(int) $postId);
	obCheck((int) $posted->count === 4 && !empty($posted->validated), 'Four finalized bookkeeping rows created');
	obNear($posted->debit, 150, 'Posted opening debits balance through clearing');
	obNear($posted->credit, 150, 'Posted opening credits balance through clearing');
	obCheck((int) $service->one('SELECT COUNT(*) count FROM '.$service->table('ob_posting_link').' WHERE entity=1 AND fk_batch='.(int) $postId)->count === 4, 'Posting audit links recorded');
	obFails(function () use ($service, $postId, $user) { $service->finalizeBatch($postId, $user); }, 'Finalization is idempotently blocked');

	$partyBatchId = $service->createBatch($cutover, 'Party posting fixture', $user);
	$service->saveHeader($partyBatchId, array(
		'label' => 'Party posting fixture', 'cutover_date' => $cutover, 'clearing_account' => '9999',
		'stock_account' => '1000', 'receivable_account' => '1100', 'payable_account' => '2000',
		'journal_code' => 'OB', 'note' => 'Native invoice fixture',
	));
	$service->savePartyLines($partyBatchId, 'ar', array(array('party' => 1, 'source_ref' => 'AR-NATIVE', 'invoice_date' => $cutover, 'due_date' => $cutover, 'original_amount' => '70', 'prior_paid' => '20', 'outstanding' => '50', 'currency' => 'IDR', 'note' => 'Native AR')));
	$service->savePartyLines($partyBatchId, 'ap', array(array('party' => 2, 'source_ref' => 'AP-NATIVE', 'invoice_date' => $cutover, 'due_date' => $cutover, 'original_amount' => '30', 'prior_paid' => '0', 'outstanding' => '30', 'currency' => 'IDR', 'note' => 'Native AP')));
	$service->saveGlLines($partyBatchId, array(array('account' => '3000', 'debit' => '', 'credit' => '20', 'note' => 'Balancing equity')));
	$service->validateBatch($partyBatchId, $user);
	obCheck(!$service->postingProblems($partyBatchId), 'AR/AP batch is ready to post');
	$partyResult = $service->finalizeBatch($partyBatchId, $user);
	obCheck($partyResult['invoices'] === 2 && $partyResult['journals'] === 6, 'AR/AP finalization creates invoices and journals');
	$arInvoice = $service->one('SELECT * FROM '.$service->table('facture').' WHERE entity=1 AND ref_ext LIKE '.$service->str('OPENINGBALANCE:'.$partyBatchId.':ar:%'));
	$apInvoice = $service->one('SELECT * FROM '.$service->table('facture_fourn').' WHERE entity=1 AND ref_ext LIKE '.$service->str('OPENINGBALANCE:'.$partyBatchId.':ap:%'));
	obCheck($arInvoice && $arInvoice->fk_statut == 1 && abs($arInvoice->total_ttc - 50) < 0.005, 'Native customer opening invoice is validated and outstanding');
	obCheck($apInvoice && $apInvoice->fk_statut == 1 && abs($apInvoice->total_ttc - 30) < 0.005, 'Native supplier opening invoice is validated and outstanding');
	$arDetail = $service->one('SELECT * FROM '.$service->table('facturedet').' WHERE fk_facture='.(int) $arInvoice->rowid);
	$apDetail = $service->one('SELECT * FROM '.$service->table('facture_fourn_det').' WHERE fk_facture_fourn='.(int) $apInvoice->rowid);
	$clearingId = $service->account('9999')->rowid;
	obCheck(!$arDetail->fk_product && (int) $arDetail->fk_code_ventilation === (int) $clearingId && abs($arDetail->total_tva) < 0.005, 'Customer opening line is a non-product, zero-tax clearing line');
	obCheck(!$apDetail->fk_product && (int) $apDetail->fk_code_ventilation === (int) $clearingId && abs($apDetail->tva) < 0.005, 'Supplier opening line is a non-product, zero-tax clearing line');
	obCheck((int) $service->one('SELECT COUNT(*) count FROM '.$service->table('accounting_bookkeeping')." WHERE doc_type='customer_invoice' AND fk_doc=".(int) $arInvoice->rowid)->count === 2, 'Customer invoice is marked transferred to bookkeeping');
	obCheck((int) $service->one('SELECT COUNT(*) count FROM '.$service->table('accounting_bookkeeping')." WHERE doc_type='supplier_invoice' AND fk_doc=".(int) $apInvoice->rowid)->count === 2, 'Supplier invoice is marked transferred to bookkeeping');
	$daily = new PiDailyReport(new PerpetualInventory($db, 1));
	obCheck(count($daily->register('receivable', $cutover, $cutover)) === 0 && count($daily->register('payable', $cutover, $cutover)) === 0, 'Opening invoices are excluded from period sales and purchase registers');
	obCheck(count($daily->summary('receivable', $cutover)) === 1 && count($daily->summary('payable', $cutover)) === 1, 'Opening invoices remain included in receivable and payable ageing');

	$customerPayment = new Paiement($db);
	$customerPayment->datepaye = dol_now();
	$customerPayment->paiementid = 1;
	$customerPayment->num_payment = 'OB-CUSTOMER-TEST';
	$customerPayment->amounts = array((int) $arInvoice->rowid => 50);
	$customerPaymentId = $customerPayment->create($user, 1);
	obCheck($customerPaymentId > 0, 'Customer opening invoice accepts a native payment');
	$arAfterPayment = $service->one('SELECT fk_statut,paye FROM '.$service->table('facture').' WHERE rowid='.(int) $arInvoice->rowid);
	$arAllocated = $service->one('SELECT SUM(amount) amount FROM '.$service->table('paiement_facture').' WHERE fk_facture='.(int) $arInvoice->rowid)->amount;
	obNear($arAllocated, 50, 'Customer payment allocation matches outstanding');
	obCheck((int) $arAfterPayment->paye === 1 && (int) $arAfterPayment->fk_statut === 2, 'Customer opening invoice closes as paid');

	$supplierPayment = new PaiementFourn($db);
	$supplierPayment->datepaye = dol_now();
	$supplierPayment->paiementid = 1;
	$supplierPayment->num_payment = 'OB-SUPPLIER-TEST';
	$supplierPayment->amounts = array((int) $apInvoice->rowid => 30);
	$supplierPaymentId = $supplierPayment->create($user, 1);
	obCheck($supplierPaymentId > 0, 'Supplier opening invoice accepts a native payment');
	$apAfterPayment = $service->one('SELECT fk_statut,paye FROM '.$service->table('facture_fourn').' WHERE rowid='.(int) $apInvoice->rowid);
	obCheck((int) $apAfterPayment->paye === 1 && (int) $apAfterPayment->fk_statut === 2, 'Supplier opening invoice closes as paid');
	obNear($service->one('SELECT SUM(amount) amount FROM '.$service->table('paiementfourn_facturefourn').' WHERE fk_facturefourn='.(int) $apInvoice->rowid)->amount, 30, 'Supplier payment allocation matches outstanding');

	$stockBatchId = $service->createBatch($cutover, 'Stock posting fixture', $user);
	$service->saveHeader($stockBatchId, array(
		'label' => 'Stock posting fixture', 'cutover_date' => $cutover, 'clearing_account' => '9999',
		'stock_account' => '1000', 'receivable_account' => '1100', 'payable_account' => '2000',
		'journal_code' => 'OB', 'note' => 'Native stock fixture',
	));
	$service->saveStockLines($stockBatchId, array(array('product' => 1, 'warehouse' => 1, 'qty' => '4', 'unit_cost' => '25', 'note' => 'Native stock opening')));
	$service->saveGlLines($stockBatchId, array(array('account' => '3000', 'debit' => '', 'credit' => '100', 'note' => 'Opening equity')));
	$stockLine = $service->stockLines($stockBatchId)[0];
	$service->validateBatch($stockBatchId, $user);
	obCheck(!$service->postingProblems($stockBatchId), 'Stock batch is ready to post before perpetual inventory activation');
	$stockResult = $service->finalizeBatch($stockBatchId, $user);
	obCheck($stockResult['stock'] === 1 && $stockResult['journals'] === 4, 'Stock finalization reports one valuation and balanced journal rows');
	obNear($service->one('SELECT reel amount FROM '.$service->table('product_stock').' WHERE fk_product=1 AND fk_entrepot=1')->amount, 4, 'Stock finalization creates native quantity once');
	$stockEntry = $service->one('SELECT * FROM '.$service->table('pi_entry').' WHERE entity=1 AND event_key='.$service->str('openingbalance:'.$stockBatchId.':'.$stockLine->rowid));
	obCheck($stockEntry && $stockEntry->kind === 'opening' && $stockEntry->contra_account === '9999', 'Stock finalization creates perpetual opening valuation with clearing account');
	obNear($stockEntry->balance_value, 100, 'Perpetual opening valuation matches quantity and unit cost');
	obCheck((int) $service->one('SELECT COUNT(*) count FROM '.$service->table('ob_posting_link').' WHERE entity=1 AND fk_batch='.(int) $stockBatchId)->count === 6, 'Stock finalization records movement, valuation, journal, and GL audit links');

	// Running migration: operational receipt/delivery already exist, then an
	// older additive opening is inserted and the complete valuation is replayed.
	$pi = new PerpetualInventory($db, 1);
	$service->q('UPDATE '.$service->table('pi_config').' SET enabled=1,start_date='.$service->str($db->idate(dol_now() - 60)).' WHERE entity=1');
	$config = $pi->config(true);
	$balance = $pi->balance(2, 1);
	$receiptDate = dol_now() - 30;
	$pi->record($config, $balance, 'receipt', 5, 20, $receiptDate, 'running-receipt', '2000', $user, array('note' => 'Running receipt'));
	$balance = $pi->balance(2, 1);
	$deliveryId = $pi->record($config, $balance, 'delivery', -2, 0, dol_now() - 10, 'running-delivery', '3000', $user, array('note' => 'Running delivery'));
	$service->q('INSERT INTO '.$service->table('product_stock').' (fk_product,fk_entrepot,reel) VALUES (2,1,3)');

	$runningCutover = date('Y-m-d', dol_now() - 86400);
	$runningBatchId = $service->createBatch($runningCutover, 'Running stock migration', $user);
	$service->saveHeader($runningBatchId, array(
		'label' => 'Running stock migration', 'cutover_date' => $runningCutover, 'clearing_account' => '9999',
		'stock_account' => '1000', 'receivable_account' => '1100', 'payable_account' => '2000',
		'journal_code' => 'OB', 'note' => 'Backfilled while operations continue',
	));
	$service->saveStockLines($runningBatchId, array(array('product' => 2, 'warehouse' => 1, 'qty' => '10', 'unit_cost' => '10', 'note' => 'Historical opening')));
	$service->saveGlLines($runningBatchId, array(array('account' => '3000', 'debit' => '', 'credit' => '100', 'note' => 'Opening equity')));
	$service->validateBatch($runningBatchId, $user);
	obCheck(!$service->postingProblems($runningBatchId), 'Running perpetual inventory accepts a historical stock opening');
	$runningResult = $service->finalizeBatch($runningBatchId, $user);
	obCheck($runningResult['stock'] === 1, 'Running stock opening finalizes once');
	obNear($service->one('SELECT reel amount FROM '.$service->table('product_stock').' WHERE fk_product=2 AND fk_entrepot=1')->amount, 13, 'Historical opening is additive to current native stock');
	$runningBalance = $pi->one('SELECT * FROM '.$pi->table('pi_balance').' WHERE entity=1 AND fk_product=2 AND fk_warehouse=1');
	obNear($runningBalance->qty, 13, 'Replay produces current ledger quantity');
	obNear($runningBalance->stock_value, 173.33, 'Replay carries historical opening through moving average');
	$deliveryAfterReplay = $pi->one('SELECT * FROM '.$pi->table('pi_entry').' WHERE rowid='.(int) $deliveryId);
	obNear($deliveryAfterReplay->value_delta, -26.67, 'Replay recalculates delivery HPP from historical moving average');
	$rerun = $service->recalculateStockValuation($runningBatchId, $user);
	obCheck($rerun['entries'] >= 4, 'Posted batch exposes idempotent valuation replay');
	obNear($service->one('SELECT reel amount FROM '.$service->table('product_stock').' WHERE fk_product=2 AND fk_entrepot=1')->amount, 13, 'Recalculate button does not duplicate native opening quantity');
	obNear($pi->one('SELECT stock_value FROM '.$pi->table('pi_balance').' WHERE entity=1 AND fk_product=2 AND fk_warehouse=1')->stock_value, 173.33, 'Repeated replay keeps the same valuation');

	echo 'PASS: '.$checks." opening balance integration assertions\n";
} finally {
	$db->rollback();
	foreach (array_reverse($tables) as $table) {
		if (strpos($table, $fixturePrefix) !== 0 || !preg_match('/^obtest_[a-f0-9]+_[a-z0-9_]+$/', $table)) {
			throw new RuntimeException('Unsafe fixture cleanup table');
		}
		$sourceDb->query('DROP TABLE IF EXISTS '.$table);
	}
	$db->close();
	$sourceDb->close();
}
