<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once __DIR__.'/../../class/creditlimit.class.php';

class InterfaceCreditLimitTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'CreditLimitTriggers';
		$this->family = 'financial';
		$this->description = 'Block customer sales documents that breach credit policy';
		$this->version = '1.0.0';
		$this->picto = 'payment';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('creditlimit') || !in_array($action, array('ORDER_VALIDATE', 'SHIPPING_VALIDATE', 'BILL_VALIDATE'), true)) return 0;
		try {
			(new CreditLimitService($this->db, $conf->entity))->enforce($object, $user);
			return 0;
		} catch (Throwable $e) {
			$this->error = $e->getMessage(); $this->errors[] = $this->error;
			$object->error = $this->error; $object->errors[] = $this->error;
			dol_syslog('CreditLimit: '.$this->error, LOG_ERR);
			return -1;
		}
	}
}
