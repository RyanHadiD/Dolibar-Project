<?php
require_once __DIR__.'/lib/common.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

$type = GETPOST('type', 'alpha');
$id = GETPOSTINT('id');
$definitions = array(
	'customer_order' => array('class' => 'Commande', 'right' => array('commande', 'creer'), 'label' => 'Sales Order', 'return' => DOL_URL_ROOT.'/commande/card.php?id='),
	'supplier_order' => array('class' => 'CommandeFournisseur', 'right' => array('fournisseur', 'commande', 'creer'), 'label' => 'Purchase Order', 'return' => DOL_URL_ROOT.'/fourn/commande/card.php?id='),
	'customer_payment' => array('class' => 'Paiement', 'right' => array('facture', 'paiement'), 'label' => 'Customer Payment', 'return' => DOL_URL_ROOT.'/compta/paiement/list.php'),
	'supplier_payment' => array('class' => 'PaiementFourn', 'right' => array('fournisseur', 'facture', 'supprimer'), 'label' => 'Supplier Payment', 'return' => DOL_URL_ROOT.'/fourn/paiement/list.php'),
);
if (!$id || !isset($definitions[$type])) {
	accessforbidden('Jenis dokumen atau ID tidak valid.');
}
$definition = $definitions[$type];
if (!$user->admin && !call_user_func_array(array($user, 'hasRight'), $definition['right'])) {
	accessforbidden();
}
$class = $definition['class'];
$object = new $class($db);
if ($object->fetch($id) <= 0 || (!empty($object->entity) && (int) $object->entity !== (int) $conf->entity)) {
	accessforbidden('Dokumen tidak ditemukan pada entitas aktif.');
}

