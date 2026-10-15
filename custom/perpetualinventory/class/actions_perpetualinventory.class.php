<?php

require_once __DIR__.'/perpetualinventory.class.php';

/** Invoice-card actions that keep document and bookkeeping deletion atomic. */
class ActionsPerpetualInventory
{
	public $db;
	public $resprints = '';
	public $error = '';
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	private function invoiceType($object)
	{
		return in_array($object->element, array('facture_fourn', 'invoice_supplier'), true) ? 'supplier' : 'customer';
	}

	private function documentType($object)
	{
		$types = array(
			'commande' => 'customer_order',
			'order_supplier' => 'supplier_order',
			'payment' => 'customer_payment',
			'payment_supplier' => 'supplier_payment',
		);
		if (isset($types[$object->element])) {
			return $types[$object->element];
		}
		if ($object->element === 'shipping') {
			return 'shipment';
		}
		if ($object->element === 'reception') {
			return 'reception';
		}
		return $this->invoiceType($object) === 'supplier' ? 'supplier_invoice' : 'customer_invoice';
	}

	private function isStockDocument($object)
	{
		return in_array($object->element, array('shipping', 'reception'), true);
	}

	private function isOrderOrPayment($object)
	{
		return in_array($object->element, array('commande', 'order_supplier', 'payment', 'payment_supplier'), true);
	}

	private function printAuditButton($object)
	{
		global $user;
		if (!$user->hasRight('perpetualinventory', 'read')) {
			return;
		}
		$url = dol_buildpath('/perpetualinventory/audit.php', 1).'?type='.urlencode($this->documentType($object)).'&document_id='.(int) $object->id;
		print dolGetButtonAction('Lihat alasan pembatalan dan revisi', 'Riwayat Cancel/Revisi', 'default', $url, '', true);
	}

	/** Render lifecycle controls for a bookkeeping piece without depending on Print Format. */
	private function printJournalLifecycleButtons($object)
	{
		global $conf, $user;
		$pieceNum = (int) ($object->piece_num ?? GETPOSTINT('piece_num'));
		if ($pieceNum <= 0) {
			return;
		}
		$sql = 'SELECT MAX(date_validated) date_validated,MAX(date_export) date_export,';
		$sql .= "SUM(CASE WHEN COALESCE(doc_type,'') NOT IN ('','manual','various') THEN 1 ELSE 0 END) system_lines";
		$sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping WHERE entity='.(int) $conf->entity.' AND piece_num='.$pieceNum;
		$result = $this->db->query($sql);
		$row = $result ? $this->db->fetch_object($result) : null;
		if (($user->admin || $user->hasRight('accounting', 'mouvements', 'creer')) && $row && !empty($row->date_validated) && empty($row->date_export) && empty($row->system_lines)) {
			$url = dol_buildpath('/perpetualinventory/journal_action.php', 1).'?piece_num='.$pieceNum;
			print dolGetButtonAction('Simpan snapshot dan kembalikan jurnal manual ke Draft untuk direvisi', 'Cancel & Amend', 'default', $url, '', true);
		} elseif ($user->admin || $user->hasRight('accounting', 'mouvements', 'creer')) {
			if ($row && !empty($row->system_lines)) {
				$help = 'Journal otomatis harus direvisi melalui Customer/Vendor Invoice, Shipment, Reception, atau dokumen sumbernya agar ledger tetap konsisten.';
			} elseif ($row && !empty($row->date_export)) {
				$help = 'Journal sudah diekspor. Gunakan journal koreksi/reversal pada periode aktif.';
			} else {
				$help = 'Journal masih Draft dan dapat langsung diedit tanpa proses Cancel & Amend.';
			}
			print dolGetButtonAction($help, 'Cancel & Amend', 'default', '#', '', false);
		}
		if ($user->hasRight('perpetualinventory', 'read')) {
			$url = dol_buildpath('/perpetualinventory/audit.php', 1).'?type=accounting_journal&document_id='.$pieceNum;
			print dolGetButtonAction('Lihat alasan dan snapshot revisi jurnal', 'Riwayat Cancel/Revisi', 'default', $url, '', true);
		}
	}

