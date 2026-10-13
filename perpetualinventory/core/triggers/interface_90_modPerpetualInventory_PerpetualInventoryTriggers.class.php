<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once __DIR__.'/../../class/perpetualinventory.class.php';

class InterfacePerpetualInventoryTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = 'PerpetualInventoryTriggers';
		$this->family = 'financial';
		$this->description = 'Atomic inventory valuation and stock journals';
		$this->version = '1.0.0';
		$this->picto = 'stock';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('perpetualinventory')) {
			return 0;
		}
		$pi = new PerpetualInventory($this->db, $conf->entity);
		try {
			$c = $pi->config();
			if (!$c || !$c->enabled) {
				return 0;
			}
			if ($action === 'STOCK_MOVEMENT') {
				$pi->onMovement($object, $user);
			}
			if (in_array($action, array('STOCK_MOVEMENT_DELETE', 'STOCK_MOVEMENT_MODIFY', 'MOUVEMENTSTOCK_DELETE', 'MOUVEMENTSTOCK_MODIFY'), true)) {
				throw new DomainException('Buku stok bersifat permanen. Gunakan operasi Pembalik.');
			}
			if (in_array($action, array('RECEPTION_VALIDATE', 'SHIPPING_VALIDATE'), true)) {
				$pi->validateWorkflow();
			}
			if (in_array($action, array('PRODUCT_DELETE', 'WAREHOUSE_DELETE'), true)) {
				$field = $action === 'PRODUCT_DELETE' ? 'fk_product' : 'fk_warehouse';
				if ($pi->one('SELECT rowid FROM '.$pi->table('pi_entry').' WHERE entity='.$pi->entity.' AND '.$field.'='.(int) $object->id.' LIMIT 1')) {
					throw new DomainException('Barang/gudang memiliki riwayat buku stok dan tidak dapat dihapus.');
				}
			}
			if ($action === 'BILL_SUPPLIER_VALIDATE') {
				$pi->validateWorkflow();
				$pi->autoMatchInvoice($object->id, $user);
				$pi->checkInvoice($object->id, true);
			}
			if (in_array($action, array('BILL_SUPPLIER_DELETE', 'BILL_SUPPLIER_CANCEL', 'BILL_SUPPLIER_UNVALIDATE'), true)) {
				$pi->cancelInvoiceAccounting('supplier', $object->id);
			}
			if (in_array($action, array('BILL_DELETE', 'BILL_CANCEL', 'BILL_UNVALIDATE'), true)) {
				$pi->cancelInvoiceAccounting('customer', $object->id);
			}
			if (in_array($action, array('PAYMENT_CUSTOMER_DELETE', 'PAYMENT_SUPPLIER_DELETE'), true)) {
				$reason = trim(GETPOST('reason', 'restricthtml'));
				if (mb_strlen($reason) < 5) {
					throw new DomainException('Gunakan Cancel & Recreate dan isi alasan minimal 5 karakter untuk membatalkan payment.');
				}
			}
			if ($action === 'BOOKKEEPING_CREATE' && $object->doc_type === 'supplier_invoice') {
				$pi->checkInvoice($object->fk_doc);
			}
			if (in_array($action, array('BOOKKEEPING_MODIFY', 'BOOKKEEPING_DELETE'), true) && in_array($object->doc_type, array('perpetualinventory', 'reception', 'shipment'), true)) {
				$entryId = $object->doc_type === 'perpetualinventory' ? $object->fk_doc : $object->fk_docdet;
				$entry = $pi->one('SELECT rowid FROM '.$pi->table('pi_entry').' WHERE entity='.$pi->entity.' AND journal_posted=1 AND rowid='.(int) $entryId);
				if ($entry) {
					throw new DomainException('Jurnal persediaan otomatis tidak dapat dihapus/diubah. Gunakan pembalik stok.');
				}
			}
			if (in_array($action, array('SHIPPING_DELETE', 'SHIPPING_CANCEL'), true)) {
				$pi->prepareDocumentRollback('shipping', $object->id);
			}
			if (in_array($action, array('RECEPTION_DELETE', 'RECEPTION_UNVALIDATE'), true)) {
				$pi->assertDocumentRollbackComplete('reception', $object->id);
			}
			if ($action === 'SHIPMENT_UNVALIDATE') {
				$pi->unvalidateShipment($object, $user);
			}
			return 0;
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			$this->errors[] = $this->error;
			$object->error = $this->error;
			$object->errors[] = $this->error;
			dol_syslog('PerpetualInventory: '.$this->error, LOG_ERR);
			return -1;
		}
	}
}
