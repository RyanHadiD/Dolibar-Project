<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once __DIR__.'/../../class/namingseries.class.php';

class InterfaceNamingSeriesTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'NamingSeriesTriggers';
		$this->family = 'technic';
		$this->description = 'Persist the selected naming series on each document';
		$this->version = '0.5.0';
		$this->picto = 'barcode';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('namingseries')) return 0;
		$type = NamingSeries::documentTypeForObject($object);
		if ($type === '' || empty($object->id)) return 0;

		try {
			$engine = new NamingSeries($this->db, $conf->entity);
			if (substr($action, -7) === '_CREATE' && !empty($object->naming_series_code)) {
				$engine->saveDocumentChoice($type, (int) $object->id, (string) $object->naming_series_code, (int) $user->id);
			} elseif (substr($action, -7) === '_DELETE') {
				$engine->deleteDocumentChoice($type, (int) $object->id);
			}
			return 0;
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			$this->errors[] = $this->error;
			return -1;
		}
	}
}