	private function stockDocumentSnapshot($object)
	{
		$isShipment = $object->element === 'shipping';
		$documentTable = $isShipment ? 'expedition' : 'reception';
		$document = null;
		$resql = $this->db->query('SELECT * FROM '.$this->db->prefix().$documentTable.' WHERE rowid='.(int) $object->id);
		if ($resql) {
			$document = $this->db->fetch_object($resql);
		}
		$lines = array();
		$lineTable = $isShipment ? 'expeditiondet' : 'receptiondet_batch';
		$parentField = $isShipment ? 'fk_expedition' : 'fk_reception';
		$resql = $this->db->query('SELECT * FROM '.$this->db->prefix().$lineTable.' WHERE '.$parentField.'='.(int) $object->id.' ORDER BY rang,rowid');
		while ($resql && ($line = $this->db->fetch_object($resql))) {
			$lineData = get_object_vars($line);
			$lineData['batches'] = array();
			if ($isShipment) {
				$batches = $this->db->query('SELECT * FROM '.$this->db->prefix().'expeditiondet_batch WHERE fk_expeditiondet='.(int) $line->rowid.' ORDER BY rowid');
				while ($batches && ($batch = $this->db->fetch_object($batches))) {
					$lineData['batches'][] = get_object_vars($batch);
				}
			}
			$lines[] = $lineData;
		}
		$snapshot = json_encode(array('version' => 1, 'document' => $document ? get_object_vars($document) : array(), 'lines' => $lines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($snapshot === false) {
			throw new DomainException('Snapshot dokumen stok gagal dibuat.');
		}
		return $snapshot;
	}

	private function canDelete($object, $user)
	{
		if ($user->admin) {
			return true;
		}
		if ($this->invoiceType($object) === 'supplier') {
			return $user->hasRight('fournisseur', 'facture', 'supprimer') || $user->hasRight('supplier_invoice', 'supprimer');
		}
		return $user->hasRight('facture', 'supprimer');
	}

	private function canRevise($object, $user)
	{
		if ($user->admin) {
			return true;
		}
		if ($this->invoiceType($object) === 'supplier') {
			return $user->hasRight('fournisseur', 'facture', 'creer') || $user->hasRight('supplier_invoice', 'creer');
		}
		return $user->hasRight('facture', 'creer');
	}

	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $langs, $user;
		if (!isModEnabled('perpetualinventory') || (int) $object->id <= 0) {
			// BookKeeping cards identify a transaction by piece_num and may not
			// expose a conventional object id.
			if (!isModEnabled('perpetualinventory') || ($object->element ?? '') !== 'accountingbookkeeping') {
				return 0;
			}
		}
		if (($object->element ?? '') === 'accountingbookkeeping') {
			$this->printJournalLifecycleButtons($object);
			return 0;
		}
		if ($this->isStockDocument($object)) {
			$module = $object->element === 'shipping' ? 'expedition' : 'reception';
			$status = isset($object->status) ? (int) $object->status : (int) $object->statut;
			// Importing source lines is an edit of the current draft.  Do not show
			// this action on validated documents, and pass the current Reception so
			// the picker adds items here instead of opening another new document.
			if ($object->element === 'reception' && $status === 0 && ($user->admin || $user->hasRight($module, 'creer'))) {
				$target = $object->element === 'shipping' ? 'shipment' : 'reception';
				$source = $target === 'shipment' ? $langs->trans('CustomerOrder') : $langs->trans('SupplierOrder');
				$url = dol_buildpath('/perpetualinventory/get_items_from.php', 1).'?target='.$target.'&destination_id='.(int) $object->id;
				$label = $langs->trans($target === 'shipment' ? 'PIGetItemsFromSalesOrder' : 'PIGetItemsFromPurchaseOrder');
				print dolGetButtonAction($langs->trans('PIGetItemsFromHelp', $source), $label, 'default', $url, '', true);
			}
			if (($status > 0 || ($object->element === 'shipping' && $status === -1)) && ($user->admin || $user->hasRight($module, 'creer'))) {
				$actionName = $object->element === 'shipping' ? 'pi_amend_shipment' : 'pi_amend_reception';
				$url = $_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action='.$actionName.'&token='.newToken();
				$label = $status === -1 ? 'Amend → Draft' : 'Cancel & Amend';
				$help = $status === -1 ? 'Pulihkan baris Shipment canceled dan buka sebagai Draft' : 'Batalkan posting stok/jurnal dan buka dokumen untuk direvisi';
				print dolGetButtonAction($help, $label, 'default', $url, '', true);
			}
			$this->printAuditButton($object);
			return 0;
		}
		if ($this->isOrderOrPayment($object)) {
			$type = $this->documentType($object);
			$isPayment = strpos($type, 'payment') !== false;
			$status = isset($object->status) ? (int) $object->status : (int) $object->statut;
			if ($isPayment || $status !== 0) {
				$url = dol_buildpath('/perpetualinventory/document_action.php', 1).'?type='.urlencode($type).'&id='.(int) $object->id.'&token='.newToken();
				print dolGetButtonAction($isPayment ? 'Batalkan payment native, simpan snapshot audit, lalu buat payment pengganti' : 'Kembalikan dokumen ke Draft setelah memastikan tidak ada dokumen turunan aktif', $isPayment ? 'Revisi Payment' : 'Cancel & Amend', 'default', $url, '', true);
			}
			$this->printAuditButton($object);
			return 0;
		}
		if (!method_exists($object, 'getVentilExportCompta')) {
			return 0;
		}
		$invoiceType = $this->invoiceType($object);
		$status = isset($object->status) ? (int) $object->status : (int) $object->statut;
		if ($status === 0 && (int) $object->type === 0 && $this->canRevise($object, $user)) {
			$target = $invoiceType === 'supplier' ? 'supplier_invoice' : 'customer_invoice';
			$source = $invoiceType === 'supplier' ? $langs->trans('Reception') : $langs->trans('Shipment');
			$url = dol_buildpath('/perpetualinventory/get_invoice_items.php', 1).'?target='.$target.'&destination_id='.(int) $object->id;
			$label = $langs->trans($invoiceType === 'supplier' ? 'PIGetItemsFromReception' : 'PIGetItemsFromShipment');
			print dolGetButtonAction($langs->trans('PIGetInvoiceItemsHelp', $source), $label, 'default', $url, '', true);
		}
		$idKey = $this->invoiceType($object) === 'supplier' ? 'id' : 'facid';
		if ($this->canRevise($object, $user) && in_array((int) $object->status, array(1, 3), true)) {
			$type = $this->documentType($object);
			$url = dol_buildpath('/perpetualinventory/document_action.php', 1).'?type='.urlencode($type).'&id='.(int) $object->id.'&token='.newToken();
			$params = array('attr' => array('class' => 'classfortooltip', 'title' => 'Batalkan posting buku besar, simpan snapshot, lalu kembalikan invoice ke Draft'));
			print dolGetButtonAction('Batalkan posting, simpan snapshot audit, dan buka invoice untuk direvisi', 'Cancel & Amend', 'default', $url, '', true, $params);
		}
		if ($this->canDelete($object, $user)) {
			$url = $_SERVER['PHP_SELF'].'?'.$idKey.'='.(int) $object->id.'&action=pi_delete_ledger&token='.newToken();
			$params = array('attr' => array('class' => 'classfortooltip', 'title' => 'Hapus invoice beserta jurnal buku besar terkait'));
			print dolGetButtonAction('Hapus invoice dan ledger secara atomik', 'Hapus + Ledger', 'delete', $url, '', true, $params);
		}
		$this->printAuditButton($object);
		return 0;
	}

	/** Add an ERP-style source-document shortcut above shipment/reception lists. */
	public function printFieldPreListTitle($parameters, &$object, &$action)
	{
		global $langs, $user;
		$contexts = explode(':', isset($parameters['context']) ? (string) $parameters['context'] : '');
		$isReception = in_array('receptionlist', $contexts, true);
		$isShipment = in_array('expeditionlist', $contexts, true);
		if (!$isReception && !$isShipment) {
			return 0;
		}
		$module = $isShipment ? 'expedition' : 'reception';
		if (!$user->admin && !$user->hasRight($module, 'creer')) {
			return 0;
		}
		$target = $isShipment ? 'shipment' : 'reception';
		$url = dol_buildpath('/perpetualinventory/get_items_from.php', 1).'?target='.$target;
		$label = $langs->trans($isShipment ? 'PIGetItemsFromSalesOrder' : 'PIGetItemsFromPurchaseOrder');
		$this->resprints = '<div class="divsearchfield"><a class="butAction" href="'.dol_escape_htmltag($url).'">'.img_picto('', 'filter').' '.dol_escape_htmltag($label).'</a></div>';
		return 0;
	}

	public function formConfirm($parameters, &$object, &$action)
	{
		global $form;
		$this->resprints = '';
		$stockActions = array('pi_cancel_shipment', 'pi_amend_shipment', 'pi_amend_reception');
		if (!in_array($action, array_merge(array('pi_delete_ledger', 'pi_revise_ledger'), $stockActions), true)) {
			return 0;
		}
		if (in_array($action, $stockActions, true)) {
			$isCancel = $action === 'pi_cancel_shipment';
			$isShipment = $object->element === 'shipping';
			$isCanceledAmend = !$isCancel && $isShipment && (int) $object->status === -1;
			$questions = array(array(
				'type' => 'text',
				'name' => 'pi_reason',
				'label' => '<span class="fieldrequired">Alasan</span>',
				'value' => '',
				'morecss' => 'minwidth500',
			));
			$this->resprints = $form->formconfirm(
				$_SERVER['PHP_SELF'].'?id='.(int) $object->id,
				$isCancel ? 'Batalkan Shipment' : ($isCanceledAmend ? 'Amend Shipment Canceled' : 'Batalkan Posting untuk Revisi'),
				($isCancel ? 'Shipment akan dibatalkan dan stok/jurnalnya dibalik.' : ($isCanceledAmend ? 'Baris Shipment akan dipulihkan dan dokumen dibuka kembali sebagai Draft tanpa membalik stok untuk kedua kali.' : 'Posting stok dan jurnal akan dibatalkan, lalu dokumen dikembalikan ke Draft agar dapat direvisi.')).' Alasan akan disimpan dalam audit trail.',
				$isCancel ? 'pi_confirm_cancel_shipment' : ($isShipment ? 'pi_confirm_amend_shipment' : 'pi_confirm_amend_reception'),
				$questions,
				'no',
				1
			);
			return 0;
		}
		$idKey = $this->invoiceType($object) === 'supplier' ? 'id' : 'facid';
		$isRevise = $action === 'pi_revise_ledger';
		$questions = $isRevise ? array(array('type' => 'text', 'name' => 'pi_reason', 'label' => '<span class="fieldrequired">Alasan</span>', 'value' => '', 'morecss' => 'minwidth500')) : '';
		$this->resprints = $form->formconfirm(
			$_SERVER['PHP_SELF'].'?'.$idKey.'='.(int) $object->id,
			$isRevise ? 'Revisi Invoice + Ledger' : 'Hapus Invoice + Ledger',
			$isRevise
				? 'Posting buku besar invoice <b>'.dol_escape_htmltag($object->ref).'</b> akan dibatalkan dan invoice dikembalikan ke draft agar dapat direvisi. Lanjutkan?'
				: 'Invoice <b>'.dol_escape_htmltag($object->ref).'</b> dan seluruh jurnal buku besarnya akan dihapus permanen. Lanjutkan?',
			$isRevise ? 'pi_confirm_revise_ledger' : 'pi_confirm_delete_ledger',
			$questions,
			'no',
			1
		);
		return 0;
	}

	public function doActions($parameters, &$object, &$action)
	{
		global $conf, $user;
		// The native "New shipment/reception" menu opens the card without an
		// origin document.  In that case Dolibarr only displays an instruction
		// telling the user to go back to the order.  Route that entry point to
		// our outstanding-order picker instead, so users can start from the
		// stock menu and continue with the normal, fully linked native form.
		if (isModEnabled('perpetualinventory') && $action === 'create' && !GETPOST('origin', 'alpha') && empty($object->id)) {
			$target = '';
			$permissionModule = '';
			if ($object->element === 'shipping') {
				$target = 'shipment';
				$permissionModule = 'expedition';
			} elseif ($object->element === 'reception') {
				$target = 'reception';
				$permissionModule = 'reception';
			}
			if ($target !== '' && ($user->admin || $user->hasRight($permissionModule, 'creer'))) {
				header('Location: '.dol_buildpath('/perpetualinventory/get_items_from.php', 1).'?target='.$target);
				exit;
			}
		}
		if (isModEnabled('perpetualinventory') && in_array($object->element, array('commande', 'order_supplier'), true) && in_array($action, array('cancel', 'confirm_cancel', 'modif', 'confirm_modif', 'reopen'), true)) {
			$type = $this->documentType($object);
			header('Location: '.dol_buildpath('/perpetualinventory/document_action.php', 1).'?type='.urlencode($type).'&id='.(int) $object->id.'&token='.newToken());
			exit;
		}
		if (isModEnabled('perpetualinventory') && in_array($object->element, array('payment', 'payment_supplier'), true) && $action === 'delete') {
			$type = $this->documentType($object);
			header('Location: '.dol_buildpath('/perpetualinventory/document_action.php', 1).'?type='.urlencode($type).'&id='.(int) $object->id.'&token='.newToken());
			exit;
		}
		if (isModEnabled('perpetualinventory') && in_array($object->element, array('facture', 'facture_fourn', 'invoice', 'invoice_supplier'), true) && in_array($action, array('modif', 'confirm_modif', 'edit', 'confirm_edit', 'canceled', 'confirm_canceled'), true) && (int) $object->status !== 0) {
			$type = $this->documentType($object);
			header('Location: '.dol_buildpath('/perpetualinventory/document_action.php', 1).'?type='.urlencode($type).'&id='.(int) $object->id.'&token='.newToken());
			exit;
		}
		if (isModEnabled('perpetualinventory') && $object->element === 'shipping') {
			// A confirmation page opened before this module update may still post
			// the native action without a reason. Route it back through our form.
			if ($action === 'confirm_cancel') {
				$action = 'pi_cancel_shipment';
				return 0;
			}
			if ($action === 'cancel') {
				$action = 'pi_cancel_shipment';
				return 0;
			}
			if ($action === 'setdraft') {
				$action = 'pi_amend_shipment';
				return 0;
			}
		}
		if (isModEnabled('perpetualinventory') && $object->element === 'reception' && in_array($action, array('modif', 'annuler'), true)) {
			$action = 'pi_amend_reception';
			return 0;
		}

		$stockConfirmActions = array('pi_confirm_cancel_shipment', 'pi_confirm_amend_shipment', 'pi_confirm_amend_reception');
		if (in_array($action, $stockConfirmActions, true) && GETPOST('confirm', 'alpha') === 'yes') {
			if (!$user->hasRight($object->element === 'shipping' ? 'expedition' : 'reception', 'creer')) {
				$this->error = 'Tidak memiliki izin membatalkan atau merevisi dokumen.';
				return -1;
			}
			try {
				$reason = trim(GETPOST('pi_reason', 'restricthtml'));
				$pi = new PerpetualInventory($this->db, $object->entity ?: $conf->entity);
				// Validate first so a missing reason cannot start any stock mutation.
				if (mb_strlen($reason) < 5) {
					throw new DomainException('Alasan pembatalan/revisi wajib diisi minimal 5 karakter.');
				}
				$pi->transaction(function () use ($action, $object, $user, $pi, $reason) {
					$snapshot = $this->stockDocumentSnapshot($object);
					if ($action === 'pi_confirm_cancel_shipment') {
						$result = $object->cancel($user, 0, false);
						if ($result <= 0 || $object->setStatut(-1) < 0) {
							throw new DomainException($object->error ?: 'Shipment gagal dibatalkan.');
						}
						$pi->recordDocumentAction('shipment', $object->id, $object->ref, 'cancel', $reason, $user, $snapshot);
						return;
					}
					if ($object->element === 'shipping' && (int) $object->status === -1) {
						$result = $pi->amendCanceledShipment($object, $user);
					} else {
						$result = $object->element === 'shipping' ? $object->setDraft($user, 0) : $object->setDraft($user);
					}
					if ($result < 0) {
						throw new DomainException($object->error ?: 'Dokumen gagal dikembalikan ke Draft.');
					}
					$pi->recordDocumentAction($this->documentType($object), $object->id, $object->ref, 'amend', $reason, $user, $snapshot);
				});
				setEventMessages('Dokumen berhasil diproses. Alasan telah disimpan pada audit trail.', null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?id='.(int) $object->id);
				exit;
			} catch (Throwable $e) {
				$this->error = $e->getMessage();
				$this->errors[] = $this->error;
				$action = '';
				return -1;
			}
		}
		if (!in_array($action, array('pi_confirm_delete_ledger', 'pi_confirm_revise_ledger'), true) || GETPOST('confirm', 'alpha') !== 'yes') {
			return 0;
		}
		$isRevise = $action === 'pi_confirm_revise_ledger';
		if (!isModEnabled('perpetualinventory') || ($isRevise ? !$this->canRevise($object, $user) : !$this->canDelete($object, $user))) {
			$this->error = 'Tidak memiliki izin untuk '.($isRevise ? 'merevisi' : 'menghapus').' invoice dan ledger.';
			return -1;
		}
		try {
			$pi = new PerpetualInventory($this->db, $object->entity ?: $conf->entity);
			if ($isRevise) {
				$reason = trim(GETPOST('pi_reason', 'restricthtml'));
				if (mb_strlen($reason) < 5) {
					throw new DomainException('Alasan pembatalan/revisi wajib diisi minimal 5 karakter.');
				}
				$pi->transaction(function () use ($pi, $object, $user, $reason) {
					$pi->reviseInvoiceWithLedger($object, $user);
					$pi->recordDocumentAction($this->documentType($object), $object->id, $object->ref, 'amend', $reason, $user);
				});
				$idKey = $this->invoiceType($object) === 'supplier' ? 'id' : 'facid';
				header('Location: '.$_SERVER['PHP_SELF'].'?'.$idKey.'='.(int) $object->id);
				exit;
			}
			$pi->deleteInvoiceWithLedger($object, $user);
			$target = $this->invoiceType($object) === 'supplier' ? DOL_URL_ROOT.'/fourn/facture/list.php?restore_lastsearch_values=1' : DOL_URL_ROOT.'/compta/facture/list.php?restore_lastsearch_values=1';
			header('Location: '.$target);
			exit;
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			$this->errors[] = $this->error;
			$action = '';
			return -1;
		}
	}
}
