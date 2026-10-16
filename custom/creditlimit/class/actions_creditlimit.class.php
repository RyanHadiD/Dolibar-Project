<?php
require_once __DIR__.'/creditlimit.class.php';
require_once __DIR__.'/customerorderproof.class.php';

class ActionsCreditLimit
{
	public $db;
	public $resprints = '';
	public $error = '';
	public $errors = array();
	public function __construct($db) { $this->db = $db; }

	public function doActions($parameters, &$object, &$action)
	{
		global $conf, $langs, $user;
		if (!isModEnabled('creditlimit')) return 0;

		if (($object->element ?? '') === 'commande' && $action === 'cl_upload_customer_proof') {
			$langs->load('creditlimit@creditlimit');
			if (!$user->hasRight('commande', 'creer')) {
				$this->error = $langs->trans('CLProofPermissionDenied');
				$this->errors[] = $this->error;
				return -1;
			}
			if ((int) ($object->id ?? 0) <= 0 || (int) ($object->status ?? $object->statut ?? 0) !== 0) {
				$this->error = $langs->trans('CLProofDraftOnly');
				$this->errors[] = $this->error;
				return -1;
			}
			if (empty($_FILES['cl_customer_proof']['name'])) {
				$this->error = $langs->trans('CLProofFileRequired');
				$this->errors[] = $this->error;
				return -1;
			}
			$uploadName = $_FILES['cl_customer_proof']['name'];
			if (!is_string($uploadName) || !preg_match('/\.(pdf|jpe?g|png|webp)$/i', $uploadName)) {
				$this->error = $langs->trans('CLProofInvalidFileType');
				$this->errors[] = $this->error;
				return -1;
			}

			$result = (new CustomerOrderProof($this->db))->upload($object);
			if ($result <= 0) {
				$this->error = $langs->trans('CLProofUploadFailed');
				$this->errors[] = $this->error;
				return -1;
			}
			setEventMessages($langs->trans('CLProofUploadSuccess'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
			exit;
		}

		if (GETPOST('confirm', 'alpha') !== 'yes') return 0;
		$actions = array('commande' => 'confirm_validate', 'shipping' => 'confirm_valid', 'facture' => 'confirm_valid');
		$element = $object->element ?? '';
		if (!isset($actions[$element]) || $action !== $actions[$element]) return 0;
		try {
			if ($element === 'commande' && !(new CustomerOrderProof($this->db))->hasProof($object)) {
				$langs->load('creditlimit@creditlimit');
				throw new DomainException($langs->trans('CLProofRequiredForValidation'));
			}
			(new CreditLimitService($this->db, $conf->entity))->preflight($object, $user);
			return 0;
		} catch (Throwable $e) {
			$this->error = $e->getMessage(); $this->errors[] = $this->error;
			return -1;
		}
	}

	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $langs, $user;
		if (!isModEnabled('creditlimit') || !is_object($object)) return 0;
		$langs->load('creditlimit@creditlimit');
		if (($object->element ?? '') === 'societe') {
			if ($user->hasRight('creditlimit', 'read') && (int) $object->id > 0 && !empty($object->client)) {
				print dolGetButtonAction('Lihat limit, exposure, overdue, dan kebijakan customer', 'Credit Control', 'default', dol_buildpath('/creditlimit/customer.php', 1).'?id='.(int) $object->id, '', true);
			}
			return 0;
		}
		if (($object->element ?? '') === 'commande' && (int) ($object->id ?? 0) > 0) {
			$this->printCustomerProofControl($object);
		}
		if (!$user->hasRight('creditlimit', 'read')) return 0;
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

	/** Print the proof status and upload control on a customer order card. */
	private function printCustomerProofControl($object)
	{
		global $langs, $user;
		$proof = new CustomerOrderProof($this->db);
		$files = $proof->files($object);
		$hasProof = count($files) > 0;
		$documentsUrl = DOL_URL_ROOT.'/commande/document.php?id='.(int) $object->id;

		if ($hasProof) {
			$label = $langs->trans('CLProofAttached').' ('.count($files).')';
			print dolGetButtonAction(implode(', ', $files), $label, 'default', $documentsUrl, '', true);
		} else {
			print dolGetButtonAction($langs->trans('CLProofRequiredForValidation'), $langs->trans('CLProofMissing'), 'default', '#', '', false);
		}

		$status = (int) ($object->status ?? $object->statut ?? 0);
		if ($status !== 0 || !$user->hasRight('commande', 'creer')) return;

		$formId = 'cl-customer-proof-form-'.(int) $object->id;
		$inputId = 'cl-customer-proof-'.(int) $object->id;
		print '<form id="'.dol_escape_htmltag($formId).'" class="inline-block" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.(int) $object->id.'" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="cl_upload_customer_proof">';
		print '<input id="'.dol_escape_htmltag($inputId).'" class="flat" type="file" name="cl_customer_proof" accept="application/pdf,image/jpeg,image/png,image/webp" required>';
		print '<button type="submit" class="butAction">'.dol_escape_htmltag($langs->trans($hasProof ? 'CLAddAnotherProof' : 'CLUploadProof')).'</button>';
		print '</form>';
	}
}