function piLifecycleSnapshot($type, $object, $pi)
{
	$tables = array(
		'customer_order' => 'commande',
		'supplier_order' => 'commande_fournisseur',
		'customer_payment' => 'paiement',
		'supplier_payment' => 'paiementfourn',
	);
	$document = $pi->one('SELECT * FROM '.$pi->table($tables[$type]).' WHERE rowid='.(int) $object->id);
	if ($type === 'customer_payment') {
		$allocations = $pi->rows('SELECT * FROM '.$pi->table('paiement_facture').' WHERE fk_paiement='.(int) $object->id);
	} elseif ($type === 'supplier_payment') {
		$allocations = $pi->rows('SELECT * FROM '.$pi->table('paiementfourn_facturefourn').' WHERE fk_paiementfourn='.(int) $object->id);
	} else {
		$allocations = array();
	}
	if ($type === 'customer_order') {
		$lines = $pi->rows('SELECT * FROM '.$pi->table('commandedet').' WHERE fk_commande='.(int) $object->id.' ORDER BY rang,rowid');
	} elseif ($type === 'supplier_order') {
		$lines = $pi->rows('SELECT * FROM '.$pi->table('commande_fournisseurdet').' WHERE fk_commande='.(int) $object->id.' ORDER BY rang,rowid');
	} else {
		$lines = array();
	}
	$snapshot = json_encode(array('version' => 1, 'document' => $document ? get_object_vars($document) : array(), 'lines' => array_map('get_object_vars', $lines), 'allocations' => array_map('get_object_vars', $allocations)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($snapshot === false) {
		throw new DomainException('Snapshot dokumen gagal dibuat.');
	}
	return $snapshot;
}

function piAssertNoActiveChildren($type, $id, $pi)
{
	if ($type === 'customer_order') {
		$child = $pi->one('SELECT s.ref FROM '.$pi->table('element_element').' ee JOIN '.$pi->table('expedition').' s ON s.rowid=ee.fk_target AND s.entity='.$pi->entity.' WHERE ee.sourcetype='.$pi->str('commande').' AND ee.fk_source='.(int) $id.' AND ee.targettype='.$pi->str('shipping').' AND s.fk_statut>0 LIMIT 1');
		if ($child) throw new DomainException('Masih ada Shipment aktif '.$child->ref.'. Cancel/Amend Shipment tersebut terlebih dahulu.');
		$child = $pi->one('SELECT f.ref FROM '.$pi->table('element_element').' ee JOIN '.$pi->table('facture').' f ON f.rowid=ee.fk_target AND f.entity='.$pi->entity.' WHERE ee.sourcetype='.$pi->str('commande').' AND ee.fk_source='.(int) $id.' AND ee.targettype='.$pi->str('facture').' AND f.fk_statut IN (1,2) LIMIT 1');
		if ($child) throw new DomainException('Masih ada Customer Invoice aktif '.$child->ref.'. Cancel/Amend invoice tersebut terlebih dahulu.');
	}
	if ($type === 'supplier_order') {
		$child = $pi->one('SELECT r.ref FROM '.$pi->table('element_element').' ee JOIN '.$pi->table('reception').' r ON r.rowid=ee.fk_target AND r.entity='.$pi->entity.' WHERE ee.sourcetype IN ('.$pi->str('order_supplier').','.$pi->str('supplier_order').') AND ee.fk_source='.(int) $id.' AND ee.targettype='.$pi->str('reception').' AND r.fk_statut>0 LIMIT 1');
		if ($child) throw new DomainException('Masih ada Reception aktif '.$child->ref.'. Cancel/Amend Reception tersebut terlebih dahulu.');
		$child = $pi->one('SELECT f.ref FROM '.$pi->table('element_element').' ee JOIN '.$pi->table('facture_fourn').' f ON f.rowid=ee.fk_target AND f.entity='.$pi->entity.' WHERE ee.sourcetype='.$pi->str('order_supplier').' AND ee.fk_source='.(int) $id.' AND ee.targettype='.$pi->str('invoice_supplier').' AND f.fk_statut IN (1,2) LIMIT 1');
		if ($child) throw new DomainException('Masih ada Vendor Invoice aktif '.$child->ref.'. Cancel/Amend invoice tersebut terlebih dahulu.');
	}
}

function piReopenPaidInvoices($type, $paymentId, $pi, $user)
{
	if ($type === 'customer_payment') {
		$links = $pi->rows('SELECT DISTINCT fk_facture invoice_id FROM '.$pi->table('paiement_facture').' WHERE fk_paiement='.(int) $paymentId);
		$class = 'Facture';
	} else {
		$links = $pi->rows('SELECT DISTINCT fk_facturefourn invoice_id FROM '.$pi->table('paiementfourn_facturefourn').' WHERE fk_paiementfourn='.(int) $paymentId);
		$class = 'FactureFournisseur';
	}
	foreach ($links as $link) {
		$invoice = new $class($pi->db);
		if ($invoice->fetch((int) $link->invoice_id) <= 0) {
			throw new DomainException('Invoice terkait payment tidak ditemukan.');
		}
		if (!empty($invoice->paye) || (int) $invoice->status > 1) {
			if ($invoice->setUnpaid($user) < 0) {
				throw new DomainException($invoice->error ?: 'Invoice terkait gagal dibuka kembali.');
			}
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	piPost();
	$reason = trim(GETPOST('reason', 'restricthtml'));
	if (mb_strlen($reason) < 5) {
		setEventMessages('Alasan wajib diisi minimal 5 karakter.', null, 'errors');
	} else {
		try {
			$isPayment = strpos($type, 'payment') !== false;
			$replacementUrl = '';
			if ($type === 'customer_payment') {
				$firstInvoice = $pi->one('SELECT fk_facture invoice_id FROM '.$pi->table('paiement_facture').' WHERE fk_paiement='.(int) $object->id.' ORDER BY rowid LIMIT 1');
				if ($firstInvoice) $replacementUrl = DOL_URL_ROOT.'/compta/paiement.php?facid='.(int) $firstInvoice->invoice_id.'&action=create';
			} elseif ($type === 'supplier_payment') {
				$firstInvoice = $pi->one('SELECT fk_facturefourn invoice_id FROM '.$pi->table('paiementfourn_facturefourn').' WHERE fk_paiementfourn='.(int) $object->id.' ORDER BY rowid LIMIT 1');
				if ($firstInvoice) $replacementUrl = DOL_URL_ROOT.'/fourn/facture/paiement.php?facid='.(int) $firstInvoice->invoice_id.'&action=create';
			}
			$pi->transaction(function () use ($type, $object, $user, $pi, $reason, $isPayment) {
				$snapshot = piLifecycleSnapshot($type, $object, $pi);
				if ($isPayment) {
					piReopenPaidInvoices($type, $object->id, $pi, $user);
					$result = $object->delete($user);
					if ($result <= 0) throw new DomainException($object->error ?: implode('; ', (array) $object->errors));
					$pi->recordDocumentAction($type, $object->id, $object->ref, 'cancel', $reason, $user, $snapshot);
					return;
				}
				piAssertNoActiveChildren($type, $object->id, $pi);
				if ($type === 'customer_order') {
					if ((int) $object->status < 0) {
						$pi->q('UPDATE '.$pi->table('commande').' SET fk_statut=0,fk_user_modif='.(int) $user->id.' WHERE entity='.$pi->entity.' AND rowid='.(int) $object->id);
						$object->status = $object->statut = 0;
						$result = $object->call_trigger('ORDER_UNVALIDATE', $user) < 0 ? -1 : 1;
					} else {
						$result = $object->setDraft($user, -1);
					}
				} else {
					$result = $object->setStatus($user, CommandeFournisseur::STATUS_DRAFT);
				}
				if ($result < 0) throw new DomainException($object->error ?: implode('; ', (array) $object->errors));
				$pi->recordDocumentAction($type, $object->id, $object->ref, 'amend', $reason, $user, $snapshot);
			});
			setEventMessages($isPayment ? 'Payment dibatalkan. Gunakan snapshot audit sebagai referensi untuk membuat payment pengganti.' : 'Dokumen berhasil dikembalikan ke Draft untuk direvisi.', null, 'mesgs');
			$target = ($isPayment && $replacementUrl) ? $replacementUrl : $definition['return'].($isPayment ? '' : (int) $object->id);
			header('Location: '.$target);
			exit;
		} catch (Throwable $e) {
			setEventMessages($e->getMessage(), null, 'errors');
		}
	}
}

piHeader(($type === 'customer_payment' || $type === 'supplier_payment') ? 'Cancel & Recreate '.$definition['label'] : 'Cancel & Amend '.$definition['label']);
print '<div class="fichecenter"><div class="underbanner clearboth"></div><div class="info">Dokumen: <b>'.piEscape($object->ref ?: '#'.$object->id).'</b>. Sistem akan memeriksa dokumen turunan, rekonsiliasi bank, pembayaran, stok, dan ledger melalui aturan native sebelum perubahan dilakukan.</div>';
print '<form method="post" class="pi-form">';
piToken('confirm');
print '<input type="hidden" name="type" value="'.piEscape($type).'"><input type="hidden" name="id" value="'.(int) $id.'">';
print '<label for="reason">Alasan pembatalan/revisi</label><textarea class="minwidth500" id="reason" name="reason" rows="4" minlength="5" maxlength="1000" required>'.piEscape(GETPOST('reason', 'restricthtml')).'</textarea>';
$isPaymentPage = strpos($type, 'payment') !== false;
print '<p class="pi-muted">'.($isPaymentPage ? 'Payment akan dihapus secara native dan harus dibuat ulang. Payment dengan bank line yang sudah direkonsiliasi tidak dapat dibatalkan.' : 'Dokumen akan kembali ke Draft. Selesaikan dokumen turunannya terlebih dahulu bila sistem menolak.').'</p>';
print '<button class="button button-save" type="submit">Konfirmasi</button> <a class="button" href="'.piEscape($definition['return'].($isPaymentPage ? '' : (int) $object->id)).'">Kembali</a></form></div>';
piEnd();
