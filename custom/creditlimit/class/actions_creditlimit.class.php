<?php
require_once __DIR__.'/creditlimit.class.php';

class ActionsCreditLimit
{
	public $db;
	public $resprints = '';
	public $error = '';
	public $errors = array();
	public function __construct($db) { $this->db = $db; }

	public function doActions($parameters, &$object, &$action)
	{
		global $conf, $user;
		if (!isModEnabled('creditlimit') || GETPOST('confirm', 'alpha') !== 'yes') return 0;
		$actions = array('commande' => 'confirm_validate', 'shipping' => 'confirm_valid', 'facture' => 'confirm_valid');
		$element = $object->element ?? '';
		if (!isset($actions[$element]) || $action !== $actions[$element]) return 0;
		try {
			(new CreditLimitService($this->db, $conf->entity))->preflight($object, $user);
			return 0;
		} catch (Throwable $e) {
			$this->error = $e->getMessage(); $this->errors[] = $this->error;
			return -1;
		}
	}

	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $user;
		if (!isModEnabled('creditlimit') || !$user->hasRight('creditlimit', 'read') || !is_object($object)) return 0;
		if (($object->element ?? '') === 'societe') {
			if ((int) $object->id > 0 && !empty($object->client)) {
				print dolGetButtonAction('Lihat limit, exposure, overdue, dan kebijakan customer', 'Credit Control', 'default', dol_buildpath('/creditlimit/customer.php', 1).'?id='.(int) $object->id, '', true);
			}
			return 0;
		}
		if (!in_array(($object->element ?? ''), array('commande', 'shipping', 'facture'), true) || (int) ($object->id ?? 0) <= 0) return 0;
		if (($object->element ?? '') === 'facture' && (int) ($object->type ?? 0) === 2) return 0;
		$service = new CreditLimitService($this->db, $conf->entity);
		$socId = $service->customerId($object);
		if ($socId <= 0) return 0;
		print dolGetButtonAction('Lihat posisi kredit customer', 'Credit Control', 'default', dol_buildpath('/creditlimit/customer.php', 1).'?id='.$socId, '', true);
		$status = (int) ($object->status ?? $object->statut ?? 0);
		$check = $service->evaluate($object, $status === 0);
		if ($status === 0 && $check['blocked']) {
			$url = dol_buildpath('/creditlimit/override.php', 1).'?type='.urlencode($check['type']).'&id='.(int) $object->id;
			$help = implode('; ', $check['violations']);
			print dolGetButtonAction($help, 'Approve Credit Override', 'default', $url, '', $user->hasRight('creditlimit', 'approve'));
		}
		return 0;
	}
}
