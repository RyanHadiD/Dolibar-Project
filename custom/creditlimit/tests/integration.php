<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('NOSESSION', 1);
require dirname(__DIR__, 3).'/master.inc.php';
require_once __DIR__.'/../class/creditlimit.class.php';
require_once __DIR__.'/../class/customerorderproof.class.php';

if ($db->type !== 'mysqli') throw new RuntimeException('Credit Limit fixture requires MariaDB/MySQL.');
$sourceDb = $db; $prefix = 'cltest_'.bin2hex(random_bytes(5)).'_'; $tables = array(); $checks = 0; $proofFixture = '';
class ClFixtureDB extends DoliDBMysqli
{
	public $fixturePrefix;
	public function query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0)
	{
		if ($this->fixturePrefix) $query = str_replace(MAIN_DB_PREFIX, $this->fixturePrefix, $query);
		return parent::query($query, $usesavepoint, $type, $result_mode);
	}
}
function clCheck($condition, $label) { global $checks; if (!$condition) throw new RuntimeException('FAIL: '.$label); $checks++; }
function clFails($callback, $label) { try { $callback(); } catch (DomainException $e) { clCheck(true, $label); return; } throw new RuntimeException('Expected rejection: '.$label); }

$db = new ClFixtureDB('mysqli', $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port);
$db->fixturePrefix = $prefix; $db->prefix_db = $prefix;
try {
	$proofFixture = sys_get_temp_dir().'/clproof_'.bin2hex(random_bytes(8));
	if (!mkdir($proofFixture, 0700)) throw new RuntimeException('Unable to create proof fixture directory');
	file_put_contents($proofFixture.'/generated-order.pdf', 'not a proof');
	file_put_contents($proofFixture.'/CUSTOMER_PROOF-empty.pdf', '');
	clCheck(CustomerOrderProof::filesInDirectory($proofFixture) === array(), 'Generated and empty documents do not satisfy proof requirement');
	file_put_contents($proofFixture.'/CUSTOMER_PROOF-customer-po.pdf', 'fixture proof');
	clCheck(CustomerOrderProof::filesInDirectory($proofFixture) === array('CUSTOMER_PROOF-customer-po.pdf'), 'Non-empty prefixed customer document satisfies proof requirement');

	foreach (array('societe','commande','facture','element_element','user') as $name) {
		$target=$prefix.$name; if(!$sourceDb->query('CREATE TABLE '.$target.' LIKE '.MAIN_DB_PREFIX.$name)) throw new RuntimeException($sourceDb->lasterror()); $tables[]=$target;
	}
	foreach (glob(__DIR__.'/../sql/*.sql') as $path) {
		$sql=str_replace('llx_',$prefix,file_get_contents($path)); if(!$db->query($sql)) throw new RuntimeException($db->lasterror());
		preg_match('/CREATE TABLE\s+([a-zA-Z0-9_]+)/i',$sql,$m); $tables[]=$m[1];
	}
	$service=new CreditLimitService($db,1);
	$user=(object) array('id'=>9);
	$service->q("INSERT INTO ".$service->table('cl_config')." (entity,enabled,default_limit,default_mode,max_overdue_days,check_order,check_shipment,check_invoice,include_orders,override_valid_days) VALUES (1,1,0,'block',60,1,1,1,1,7)");
	$service->q("INSERT INTO ".$service->table('societe')." (rowid,entity,nom,client,status,outstanding_limit) VALUES (1,1,'Fixture Customer',1,1,1000)");
	$service->q("INSERT INTO ".$service->table('commande')." (rowid,entity,ref,fk_soc,fk_statut,total_ttc) VALUES (10,1,'SO-OLD',1,1,600),(11,1,'SO-NEW',1,0,500)");
	$order=(object) array('element'=>'commande','id'=>11,'socid'=>1,'ref'=>'SO-NEW','status'=>0,'total_ttc'=>500);
	$check=$service->evaluate($order,true);
	clCheck(abs($check['exposure']-600)<0.000001 && abs($check['projected']-1100)<0.000001,'Draft order is added to existing exposure once');
	clCheck($check['blocked'] && count($check['violations'])===1,'Order above limit is blocked');
	$service->q('UPDATE '.$service->table('commande').' SET fk_statut=1 WHERE rowid=11'); $order->status=1;
	$check=$service->evaluate($order,true);
	clCheck(abs($check['projected']-1100)<0.000001,'Current order is not double-counted after native status update');
	clFails(function()use($service,$order,$user){$service->preflight($order,$user);},'UI preflight blocks before the native transaction');
	clCheck($service->one('SELECT decision FROM '.$service->table('cl_check_log')." WHERE decision='blocked' LIMIT 1")!==null,'Blocked validation attempt is audited outside native rollback');
	clFails(function()use($service,$order,$user){$service->enforce($order,$user);},'Validation without override is rejected');
	$service->approveOverride($order,'Approved for fixture customer', $user);
	$service->enforce($order,$user);
	clCheck($service->one('SELECT status FROM '.$service->table('cl_override').' LIMIT 1')->status==='used','Approved override is consumed once');
	clCheck($service->one('SELECT decision FROM '.$service->table('cl_check_log')." WHERE decision='override' LIMIT 1")!==null,'Override decision is audited');
	$order->total_ttc=501;
	clFails(function()use($service,$order,$user){$service->enforce($order,$user);},'Changed document amount invalidates consumed approval');
	$service->q("UPDATE ".$service->table('cl_config')." SET default_mode='warn'");
	$service->enforce($order,$user);
	clCheck($service->one('SELECT decision FROM '.$service->table('cl_check_log')." WHERE decision='warning' LIMIT 1")!==null,'Warning policy logs without blocking');
	$service->q("UPDATE ".$service->table('cl_config')." SET default_mode='block'");
	$service->q('UPDATE '.$service->table('commande').' SET fk_statut=0 WHERE rowid=11');
	$service->q("INSERT INTO ".$service->table('facture')." (rowid,entity,ref,type,fk_soc,datef,total_ttc,fk_statut,paye) VALUES (20,1,'INV-DRAFT',0,1,CURRENT_DATE,500,0,0)");
	$service->q("INSERT INTO ".$service->table('element_element')." (sourcetype,targettype,fk_source,fk_target) VALUES ('commande','facture',10,20)");
	$invoice=(object) array('element'=>'facture','id'=>20,'socid'=>1,'ref'=>'INV-DRAFT','status'=>0,'type'=>0,'total_ttc'=>500);
	$check=$service->evaluate($invoice,true);
	clCheck(abs($check['order_exposure']-600)<0.000001 && abs($check['delta'])<0.000001 && abs($check['projected']-600)<0.000001,'Invoice releases linked order reserve without double-counting');
	$service->q('UPDATE '.$service->table('facture').' SET fk_statut=1 WHERE rowid=20'); $invoice->status=1;
	$check=$service->evaluate($invoice,true);
	clCheck(abs($check['projected']-600)<0.000001,'Invoice is not double-counted when trigger runs after native status update');
	echo 'PASS: '.$checks." Credit Limit integration assertions\n";
} finally {
	foreach(array_reverse($tables) as $table){ if(strpos($table,$prefix)!==0||!preg_match('/^cltest_[a-f0-9]+_[a-z0-9_]+$/',$table)) throw new RuntimeException('Unsafe fixture cleanup'); $sourceDb->query('DROP TABLE IF EXISTS '.$table); }
	if ($proofFixture && is_dir($proofFixture) && strpos(basename($proofFixture), 'clproof_') === 0) {
		foreach (scandir($proofFixture) as $proofFile) { if ($proofFile !== '.' && $proofFile !== '..' && is_file($proofFixture.'/'.$proofFile)) unlink($proofFixture.'/'.$proofFile); }
		rmdir($proofFixture);
	}
	$db->close(); $sourceDb->close();
}
