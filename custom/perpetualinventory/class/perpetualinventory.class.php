<?php
require_once __DIR__.'/pivaluation.class.php';

/** Entity-scoped ledger. All writers lock pi_config and run inside the caller transaction. */
class PerpetualInventory
{
	public $db;
	public $entity;
	public static $context = null;
	public static $documentRollback = null;

	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	public function q($sql)
	{
		$r = $this->db->query($sql);
		if (!$r) {
			throw new RuntimeException($this->db->lasterror());
		}
		return $r;
	}

	public function table($name)
	{
		return $this->db->prefix().$name;
	}

	public function str($s)
	{
		return "'".$this->db->escape((string) $s)."'";
	}

	public function one($sql)
	{
		return $this->db->fetch_object($this->q($sql));
	}

	public function rows($sql)
	{
		$r = $this->q($sql);
		$rows = array();
		while ($o = $this->db->fetch_object($r)) {
			$rows[] = $o;
		}
		return $rows;
	}

	public function config($lock = false)
	{
		return $this->one('SELECT * FROM '.$this->table('pi_config').' WHERE entity='.$this->entity.($lock ? ' FOR UPDATE' : ''));
	}

	public function transaction($callback)
	{
		$this->db->begin();
		try {
			$result = $callback();
			if (!$this->db->commit()) {
				throw new RuntimeException('Commit database gagal: '.$this->db->lasterror());
			}
			return $result;
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	public function account($number)
	{
		$a = $this->one('SELECT rowid, account_number, label FROM '.$this->table('accounting_account').' WHERE entity='.$this->entity.' AND active=1 AND fk_pcg_version='.$this->str($this->chartVersion()).' AND account_number='.$this->str($number));
		if (!$a) {
			throw new DomainException('Akun aktif tidak ditemukan: '.$number);
		}
		return $a;
	}

	public function chartVersion()
	{
		$chart = $this->one('SELECT pcg_version FROM '.$this->table('accounting_system').' WHERE rowid='.(int) getDolGlobalInt('CHARTOFACCOUNTS'));
		if (!$chart) {
			throw new DomainException('Pilih bagan akun aktif pada pengaturan Akuntansi Dolibarr dahulu.');
		}
		return $chart->pcg_version;
	}

	public function validateConfig($c)
	{
		if (!$c) {
			throw new DomainException('Simpan pengaturan akun Perpetual Inventory dahulu.');
		}
		$numbers = array();
		foreach (array('stock_account', 'grni_account', 'cogs_account', 'adjustment_account', 'opening_account') as $f) {
			$this->account($c->$f);
			$numbers[] = $c->$f;
		}
		if (count(array_unique($numbers)) != count($numbers)) {
			throw new DomainException('Gunakan akun berbeda untuk setiap fungsi akuntansi.');
		}
		if (!$this->one('SELECT rowid FROM '.$this->table('accounting_journal').' WHERE entity='.$this->entity.' AND active=1 AND code='.$this->str($c->journal_code))) {
			throw new DomainException('Pilih jurnal akuntansi yang aktif.');
		}
	}

	public function validateWorkflow()
	{
		$problems = array();
		if (!isModEnabled('stock') || !isModEnabled('accounting')) {
			$problems[] = 'aktifkan modul Stok dan Akuntansi double-entry';
		}
		if (!getDolGlobalInt('STOCK_CALCULATE_ON_RECEPTION')) {
			$problems[] = 'aktifkan perubahan stok saat validasi Reception';
		}
		if (!getDolGlobalInt('STOCK_CALCULATE_ON_SHIPMENT')) {
			$problems[] = 'aktifkan perubahan stok saat validasi Shipment';
		}
		foreach (array('STOCK_CALCULATE_ON_BILL', 'STOCK_CALCULATE_ON_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_SHIPMENT_CLOSE', 'STOCK_CALCULATE_ON_SUPPLIER_BILL', 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_RECEPTION_CLOSE', 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH') as $key) {
			if (getDolGlobalInt($key)) {
				$problems[] = 'nonaktifkan '.$key;
			}
		}
		if ($problems) {
			throw new DomainException('Pengaturan alur stok belum kompatibel: '.implode('; ', $problems).'.');
		}
	}

	public function saveConfig($data, $user)
	{
		if (!$user->admin) {
			throw new DomainException('Hanya administrator dapat mengubah pengaturan.');
		}
		return $this->transaction(function () use ($data, $user) {
			$c = $this->config(true);
			$n = (object) $data;
			$this->validateConfig($n);
			$hasEntries = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' LIMIT 1');
			$sets = array();
			foreach (array('stock_account', 'grni_account', 'cogs_account', 'adjustment_account', 'opening_account', 'journal_code') as $f) {
				if ($hasEntries && $c->$f !== $n->$f) {
					throw new DomainException('Akun dan jurnal dikunci setelah ada buku stok.');
				}
				$sets[] = $f.'='.$this->str($n->$f);
			}
			if ($c && $c->lock_date && (!$n->lock_date || $n->lock_date < $c->lock_date)) {
				throw new DomainException('Tanggal kunci tidak boleh dimundurkan.');
			}
			$sets[] = 'lock_date='.($n->lock_date ? $this->str($n->lock_date) : 'NULL');
			$sets[] = 'fk_user='.(int) $user->id;
			if (!$c) {
				$this->q('INSERT INTO '.$this->table('pi_config').' (entity) VALUES ('.$this->entity.')');
			}
			$this->q('UPDATE '.$this->table('pi_config').' SET '.implode(',', $sets).' WHERE entity='.$this->entity);
		});
	}

	public function start($user)
	{
		if (!$user->admin) {
			throw new DomainException('Hanya administrator dapat memulai posting.');
		}
		$this->transaction(function () {
			$c = $this->config(true);
			$this->validateConfig($c);
			$this->validateWorkflow();
			if ($c->enabled) {
				return;
			}
			$r = $this->reconciliation();
			$problems = array();
			if ($r['quantity_errors']) {
				$problems[] = count($r['quantity_errors']).' pasangan barang/gudang belum memiliki saldo awal yang sama dengan stok Dolibarr';
			}
			if (abs($r['difference']) > 0.005) {
				$problems[] = 'nilai buku stok '.price($r['ledger']).' tidak sama dengan saldo akun Persediaan '.price($r['gl']).' (selisih '.price($r['difference']).')';
			}
			if (abs($r['grni_difference']) > 0.005) {
				$problems[] = 'saldo GRNI berbeda '.price($r['grni_difference']);
			}
			if ($problems) {
				throw new DomainException('Perpetual Inventory belum dapat dimulai: '.implode('; ', $problems).'. Perbaiki melalui Saldo Awal dan Rekonsiliasi; proses mulai tidak membuat jurnal koreksi otomatis.');
			}
			$this->q('UPDATE '.$this->table('pi_config').' SET enabled=1,start_date='.$this->str($this->db->idate(dol_now())).' WHERE entity='.$this->entity);
		});
	}

	public function productWarehouse($product, $warehouse)
	{
		$p = $this->one('SELECT rowid, ref, entity, fk_product_type, stockable_product, tobatch FROM '.$this->table('product').' WHERE rowid='.(int) $product.' AND entity='.$this->entity);
		$w = $this->one('SELECT rowid FROM '.$this->table('entrepot').' WHERE rowid='.(int) $warehouse.' AND entity='.$this->entity);
		if (!$p || !$w || $p->fk_product_type != 0 || $p->stockable_product != 1) {
			throw new DomainException('Barang stok dan gudang harus milik entitas aktif.');
		}
		if (getDolGlobalInt('PRODUIT_SOUSPRODUITS') && $this->one('SELECT rowid FROM '.$this->table('product_association').' WHERE fk_product_pere='.(int) $product.' OR fk_product_fils='.(int) $product.' LIMIT 1')) {
			throw new DomainException('Barang '.$p->ref.' merupakan induk/komponen kit. Transaksi kit belum didukung; barang biasa tetap dapat memakai Perpetual Inventory.');
		}
		return $p;
	}

	/** Use the product purchase accounting code as its COGS account, with the module account as fallback. */
	public function productCogsAccount($product, $c)
	{
		if (getDolGlobalString('MAIN_PRODUCT_PERENTITY_SHARED')) {
			$p = $this->one('SELECT accountancy_code_buy FROM '.$this->table('product_perentity').' WHERE fk_product='.(int) $product.' AND entity='.$this->entity);
		} else {
			$p = $this->one('SELECT accountancy_code_buy FROM '.$this->table('product').' WHERE rowid='.(int) $product.' AND entity='.$this->entity);
		}
		$account = trim((string) ($p ? $p->accountancy_code_buy : ''));
		if ($account === '' || $account === '-1') {
			return $c->cogs_account;
		}
		$this->account($account);
		foreach (array('stock_account', 'grni_account', 'adjustment_account', 'opening_account') as $field) {
			if ($account === $c->$field) {
				throw new DomainException('Akun purchase/HPP produk tidak boleh memakai akun '.$account.' yang sudah digunakan untuk fungsi '.$field.'.');
			}
		}
		return $account;
	}

	public function balance($product, $warehouse)
	{
		$b = $this->one('SELECT * FROM '.$this->table('pi_balance').' WHERE entity='.$this->entity.' AND fk_product='.(int) $product.' AND fk_warehouse='.(int) $warehouse.' FOR UPDATE');
		if (!$b) {
			$this->q('INSERT INTO '.$this->table('pi_balance').' (entity,fk_product,fk_warehouse) VALUES ('.$this->entity.','.(int) $product.','.(int) $warehouse.')');
			return $this->balance($product, $warehouse);
		}
		return $b;
	}

	public function checkDate($date, $c, $b, $allowHistorical = false)
	{
		$stamp = $this->db->idate($date);
		$beforeLedger = ($c->enabled && $stamp < $c->start_date) || ($b->last_date && $stamp < $b->last_date);
		if (!$date || $date > dol_now() + 60 || ($c->lock_date && substr($stamp, 0, 10) <= $c->lock_date) || (!$allowHistorical && $beforeLedger)) {
			throw new DomainException('Tanggal transaksi terkunci, mundur, atau di masa depan.');
		}
	}

	/** Return the business date of a native Reception/Shipment movement. */
	private function movementPostingDate($movement)
	{
		$date = null;
		if ($movement->origintype === 'reception' && (int) $movement->fk_origin > 0) {
			$document = $this->one('SELECT COALESCE(date_reception,date_delivery) posting_date FROM '.$this->table('reception').' WHERE entity='.$this->entity.' AND rowid='.(int) $movement->fk_origin);
			$date = $document ? $document->posting_date : null;
		} elseif ($movement->origintype === 'shipping' && (int) $movement->fk_origin > 0) {
			$document = $this->one('SELECT COALESCE(date_expedition,date_delivery) posting_date FROM '.$this->table('expedition').' WHERE entity='.$this->entity.' AND rowid='.(int) $movement->fk_origin);
			$date = $document ? $document->posting_date : null;
		}
		return $date ? $this->db->jdate($date) : $this->db->jdate($movement->datem);
	}

	/** Called after native stock mutation, in its still-open database transaction. */
	public function onMovement($movement, $user)
	{
		$c = $this->config(true);
		if (!$c || !$c->enabled) {
			return 0;
		}
		$this->validateWorkflow();
		$existing = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_movement='.(int) $movement->id);
		if ($existing) {
			return (int) $existing->rowid;
		}
		$m = $this->one('SELECT * FROM '.$this->table('stock_mouvement').' WHERE rowid='.(int) $movement->id);
		if (!$m) {
			throw new DomainException('Pergerakan stok tidak ditemukan.');
		}
		$ctx = self::$context;
		// Opening-balance backfill creates the native movement first, then
		// inserts the historical PI row and replays valuation atomically.
		if (is_array($ctx) && !empty($ctx['skip_ledger'])) {
			return 0;
		}
		if ($ctx === null && $this->consumeDocumentRollbackMovement($m)) {
			return 0;
		}
		if ($ctx === null && $this->detectReceptionRollback($m) && $this->consumeDocumentRollbackMovement($m)) {
			return 0;
		}
		$this->productWarehouse($m->fk_product, $m->fk_entrepot);
		if ($ctx === null) {
			if ($m->origintype === 'reception' && $m->value > 0) {
				$ctx = array('kind' => 'receipt', 'contra' => $c->grni_account);
			} elseif ($m->origintype === 'shipping' && $m->value < 0) {
				$ctx = array('kind' => 'delivery', 'contra' => $this->productCogsAccount($m->fk_product, $c));
			} else {
				throw new DomainException('Gunakan Penerimaan/Pengiriman atau halaman Operasi Perpetual Inventory untuk penyesuaian, transfer, dan retur.');
			}
		}
		$b = $this->balance($m->fk_product, $m->fk_entrepot);
		$native = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.(int) $m->fk_product.' AND fk_entrepot='.(int) $m->fk_entrepot.' FOR UPDATE');
		if (abs((float) ($native ? $native->reel : 0) - ((float) $b->qty + (float) $m->value)) > 0.0000001) {
			throw new DomainException('Saldo awal/pergerakan stok belum direkonsiliasi. Posting dibatalkan.');
		}
		$postingDate = $this->movementPostingDate($m);
		return $this->record($c, $b, $ctx['kind'], $m->value, $m->price, $postingDate, 'movement:'.$m->rowid, $ctx['contra'], $user, array('movement' => $m->rowid, 'origin_type' => $m->origintype, 'origin_id' => $m->fk_origin, 'source' => isset($ctx['source']) ? $ctx['source'] : 0, 'forced' => isset($ctx['forced']) ? $ctx['forced'] : null, 'no_gl' => !empty($ctx['no_gl']), 'note' => $m->label, 'allow_historical' => array_key_exists('allow_historical', $ctx) ? (bool) $ctx['allow_historical'] : true));
	}

	/**
	 * Validate and arm an atomic rollback of a native Reception/Shipment.
	 * Dolibarr will create the opposite native stock movements afterwards; the
	 * STOCK_MOVEMENT trigger consumes them and removes PI/GL on the final move.
	 */
	public function prepareDocumentRollback($originType, $originId, $movementAlreadyApplied = null)
	{
		if (!in_array($originType, array('reception', 'shipping'), true) || (int) $originId <= 0) {
			throw new DomainException('Asal pembatalan stok tidak valid.');
		}
		if (self::$documentRollback !== null) {
			if (self::$documentRollback['origin_type'] === $originType && self::$documentRollback['origin_id'] === (int) $originId) {
				return count(self::$documentRollback['entry_ids']);
			}
			throw new DomainException('Pembatalan dokumen stok lain masih berjalan.');
		}
		$c = $this->config(true);
		$this->validateConfig($c);
		$this->validateWorkflow();
		$entries = $this->rows('SELECT * FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND origin_type='.$this->str($originType).' AND origin_id='.(int) $originId.' ORDER BY rowid');
		if (!$entries) {
			return 0;
		}
		$ids = array();
		$expected = array();
		$pairs = array();
		foreach ($entries as $e) {
			if ($c->lock_date && substr($e->date_post, 0, 10) <= $c->lock_date) {
				throw new DomainException('Dokumen berada pada periode stok yang sudah dikunci.');
			}
			$ids[] = (int) $e->rowid;
			$key = (int) $e->fk_product.':'.(int) $e->fk_warehouse;
			$expected[$key] = ($expected[$key] ?? 0.0) - (float) $e->qty;
			if (!isset($pairs[$key])) {
				$pairs[$key] = array('product' => (int) $e->fk_product, 'warehouse' => (int) $e->fk_warehouse, 'first' => (int) $e->rowid);
			} else {
				$pairs[$key]['first'] = min($pairs[$key]['first'], (int) $e->rowid);
			}
		}
		$idList = implode(',', $ids);
		if ($this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_source IN ('.$idList.') LIMIT 1')) {
			throw new DomainException('Dokumen memiliki retur/pembalik lanjutan. Batalkan transaksi turunannya terlebih dahulu.');
		}
		if ($this->one('SELECT rowid FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_entry IN ('.$idList.') LIMIT 1')) {
			throw new DomainException('Reception sudah dipakai oleh Vendor Invoice. Batalkan/hapus invoice tersebut terlebih dahulu.');
		}
		foreach ($pairs as $pair) {
			$native = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.$pair['product'].' AND fk_entrepot='.$pair['warehouse'].' FOR UPDATE');
			$balance = $this->one('SELECT qty FROM '.$this->table('pi_balance').' WHERE entity='.$this->entity.' AND fk_product='.$pair['product'].' AND fk_warehouse='.$pair['warehouse'].' FOR UPDATE');
			$applied = ($movementAlreadyApplied && (int) $movementAlreadyApplied->fk_product === $pair['product'] && (int) $movementAlreadyApplied->fk_entrepot === $pair['warehouse']) ? (float) $movementAlreadyApplied->value : 0.0;
			if (abs((float) ($native ? $native->reel : 0) - ((float) ($balance ? $balance->qty : 0) + $applied)) > 0.0000001) {
				throw new DomainException('Stok native dan buku stok belum rekonsiliasi; pembatalan dihentikan.');
			}
		}
		self::$documentRollback = array(
			'entity' => $this->entity,
			'origin_type' => $originType,
			'origin_id' => (int) $originId,
			'entry_ids' => $ids,
			'expected' => $expected,
			'pairs' => $pairs,
		);
		return count($ids);
	}

	/** Find Reception rollback movements that Dolibarr emits before RECEPTION_* triggers. */
	private function detectReceptionRollback($movement)
	{
		if (self::$documentRollback !== null || (float) $movement->value == 0) {
			return false;
		}
		$originId = 0;
		if ($movement->origintype === 'reception' && (int) $movement->fk_origin > 0) {
			$originId = (int) $movement->fk_origin;
		} else {
			$candidates = $this->rows('SELECT DISTINCT e.origin_id,r.ref FROM '.$this->table('pi_entry').' e JOIN '.$this->table('reception').' r ON r.rowid=e.origin_id AND r.entity=e.entity WHERE e.entity='.$this->entity." AND e.origin_type='reception'".' AND e.fk_product='.(int) $movement->fk_product.' AND e.fk_warehouse='.(int) $movement->fk_entrepot.' AND e.qty*'.(float) $movement->value.'<0 ORDER BY e.rowid DESC');
			foreach ($candidates as $candidate) {
				if ($candidate->ref !== '' && strpos((string) $movement->label, (string) $candidate->ref) !== false) {
					if ($originId && $originId !== (int) $candidate->origin_id) {
						return false;
					}
					$originId = (int) $candidate->origin_id;
				}
			}
		}
		if (!$originId) {
			return false;
		}
		$this->prepareDocumentRollback('reception', $originId, $movement);
		return true;
	}

	private function consumeDocumentRollbackMovement($movement)
	{
		$rollback = self::$documentRollback;
		if ($rollback === null || $rollback['entity'] !== $this->entity) {
			return false;
		}
		$key = (int) $movement->fk_product.':'.(int) $movement->fk_entrepot;
		if (!isset($rollback['expected'][$key])) {
			return false;
		}
		$remaining = (float) $rollback['expected'][$key];
		$value = (float) $movement->value;
		if ($remaining * $value <= 0 || abs($value) > abs($remaining) + 0.0000001) {
			return false;
		}
		self::$documentRollback['expected'][$key] = $remaining - $value;
		$complete = true;
		foreach (self::$documentRollback['expected'] as $qty) {
			if (abs((float) $qty) > 0.0000001) {
				$complete = false;
				break;
			}
		}
		if ($complete) {
			$this->finalizeDocumentRollback();
		}
		return true;
	}

	private function finalizeDocumentRollback()
	{
		$rollback = self::$documentRollback;
		if ($rollback === null) {
			return;
		}
		try {
			$idList = implode(',', array_map('intval', $rollback['entry_ids']));
			$docType = $rollback['origin_type'] === 'shipping' ? 'shipment' : 'reception';
			$this->q('DELETE FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity.' AND ((doc_type='.$this->str($docType).' AND fk_doc='.(int) $rollback['origin_id'].' AND fk_docdet IN ('.$idList.')) OR (doc_type='.$this->str('perpetualinventory').' AND fk_doc IN ('.$idList.')))');
			$this->q('DELETE FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND rowid IN ('.$idList.')');
			// A historical cancellation changes later moving-average issues. Replay
			// all surviving entries and rebuild affected journals atomically.
			$this->recalculateValuation($GLOBALS['user'], false, true);
			self::$documentRollback = null;
		} catch (Throwable $e) {
			self::$documentRollback = null;
			throw $e;
		}
	}

	/** Ensure a native action did produce and consume every required stock reversal. */
	public function assertDocumentRollbackComplete($originType, $originId)
	{
		$remaining = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND origin_type='.$this->str($originType).' AND origin_id='.(int) $originId.' LIMIT 1');
		if ($remaining) {
			throw new DomainException('Dolibarr tidak membuat gerakan stok pembatalan lengkap; dokumen dan ledger tidak diubah.');
		}
		if (self::$documentRollback !== null) {
			self::$documentRollback = null;
		}
	}

	/** Reverse native Shipment stock before Dolibarr changes it back to draft. */
	public function unvalidateShipment($shipment, $user)
	{
		$originId = (int) $shipment->id;
		$entries = $this->rows('SELECT e.rowid,e.qty,e.fk_product,e.fk_warehouse,m.batch,m.eatby,m.sellby FROM '.$this->table('pi_entry').' e LEFT JOIN '.$this->table('stock_mouvement').' m ON m.rowid=e.fk_movement WHERE e.entity='.$this->entity." AND e.origin_type='shipping' AND e.origin_id=".$originId.' ORDER BY e.rowid DESC');
		if (!$entries) {
			return 0;
		}
		$this->prepareDocumentRollback('shipping', $originId);
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		foreach ($entries as $entry) {
			$qty = -(float) $entry->qty;
			$movement = new MouvementStock($this->db);
			$result = $movement->_create(
				$user,
				(int) $entry->fk_product,
				(int) $entry->fk_warehouse,
				$qty,
				$qty > 0 ? 3 : 2,
				0,
				'Shipment dikembalikan ke draft: '.$shipment->ref,
				'',
				'',
				$entry->eatby ? $this->db->jdate($entry->eatby) : 0,
				$entry->sellby ? $this->db->jdate($entry->sellby) : 0,
				(string) $entry->batch,
				false,
				0,
				1
			);
			if ($result <= 0) {
				self::$documentRollback = null;
				throw new DomainException('Stok Shipment gagal dibalik: '.$movement->error.' '.implode('; ', (array) $movement->errors));
			}
		}
		$this->assertDocumentRollbackComplete('shipping', $originId);
		return count($entries);
	}

	/**
	 * Restore a canceled native Shipment as a draft without touching stock again.
	 * Native cancellation has already reversed stock and deletes expedition lines,
	 * so legacy documents are rebuilt from their order and original movements.
	 */
	public function amendCanceledShipment($shipment, $user)
	{
		if ((int) $shipment->status !== -1) {
			throw new DomainException('Hanya Shipment berstatus Canceled yang dapat diamend dengan pemulihan ini.');
		}
		$dependentInvoice = $this->one('SELECT f.rowid,f.ref,f.fk_statut FROM '.$this->table('element_element').' ee JOIN '.$this->table('facture').' f ON f.rowid=ee.fk_target AND f.entity='.$this->entity.' WHERE ee.sourcetype='.$this->str('shipping').' AND ee.fk_source='.(int) $shipment->id.' AND ee.targettype='.$this->str('facture').' AND f.fk_statut IN (1,2) LIMIT 1');
		if ($dependentInvoice) {
			throw new DomainException('Shipment memiliki Customer Invoice aktif '.$dependentInvoice->ref.'. Batalkan/revisi invoice tersebut terlebih dahulu, lalu Amend Shipment.');
		}
		if ($this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity." AND origin_type='shipping' AND origin_id=".(int) $shipment->id.' LIMIT 1')) {
			throw new DomainException('Ledger Shipment canceled masih aktif; jalankan rekonsiliasi sebelum amend.');
		}
		$source = $this->one('SELECT ee.fk_source FROM '.$this->table('element_element').' ee JOIN '.$this->table('commande').' c ON c.rowid=ee.fk_source AND c.entity='.$this->entity.' WHERE ee.sourcetype='.$this->str('commande').' AND ee.targettype='.$this->str('shipping').' AND ee.fk_target='.(int) $shipment->id.' LIMIT 1');
		if (!$source) {
			throw new DomainException('Sales Order sumber Shipment tidak ditemukan; amend otomatis dihentikan.');
		}
		$existing = $this->one('SELECT COUNT(*) qty FROM '.$this->table('expeditiondet').' WHERE fk_expedition='.(int) $shipment->id);
		if ((int) $existing->qty === 0) {
			$audit = $this->one('SELECT snapshot_json FROM '.$this->table('pi_document_action').' WHERE entity='.$this->entity.' AND element_type='.$this->str('shipment').' AND fk_element='.(int) $shipment->id." AND action_code='cancel' AND snapshot_json IS NOT NULL ORDER BY rowid DESC LIMIT 1");
			$snapshot = $audit ? json_decode((string) $audit->snapshot_json, true) : null;
			if (is_array($snapshot) && !empty($snapshot['lines'])) {
				$this->restoreShipmentSnapshot((int) $shipment->id, $snapshot['lines']);
				$existing->qty = count($snapshot['lines']);
			}
		}
		if ((int) $existing->qty === 0) {
			$movements = $this->rows('SELECT fk_product,fk_entrepot,SUM(ABS(value)) qty,COUNT(DISTINCT NULLIF(batch,\'\')) batches FROM '.$this->table('stock_mouvement').' WHERE origintype='.$this->str('shipping').' AND fk_origin='.(int) $shipment->id.' AND value<0 GROUP BY fk_product,fk_entrepot');
			if (!$movements) {
				throw new DomainException('Jejak stock movement Shipment tidak ditemukan; baris tidak dapat dipulihkan otomatis.');
			}
			$movementQty = array();
			$defaultWarehouse = 0;
			foreach ($movements as $movement) {
				if ((int) $movement->batches > 0) {
					throw new DomainException('Shipment lot/serial lama memerlukan pemulihan batch manual; amend otomatis dihentikan.');
				}
				$key = (int) $movement->fk_product;
				$movementQty[$key] = array('qty' => (float) $movement->qty, 'warehouse' => (int) $movement->fk_entrepot);
				$defaultWarehouse = $defaultWarehouse ?: (int) $movement->fk_entrepot;
			}
			$orderLines = $this->rows('SELECT d.rowid,d.fk_product,d.qty,d.fk_unit,d.description,d.rang,p.stockable_product FROM '.$this->table('commandedet').' d LEFT JOIN '.$this->table('product').' p ON p.rowid=d.fk_product WHERE d.fk_commande='.(int) $source->fk_source.' ORDER BY d.rang,d.rowid');
			$inserted = 0;
			foreach ($orderLines as $line) {
				$product = (int) $line->fk_product;
				if ($product <= 0) {
					continue;
				}
				if (!empty($line->stockable_product)) {
					if (empty($movementQty[$product]['qty'])) {
						continue;
					}
					$qty = min((float) $line->qty, (float) $movementQty[$product]['qty']);
					$warehouse = (int) $movementQty[$product]['warehouse'];
					$movementQty[$product]['qty'] -= $qty;
				} else {
					// Non-stock lines do not have a movement. They are unambiguous only
					// when this order has a single Shipment relationship.
					$countShipments = $this->one('SELECT COUNT(*) qty FROM '.$this->table('element_element').' WHERE sourcetype='.$this->str('commande').' AND fk_source='.(int) $source->fk_source.' AND targettype='.$this->str('shipping'));
					if ((int) $countShipments->qty !== 1) {
						throw new DomainException('Kuantitas baris non-stok tidak dapat ditentukan karena Sales Order memiliki beberapa Shipment.');
					}
					$qty = (float) $line->qty;
					$warehouse = $defaultWarehouse;
				}
				if ($qty <= 0) {
					continue;
				}
				$this->q('INSERT INTO '.$this->table('expeditiondet').' (fk_expedition,fk_elementdet,element_type,fk_product,qty,fk_unit,fk_entrepot,description,rang) VALUES ('.(int) $shipment->id.','.(int) $line->rowid.','.$this->str('commande').','.$product.','.(float) $qty.','.(int) $line->fk_unit.','.$warehouse.','.$this->str((string) $line->description).','.(int) $line->rang.')');
				$inserted++;
			}
			if (!$inserted) {
				throw new DomainException('Tidak ada baris Shipment yang dapat dipulihkan.');
			}
			foreach ($movementQty as $remaining) {
				if ((float) $remaining['qty'] > 0.0000001) {
					throw new DomainException('Jejak kuantitas Shipment tidak cocok dengan Sales Order; amend otomatis dibatalkan.');
				}
			}
		}
		$this->q('UPDATE '.$this->table('expedition').' SET fk_statut=0,billed=0,fk_user_modif='.(int) $user->id.' WHERE entity='.$this->entity.' AND rowid='.(int) $shipment->id.' AND fk_statut=-1');
		$shipment->status = $shipment->statut = 0;
		return 1;
	}

	private function restoreShipmentSnapshot($shipmentId, $lines)
	{
		$lineMap = array();
		$pendingParents = array();
		foreach ($lines as $line) {
			$oldId = (int) ($line['rowid'] ?? 0);
			$this->q('INSERT INTO '.$this->table('expeditiondet').' (fk_expedition,fk_element,fk_elementdet,element_type,fk_product,qty,fk_unit,fk_entrepot,description,rang,extraparams) VALUES ('.(int) $shipmentId.','.(!empty($line['fk_element']) ? (int) $line['fk_element'] : 'NULL').','.(!empty($line['fk_elementdet']) ? (int) $line['fk_elementdet'] : 'NULL').','.$this->str($line['element_type'] ?? 'commande').','.(!empty($line['fk_product']) ? (int) $line['fk_product'] : 'NULL').','.(float) ($line['qty'] ?? 0).','.(!empty($line['fk_unit']) ? (int) $line['fk_unit'] : 'NULL').','.(!empty($line['fk_entrepot']) ? (int) $line['fk_entrepot'] : 'NULL').','.$this->str($line['description'] ?? '').','.(int) ($line['rang'] ?? 0).','.(!empty($line['extraparams']) ? $this->str($line['extraparams']) : 'NULL').')');
			$newId = (int) $this->db->last_insert_id($this->table('expeditiondet'));
			$lineMap[$oldId] = $newId;
			if (!empty($line['fk_parent'])) {
				$pendingParents[$newId] = (int) $line['fk_parent'];
			}
			foreach ((array) ($line['batches'] ?? array()) as $batch) {
				$this->q('INSERT INTO '.$this->table('expeditiondet_batch').' (fk_expeditiondet,eatby,sellby,batch,qty,fk_origin_stock,fk_warehouse) VALUES ('.$newId.','.(!empty($batch['eatby']) ? $this->str($batch['eatby']) : 'NULL').','.(!empty($batch['sellby']) ? $this->str($batch['sellby']) : 'NULL').','.$this->str($batch['batch'] ?? '').','.(float) ($batch['qty'] ?? 0).','.(int) ($batch['fk_origin_stock'] ?? 0).','.(!empty($batch['fk_warehouse']) ? (int) $batch['fk_warehouse'] : 'NULL').')');
			}
		}
		foreach ($pendingParents as $newId => $oldParent) {
			if (isset($lineMap[$oldParent])) {
				$this->q('UPDATE '.$this->table('expeditiondet').' SET fk_parent='.(int) $lineMap[$oldParent].' WHERE rowid='.(int) $newId);
			}
		}
	}

	/** Delete accounting dispatch for a canceled/deleted invoice and release PI receipt matches. */
	public function cancelInvoiceAccounting($invoiceType, $invoiceId)
	{
		if (!in_array($invoiceType, array('customer', 'supplier'), true) || (int) $invoiceId <= 0) {
			throw new DomainException('Jenis invoice tidak valid.');
		}
		$this->config(true);
		if ($invoiceType === 'supplier') {
			$lines = $this->rows('SELECT DISTINCT fk_invoice_line FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice='.(int) $invoiceId);
			$this->q('DELETE FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice='.(int) $invoiceId);
			foreach ($lines as $line) {
				$this->q('UPDATE '.$this->table('facture_fourn_det').' SET fk_code_ventilation=0 WHERE rowid='.(int) $line->fk_invoice_line.' AND fk_facture_fourn='.(int) $invoiceId);
			}
		}
		$types = $invoiceType === 'supplier' ? array('supplier_invoice', 'invoice_supplier') : array('customer_invoice', 'invoice');
		$quoted = array_map(array($this, 'str'), $types);
		$this->q('DELETE FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity.' AND fk_doc='.(int) $invoiceId.' AND doc_type IN ('.implode(',', $quoted).')');
	}

	/** Atomically remove an invoice after removing the bookkeeping rows that make core mark it non-erasable. */
	public function deleteInvoiceWithLedger($invoice, $user)
	{
		$type = in_array($invoice->element, array('facture_fourn', 'invoice_supplier'), true) ? 'supplier' : 'customer';
		if (method_exists($invoice, 'getSommePaiement') && abs((float) $invoice->getSommePaiement()) > 0.0000001) {
			throw new DomainException('Invoice memiliki pembayaran. Hapus pembayaran terlebih dahulu.');
		}
		return $this->transaction(function () use ($invoice, $user, $type) {
			$this->cancelInvoiceAccounting($type, $invoice->id);
			$result = $invoice->delete($user);
			if ($result <= 0) {
				$message = $invoice->error ?: implode('; ', (array) $invoice->errors);
				throw new DomainException('Invoice gagal dihapus'.($message ? ': '.$message : '.'));
			}
			return $result;
		});
	}

	/** Remove invoice bookkeeping and put a validated invoice back into draft atomically. */
	public function reviseInvoiceWithLedger($invoice, $user)
	{
		$type = in_array($invoice->element, array('facture_fourn', 'invoice_supplier'), true) ? 'supplier' : 'customer';
		if (!in_array((int) $invoice->status, array(1, 3), true)) {
			throw new DomainException('Hanya invoice tervalidasi atau canceled yang dapat dikembalikan ke draft untuk revisi.');
		}
		if (method_exists($invoice, 'getSommePaiement') && abs((float) $invoice->getSommePaiement()) > 0.0000001) {
			throw new DomainException('Invoice memiliki pembayaran. Batalkan atau hapus pembayaran terlebih dahulu.');
		}
		if ($type === 'customer' && method_exists($invoice, 'is_last_in_cycle') && !$invoice->is_last_in_cycle()) {
			throw new DomainException('Invoice bukan dokumen terakhir dalam siklus. Batalkan dokumen turunannya terlebih dahulu.');
		}
		return $this->transaction(function () use ($invoice, $user, $type) {
			$this->cancelInvoiceAccounting($type, $invoice->id);
			$result = $invoice->setDraft($user, -1);
			if ($result < 0) {
				$message = $invoice->error ?: implode('; ', (array) $invoice->errors);
				throw new DomainException('Invoice gagal dikembalikan ke draft'.($message ? ': '.$message : '.'));
			}
			return $result;
		});
	}

	public function record($c, $b, $kind, $qty, $cost, $date, $key, $contra, $user, $meta = array())
	{
		$this->checkDate($date, $c, $b, !empty($meta['allow_historical']));
		$v = PiValuation::move($b->qty, $b->stock_value, $qty, $cost, isset($meta['forced']) ? $meta['forced'] : null);
		$v['value'] = (float) price2num($v['value'], 'MT');
		$v['delta'] = round($v['value'] - $b->stock_value, 8);
		$v['cost'] = abs($v['delta'] / $qty);
		$fields = array('entity' => $this->entity, 'event_key' => $key, 'kind' => $kind, 'date_post' => $this->db->idate($date), 'fk_product' => $b->fk_product, 'fk_warehouse' => $b->fk_warehouse, 'origin_type' => $meta['origin_type'] ?? '', 'origin_id' => $meta['origin_id'] ?? 0, 'fk_source' => $meta['source'] ?? 0, 'qty' => $qty, 'unit_cost' => $v['cost'], 'value_delta' => $v['delta'], 'balance_qty' => $v['qty'], 'balance_value' => $v['value'], 'stock_account' => $c->stock_account, 'contra_account' => $contra, 'note' => substr($meta['note'] ?? '', 0, 255), 'fk_user' => $user->id);
		if (!empty($meta['movement'])) {
			$fields['fk_movement'] = $meta['movement'];
		}
		$this->q('INSERT INTO '.$this->table('pi_entry').' ('.implode(',', array_keys($fields)).') VALUES ('.implode(',', array_map(array($this, 'str'), array_values($fields))).')');
		$id = (int) $this->db->last_insert_id($this->table('pi_entry'));
		$postDate = $this->db->idate($date);
		$this->q('UPDATE '.$this->table('pi_balance').' SET qty='.(float) $v['qty'].',stock_value='.(float) $v['value'].',last_date=CASE WHEN last_date IS NULL OR last_date<'.$this->str($postDate).' THEN '.$this->str($postDate).' ELSE last_date END WHERE rowid='.(int) $b->rowid.' AND entity='.$this->entity);
		if (empty($meta['no_gl']) && $v['delta'] != 0) {
			$this->postJournal($c, $id, $date, $c->stock_account, $contra, $v['delta'], $kind, $user);
		}
		$this->q('UPDATE '.$this->table('pi_entry').' SET journal_posted='.(empty($meta['no_gl']) ? 1 : 2).' WHERE rowid='.$id.' AND entity='.$this->entity);
		return $id;
	}

	public function postJournal($c, $id, $date, $stock, $contra, $amount, $label, $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
		$document = $this->journalDocument($id, $label);
		$journalIds = array();
		foreach (array(array($stock, $amount), array($contra, -$amount)) as $line) {
			$a = $this->account($line[0]);
			$j = new BookKeeping($this->db);
			$j->doc_date = $date;
			$j->doc_type = $document['type'];
			$j->doc_ref = $document['ref'];
			$j->fk_doc = $document['fk_doc'];
			$j->fk_docdet = $document['fk_docdet'];
			$j->numero_compte = $a->account_number;
			$j->label_compte = $a->label;
			$j->label_operation = $document['label'];
			$j->debit = max(0, $line[1]);
			$j->credit = max(0, -$line[1]);
			$j->montant = abs($line[1]);
			$j->sens = $line[1] > 0 ? 'D' : 'C';
			$j->code_journal = $c->journal_code;
			$j->journal_label = 'Perpetual Inventory';
			$j->fk_user_author = $user->id;
			$j->entity = $this->entity;
			if ($j->create($user) < 0) {
				throw new DomainException('Jurnal gagal: '.$j->error.' '.implode('; ', $j->errors));
			}
			$journalIds[] = (int) $j->id;
		}
		// Finalize only this balanced system-generated piece; native bulk deletion excludes it.
		$this->q('UPDATE '.$this->table('accounting_bookkeeping').' SET date_validated='.$this->str($this->db->idate(dol_now())).',fk_user='.(int) $user->id.' WHERE entity='.$this->entity.' AND rowid IN ('.implode(',', $journalIds).')');
	}

	/** Use the native business document as the visible voucher and retain PI-id in its audit label. */
	private function journalDocument($id, $kind)
	{
		$e = $this->one('SELECT e.*,p.ref product_ref FROM '.$this->table('pi_entry').' e JOIN '.$this->table('product').' p ON p.rowid=e.fk_product WHERE e.entity='.$this->entity.' AND e.rowid='.(int) $id);
		$type = 'perpetualinventory';
		$ref = 'PI-'.$id;
		$fkDoc = $id;
		$fkDocdet = 0;
		$title = $this->journalKindLabel($kind);
		if ($e && $e->origin_type === 'reception' && $e->origin_id) {
			$source = $this->one('SELECT ref FROM '.$this->table('reception').' WHERE entity='.$this->entity.' AND rowid='.(int) $e->origin_id);
			if ($source && $source->ref) {
				$type = 'reception';
				$ref = $source->ref;
				$fkDoc = (int) $e->origin_id;
				$fkDocdet = $id;
				$title = 'Penerimaan barang';
			}
		} elseif ($e && $e->origin_type === 'shipping' && $e->origin_id) {
			$source = $this->one('SELECT ref FROM '.$this->table('expedition').' WHERE entity='.$this->entity.' AND rowid='.(int) $e->origin_id);
			if ($source && $source->ref) {
				$type = 'shipment';
				$ref = $source->ref;
				$fkDoc = (int) $e->origin_id;
				$fkDocdet = $id;
				$title = 'Pengiriman barang';
			}
		} elseif ($e && $e->origin_type === 'pistockentry' && $e->origin_id) {
			$source = $this->one('SELECT ref FROM '.$this->table('pi_stock_entry').' WHERE entity='.$this->entity.' AND rowid='.(int) $e->origin_id);
			if ($source && $source->ref) {
				$type = 'stock_entry';
				$ref = $source->ref;
				$fkDoc = (int) $e->origin_id;
				$fkDocdet = $id;
				$title = 'Stock Entry';
			}
		}
		$product = $e && $e->product_ref ? ' — '.$e->product_ref : '';
		return array('type' => $type, 'ref' => $ref, 'fk_doc' => $fkDoc, 'fk_docdet' => $fkDocdet, 'label' => $ref.' — '.$title.$product.' [PI-'.$id.']');
	}

	private function journalKindLabel($kind)
	{
		$labels = array(
			'receipt' => 'Penerimaan barang',
			'delivery' => 'Pengiriman barang',
			'opening' => 'Saldo awal persediaan',
			'adjustment' => 'Penyesuaian persediaan',
			'return' => 'Retur persediaan',
			'reversal' => 'Pembalik persediaan',
		);
		return isset($labels[$kind]) ? $labels[$kind] : ucfirst(str_replace('_', ' ', $kind));
	}

	public function opening($product, $warehouse, $cost, $adopt, $user, $date = null, $eventKey = '', $note = '', $contraAccount = '')
	{
		if (!$user->admin) {
			throw new DomainException('Saldo awal hanya untuk administrator.');
		}
		return $this->transaction(function () use ($product, $warehouse, $cost, $adopt, $user, $date, $eventKey, $note, $contraAccount) {
			$c = $this->config(true);
			$this->validateConfig($c);
			if ($c->enabled) {
				throw new DomainException('Saldo awal hanya dapat dimasukkan sebelum posting otomatis dimulai.');
			}
			$this->productWarehouse($product, $warehouse);
			$b = $this->balance($product, $warehouse);
			if ($b->last_date) {
				throw new DomainException('Saldo awal untuk barang/gudang ini sudah dibuat.');
			}
			$n = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.(int) $product.' AND fk_entrepot='.(int) $warehouse.' FOR UPDATE');
			if (!$n || $n->reel <= 0) {
				throw new DomainException('Isi stok awal positif di Dolibarr sebelum mengimpor nilai saldo awal.');
			}
			$postDate = $date ?: dol_now();
			$key = $eventKey !== '' ? $eventKey : 'opening:'.$product.':'.$warehouse;
			$contra = $contraAccount !== '' ? $contraAccount : $c->opening_account;
			$entryNote = $note !== '' ? $note : ($adopt ? 'Saldo akuntansi yang sudah ada' : 'Jurnal saldo awal baru');
			return $this->record($c, $b, 'opening', $n->reel, $cost, $postDate, $key, $contra, $user, array('no_gl' => $adopt, 'note' => $entryNote));
		});
	}

	/**
	 * Insert an additive historical opening while operations are already live.
	 * Caller owns the database transaction and must run recalculateValuation().
	 */
	public function backfillOpening($product, $warehouse, $qty, $cost, $date, $eventKey, $note, $contraAccount, $user, $addNative = true)
	{
		if (!$user->admin) {
			throw new DomainException('Backfill saldo awal hanya untuk administrator.');
		}
		$c = $this->config(true);
		$this->validateConfig($c);
		$this->productWarehouse($product, $warehouse);
		if (!is_finite((float) $qty) || $qty <= 0 || !is_finite((float) $cost) || $cost < 0) {
			throw new DomainException('Saldo awal membutuhkan jumlah positif dan modal per unit nol atau positif.');
		}
		$postDate = $this->db->idate($date);
		if (!$date || $date > dol_now() + 60 || ($c->lock_date && substr($postDate, 0, 10) <= $c->lock_date)) {
			throw new DomainException('Tanggal saldo awal terkunci atau di masa depan.');
		}
		$existing = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND event_key='.$this->str($eventKey).' FOR UPDATE');
		if ($existing) {
			return (int) $existing->rowid;
		}

		$movementId = 0;
		if ($addNative) {
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
			$movement = new MouvementStock($this->db);
			$previous = self::$context;
			self::$context = array('skip_ledger' => true);
			try {
				$movementId = $movement->reception($user, (int) $product, (int) $warehouse, (float) $qty, (float) $cost, $note, '', '', '', $date, 0, $eventKey);
				if ($movementId <= 0) {
					throw new DomainException('Gagal menambahkan kuantitas saldo awal: '.$movement->error.' '.implode('; ', $movement->errors));
				}
			} finally {
				self::$context = $previous;
			}
		}

		$amount = (float) price2num($qty * $cost, 'MT');
		$fields = array(
			'entity' => $this->entity, 'event_key' => $eventKey, 'kind' => 'opening',
			'date_post' => $postDate, 'fk_product' => (int) $product, 'fk_warehouse' => (int) $warehouse,
			'origin_type' => 'openingbalance', 'origin_id' => 0, 'fk_source' => 0,
			'qty' => (float) $qty, 'unit_cost' => (float) $cost, 'value_delta' => $amount,
			'balance_qty' => 0, 'balance_value' => 0, 'stock_account' => $c->stock_account,
			'contra_account' => $contraAccount, 'journal_posted' => 2,
			'note' => substr($note, 0, 255), 'fk_user' => (int) $user->id,
		);
		if ($movementId > 0) {
			$fields['fk_movement'] = $movementId;
		}
		$this->q('INSERT INTO '.$this->table('pi_entry').' ('.implode(',', array_keys($fields)).') VALUES ('.implode(',', array_map(array($this, 'str'), array_values($fields))).')');
		return (int) $this->db->last_insert_id($this->table('pi_entry'));
	}

	/** Replay moving-average valuation and rebuild affected automatic journals. */
	public function recalculateValuation($user, $manageTransaction = true, $internal = false)
	{
		if (!$internal && !$user->admin) {
			throw new DomainException('Hitung ulang valuasi hanya untuk administrator.');
		}
		$work = function () use ($user) {
			$c = $this->config(true);
			$this->validateConfig($c);
			$entries = $this->rows('SELECT * FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity." ORDER BY date_post,CASE WHEN kind='opening' THEN 0 ELSE 1 END,rowid FOR UPDATE");
			$states = array();
			$replayed = array();
			$changedJournals = array();
			foreach ($entries as $entry) {
				$key = (int) $entry->fk_product.':'.(int) $entry->fk_warehouse;
				$state = $states[$key] ?? array('qty' => 0.0, 'value' => 0.0);
				$forced = null;
				if ((int) $entry->fk_source > 0) {
					if (!isset($replayed[(int) $entry->fk_source])) {
						throw new DomainException('Urutan sumber valuasi PI-'.$entry->rowid.' tidak valid.');
					}
					$source = $replayed[(int) $entry->fk_source];
					$forced = -(float) $source['delta'] * abs((float) $entry->qty / (float) $source['qty']);
				}
				$v = PiValuation::move($state['qty'], $state['value'], (float) $entry->qty, (float) $entry->unit_cost, $forced);
				$v['value'] = (float) price2num($v['value'], 'MT');
				$v['delta'] = round($v['value'] - $state['value'], 8);
				$v['cost'] = abs($v['delta'] / (float) $entry->qty);
				$deltaChanged = abs((float) $entry->value_delta - $v['delta']) > 0.00000001;
				$this->q('UPDATE '.$this->table('pi_entry').' SET unit_cost='.(float) $v['cost'].',value_delta='.(float) $v['delta'].',balance_qty='.(float) $v['qty'].',balance_value='.(float) $v['value'].' WHERE entity='.$this->entity.' AND rowid='.(int) $entry->rowid);
				if ($deltaChanged && (int) $entry->journal_posted === 1) {
					$closed = $this->one('SELECT rowid FROM '.$this->table('accounting_fiscalyear').' WHERE entity='.$this->entity.' AND statut=1 AND '.$this->str(substr($entry->date_post, 0, 10)).' BETWEEN date_start AND date_end LIMIT 1');
					if ($closed) {
						throw new DomainException('Valuasi PI-'.$entry->rowid.' berubah tetapi periode akuntansinya sudah ditutup.');
					}
					$changedJournals[] = array($entry, $v['delta']);
				}
				$states[$key] = array('qty' => $v['qty'], 'value' => $v['value']);
				$replayed[(int) $entry->rowid] = array('qty' => (float) $entry->qty, 'delta' => $v['delta']);
			}

			$balances = $this->rows('SELECT * FROM '.$this->table('pi_balance').' WHERE entity='.$this->entity.' FOR UPDATE');
			foreach ($balances as $b) {
				$product = (int) $b->fk_product;
				$warehouse = (int) $b->fk_warehouse;
				$key = $product.':'.$warehouse;
				$state = $states[$key] ?? array('qty' => 0.0, 'value' => 0.0);
				$last = $this->one('SELECT date_post FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_product='.$product.' AND fk_warehouse='.$warehouse." ORDER BY date_post DESC,CASE WHEN kind='opening' THEN 0 ELSE 1 END DESC,rowid DESC LIMIT 1");
				$this->q('UPDATE '.$this->table('pi_balance').' SET qty='.(float) $state['qty'].',stock_value='.(float) $state['value'].',last_date='.($last ? $this->str($last->date_post) : 'NULL').' WHERE entity='.$this->entity.' AND rowid='.(int) $b->rowid);
			}

			foreach ($changedJournals as $change) {
				list($entry, $delta) = $change;
				$this->q('DELETE FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity.' AND ((doc_type='.$this->str('perpetualinventory').' AND fk_doc='.(int) $entry->rowid.') OR (doc_type IN ('.$this->str('reception').','.$this->str('shipment').','.$this->str('stock_entry').') AND fk_docdet='.(int) $entry->rowid.'))');
				if (abs($delta) > 0.00000001) {
					$this->postJournal($c, (int) $entry->rowid, $this->db->jdate($entry->date_post), $entry->stock_account, $entry->contra_account, $delta, $entry->kind, $user);
				}
			}

			$mismatch = $this->one('SELECT b.fk_product,b.fk_warehouse,b.qty,COALESCE(ps.reel,0) native_qty FROM '.$this->table('pi_balance').' b LEFT JOIN '.$this->table('product_stock').' ps ON ps.fk_product=b.fk_product AND ps.fk_entrepot=b.fk_warehouse WHERE b.entity='.$this->entity.' AND ABS(b.qty-COALESCE(ps.reel,0))>0.0000001 LIMIT 1');
			if ($mismatch) {
				throw new DomainException('Hitung ulang dibatalkan karena stok native dan ledger berbeda pada produk #'.$mismatch->fk_product.'/gudang #'.$mismatch->fk_warehouse.'.');
			}
			return array('entries' => count($entries), 'journals' => count($changedJournals));
		};
		return $manageTransaction ? $this->transaction($work) : $work();
	}

	/** Persist the mandatory business reason for cancellation or revision. */
	public function recordDocumentAction($elementType, $elementId, $elementRef, $actionCode, $reason, $user, $snapshot = null)
	{
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 5) {
			throw new DomainException('Alasan pembatalan/revisi wajib diisi minimal 5 karakter.');
		}
		if (!in_array($elementType, array('customer_order', 'supplier_order', 'shipment', 'reception', 'customer_invoice', 'supplier_invoice', 'customer_payment', 'supplier_payment', 'accounting_journal', 'stock_entry'), true) || !in_array($actionCode, array('cancel', 'amend'), true)) {
			throw new DomainException('Jenis dokumen atau aksi audit tidak valid.');
		}
		$this->q('INSERT INTO '.$this->table('pi_document_action').' (entity,element_type,fk_element,element_ref,action_code,reason,snapshot_json,fk_user,date_action) VALUES ('.$this->entity.','.$this->str($elementType).','.(int) $elementId.','.$this->str($elementRef).','.$this->str($actionCode).','.$this->str(mb_substr($reason, 0, 1000)).','.($snapshot !== null ? $this->str($snapshot) : 'NULL').','.(int) $user->id.','.$this->str($this->db->idate(dol_now())).')');
	}

	/** Controlled operations use native stock APIs; the trigger records the ledger and GL. */
	public function operate($kind, $product, $warehouse, $qty, $cost, $target, $source, $note, $requestKey, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin posting stok.');
		}
		return $this->transaction(function () use ($kind, $product, $warehouse, $qty, $cost, $target, $source, $note, $requestKey, $user) {
			return $this->operateLocked($kind, $product, $warehouse, $qty, $cost, $target, $source, $note, $requestKey, $user);
		});
	}

	/** Post several Stock Entry rows atomically. Every row receives a stable idempotency key. */
	public function operateBatch($kind, array $items, $note, $requestKey, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin posting stok.');
		}
		if (!$items || count($items) > 100) {
			throw new DomainException('Stock Entry harus memiliki 1 sampai 100 baris item.');
		}
		if (!preg_match('/^[a-f0-9]{32}$/', $requestKey)) {
			throw new DomainException('Kunci permintaan tidak valid. Muat ulang halaman.');
		}
		return $this->transaction(function () use ($kind, $items, $note, $requestKey, $user) {
			$ids = array();
			foreach (array_values($items) as $index => $item) {
				$row = array_merge(array('product' => 0, 'warehouse' => 0, 'qty' => 0, 'cost' => null, 'target' => 0, 'source' => 0), $item);
				$rowKey = md5($requestKey.':'.$index);
				$ids[] = $this->operateLocked($kind, (int) $row['product'], (int) $row['warehouse'], (float) $row['qty'], $row['cost'] === null ? null : (float) $row['cost'], (int) $row['target'], (int) $row['source'], $note, $rowKey, $user, (string) ($row['account'] ?? ''));
			}
			return $ids;
		});
	}

	/** Execute one already transaction-protected Stock Entry row. */
	private function operateLocked($kind, $product, $warehouse, $qty, $cost, $target, $source, $note, $requestKey, $user, $differenceAccount = '', $stockEntryId = 0, $postingDate = null)
	{
			$c = $this->config(true);
			$this->validateConfig($c);
			if (!$c->enabled) {
				throw new DomainException('Mulai posting otomatis pada pengaturan dahulu.');
			}
			$this->validateWorkflow();
			if (!preg_match('/^[a-f0-9]{32}$/', $requestKey)) {
				throw new DomainException('Kunci permintaan tidak valid. Muat ulang halaman.');
			}
			$duplicate = $this->one('SELECT e.rowid FROM '.$this->table('pi_entry').' e JOIN '.$this->table('stock_mouvement').' m ON m.rowid=e.fk_movement WHERE e.entity='.$this->entity.' AND m.inventorycode='.$this->str('PI-'.$requestKey).' LIMIT 1');
			if ($duplicate) {
				return $duplicate->rowid;
			}
			if ($kind === 'material_receipt') {
				if ($qty <= 0) {
					throw new DomainException('Material Receipt membutuhkan jumlah positif.');
				}
				$kind = 'adjustment';
				$note = 'Material Receipt: '.$note;
			} elseif ($kind === 'material_issue') {
				if ($qty <= 0) {
					throw new DomainException('Material Issue membutuhkan jumlah positif.');
				}
				$kind = 'adjustment';
				$qty = -$qty;
				$cost = null;
				$note = 'Material Issue: '.$note;
			} elseif ($kind === 'material_transfer') {
				$kind = 'transfer';
				$note = 'Material Transfer: '.$note;
			}
			if ($kind === 'count') {
				if (!is_finite((float) $qty) || $qty < 0) {
					throw new DomainException('Jumlah fisik opname harus nol atau positif.');
				}
				$this->productWarehouse($product, $warehouse);
				$b = $this->balance($product, $warehouse);
				$note = 'Opname '.(float) $qty.': '.$note;
				$qty = round($qty - $b->qty, 8);
				$kind = 'adjustment';
				if ($qty == 0) {
					throw new DomainException('Jumlah fisik sama dengan saldo; tidak ada penyesuaian untuk diposting.');
				}
			}
			if (!in_array($kind, array('opening', 'adjustment', 'transfer', 'return', 'reversal'), true) || !is_finite((float) $qty) || !$qty || !trim($note)) {
				throw new DomainException('Pilih operasi, jumlah bukan nol, dan alasan transaksi.');
			}
			$ctx = array('kind' => $kind, 'contra' => $c->adjustment_account);
			if ($differenceAccount !== '' && $kind === 'adjustment') {
				$this->account($differenceAccount);
				if ($differenceAccount === $c->stock_account) {
					throw new DomainException('Difference Account tidak boleh sama dengan akun Persediaan.');
				}
				$ctx['contra'] = $differenceAccount;
			}
			if ($stockEntryId) {
				$ctx['stock_entry_id'] = (int) $stockEntryId;
				$ctx['posting_date'] = $postingDate ?: dol_now();
				$ctx['allow_historical'] = false;
			}
			if ($kind === 'opening') {
				if ($qty <= 0 || $cost === null || !is_finite((float) $cost) || $cost < 0) {
					throw new DomainException('Saldo awal membutuhkan jumlah positif dan modal per unit nol atau positif.');
				}
				if ($this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity." AND kind<>'opening' LIMIT 1")) {
					throw new DomainException('Periode input saldo awal sudah ditutup karena terdapat transaksi operasional. Gunakan Penyesuaian untuk koreksi setelah operasional dimulai.');
				}
				$this->productWarehouse($product, $warehouse);
				$b = $this->balance($product, $warehouse);
				if ($b->last_date || abs((float) $b->qty) > 0.00000001) {
					throw new DomainException('Saldo awal barang/gudang ini sudah pernah dibuat.');
				}
				$ctx['contra'] = $c->opening_account;
				$note = 'Saldo awal: '.$note;
			}
			if ($kind === 'return' || $kind === 'reversal') {
				$s = $this->one('SELECT * FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND rowid='.(int) $source.' FOR UPDATE');
				if (!$s || !in_array($s->kind, array('receipt', 'delivery', 'adjustment'), true)) {
					throw new DomainException('Pilih transaksi penerimaan, pengiriman, atau penyesuaian asal.');
				}
				$product = (int) $s->fk_product;
				$warehouse = (int) $s->fk_warehouse;
				$used = $this->one('SELECT COALESCE(SUM(ABS(qty)),0) qty,COALESCE(SUM(ABS(value_delta)),0) amount FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_source='.(int) $s->rowid);
				if ($kind === 'reversal') {
					$last = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_product='.$product.' AND fk_warehouse='.$warehouse.' ORDER BY rowid DESC LIMIT 1');
					if ($last->rowid != $s->rowid || $used->qty > 0) {
						throw new DomainException('Pembalik hanya untuk transaksi terakhir yang belum diretur.');
					}
					$qty = abs($s->qty);
				} elseif (!in_array($s->kind, array('receipt', 'delivery'), true)) {
					throw new DomainException('Retur hanya untuk penerimaan atau pengiriman.');
				}
				$matched = $this->one('SELECT COALESCE(SUM(qty),0) qty,COALESCE(SUM(amount),0) amount FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_entry='.(int) $s->rowid);
				if ($qty <= 0 || $qty + $used->qty + $matched->qty > abs($s->qty) + 0.00000001) {
					throw new DomainException('Jumlah melebihi sisa yang dapat diretur. Penerimaan yang sudah ditagih belum mendukung retur pada versi ini.');
				}
				$qty = $s->qty > 0 ? -$qty : $qty;
				$cost = $s->unit_cost;
				$ctx['contra'] = $s->contra_account;
				$ctx['source'] = $s->rowid;
				$ctx['forced'] = $kind === 'reversal' ? -$s->value_delta : $qty * $s->unit_cost;
				if (abs(abs($qty) + $used->qty + $matched->qty - abs($s->qty)) < 0.00000001) {
					$ctx['forced'] = ($qty > 0 ? 1 : -1) * (abs($s->value_delta) - $used->amount - $matched->amount);
				}
			}
			$p = $this->productWarehouse($product, $warehouse);
			if ($qty > 0 && $kind === 'adjustment' && ($cost === null || !is_finite((float) $cost) || $cost < 0)) {
				throw new DomainException('Isi modal per unit untuk selisih stok masuk.');
			}
			if ($p->tobatch) {
				throw new DomainException('Operasi manual lot/serial belum didukung; jangan gunakan tanpa pemilihan lot.');
			}
			if ($kind === 'transfer') {
				if ($qty <= 0 || $target == $warehouse) {
					throw new DomainException('Transfer membutuhkan jumlah positif dan gudang tujuan berbeda.');
				}
				$this->productWarehouse($product, $target);
				$ctx['no_gl'] = true;
				$ctx['kind'] = 'transfer_out';
				$id = $this->nativeMove($product, $warehouse, -$qty, 0, $note, $requestKey, $ctx, $user);
				$out = $this->one('SELECT * FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_movement='.(int) $id);
				$ctx['kind'] = 'transfer_in';
				$ctx['source'] = $out->rowid;
				$ctx['forced'] = -$out->value_delta;
				$this->nativeMove($product, $target, $qty, $out->unit_cost, $note, $requestKey, $ctx, $user);
				return $out->rowid;
			}
			$movementId = $this->nativeMove($product, $warehouse, $qty, $cost, $note, $requestKey, $ctx, $user);
			$entry = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_movement='.(int) $movementId);
			return (int) $entry->rowid;
	}

	/** Validate and post one persistent Stock Entry document. */
	public function postStockEntry($documentId, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin memvalidasi Stock Entry.');
		}
		return $this->transaction(function () use ($documentId, $user) {
			$document = $this->one('SELECT * FROM '.$this->table('pi_stock_entry').' WHERE entity='.$this->entity.' AND rowid='.(int) $documentId.' FOR UPDATE');
			if (!$document || (int) $document->status !== 0) {
				throw new DomainException('Stock Entry tidak ditemukan atau bukan Draft.');
			}
			$lines = $this->rows('SELECT * FROM '.$this->table('pi_stock_entry_line').' WHERE fk_stock_entry='.(int) $documentId.' ORDER BY rang,rowid FOR UPDATE');
			if (!$lines) {
				throw new DomainException('Stock Entry belum memiliki item.');
			}
			$accountingPurposes = array('material_receipt', 'material_issue', 'count', 'adjustment');
			$postingDate = $this->db->jdate($document->date_post);
			$ids = array();
			foreach ($lines as $index => $line) {
				if (in_array($document->purpose, $accountingPurposes, true) && trim((string) $line->difference_account) === '') {
					throw new DomainException('Pilih Difference Account pada item baris '.($index + 1).'.');
				}
				$rowKey = md5($document->request_key.':'.$index);
				$entryId = $this->operateLocked($document->purpose, (int) $line->fk_product, (int) $line->fk_warehouse, (float) $line->qty, $line->unit_cost === null ? null : (float) $line->unit_cost, (int) $line->fk_target_warehouse, (int) $line->fk_source_entry, $document->ref.': '.trim($document->note.' '.$line->note), $rowKey, $user, (string) $line->difference_account, (int) $documentId, $postingDate);
				$target = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_source='.(int) $entryId." AND kind='transfer_in' ORDER BY rowid DESC LIMIT 1");
				$this->q('UPDATE '.$this->table('pi_stock_entry_line').' SET fk_pi_entry='.(int) $entryId.',fk_pi_entry_target='.(int) ($target ? $target->rowid : 0).' WHERE rowid='.(int) $line->rowid.' AND fk_stock_entry='.(int) $documentId);
				$ids[] = $entryId;
			}
			$validationStamp = $this->db->idate(dol_now());
			$this->q('UPDATE '.$this->table('pi_stock_entry').' SET status=1,date_valid='.$this->str($validationStamp).',fk_user_valid='.(int) $user->id.' WHERE entity='.$this->entity.' AND rowid='.(int) $documentId);
			return $ids;
		});
	}

	/** Cancel a posted Stock Entry and remove its PI/GL effect atomically. */
	public function cancelStockEntry($documentId, $reason, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin membatalkan Stock Entry.');
		}
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 5) {
			throw new DomainException('Alasan pembatalan wajib diisi minimal 5 karakter.');
		}
		return $this->transaction(function () use ($documentId, $reason, $user) {
			$document = $this->one('SELECT * FROM '.$this->table('pi_stock_entry').' WHERE entity='.$this->entity.' AND rowid='.(int) $documentId.' FOR UPDATE');
			if (!$document || (int) $document->status !== 1) {
				throw new DomainException('Stock Entry tidak ditemukan atau bukan berstatus Posted.');
			}
			$lines = $this->rows('SELECT * FROM '.$this->table('pi_stock_entry_line').' WHERE fk_stock_entry='.(int) $documentId.' ORDER BY rang,rowid FOR UPDATE');
			$entryIds = array();
			foreach ($lines as $line) {
				if ((int) $line->fk_pi_entry > 0) $entryIds[] = (int) $line->fk_pi_entry;
				if ((int) $line->fk_pi_entry_target > 0) $entryIds[] = (int) $line->fk_pi_entry_target;
			}
			$entryIds = array_values(array_unique($entryIds));
			if (!$entryIds) {
				throw new DomainException('Ledger Stock Entry tidak ditemukan; pembatalan dihentikan agar stok tetap aman.');
			}
			$idList = implode(',', array_map('intval', $entryIds));
			$entries = $this->rows('SELECT * FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND rowid IN ('.$idList.') ORDER BY rowid DESC FOR UPDATE');
			if (count($entries) !== count($entryIds)) {
				throw new DomainException('Sebagian ledger Stock Entry tidak ditemukan; jalankan rekonsiliasi sebelum membatalkan.');
			}
			$c = $this->config(true);
			$this->validateConfig($c);
			$this->validateWorkflow();
			foreach ($entries as $entry) {
				if ($c->lock_date && substr($entry->date_post, 0, 10) <= $c->lock_date) {
					throw new DomainException('Stock Entry berada pada periode stok yang sudah dikunci.');
				}
			}
			$child = $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_source IN ('.$idList.') AND rowid NOT IN ('.$idList.') LIMIT 1');
			if ($child) {
				throw new DomainException('Stock Entry memiliki retur/pembalik lanjutan. Batalkan transaksi turunannya terlebih dahulu.');
			}
			if ($this->one('SELECT rowid FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_entry IN ('.$idList.') LIMIT 1')) {
				throw new DomainException('Stock Entry sudah dipakai dalam pencocokan invoice. Batalkan pencocokan tersebut terlebih dahulu.');
			}
			if ($this->one('SELECT rowid FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity." AND doc_type='stock_entry' AND fk_doc=".(int) $documentId.' AND date_export IS NOT NULL LIMIT 1')) {
				throw new DomainException('Jurnal Stock Entry sudah diekspor dan tidak dapat dibatalkan. Buat jurnal koreksi pada periode aktif.');
			}
			$pairs = array();
			foreach ($entries as $entry) {
				$key = (int) $entry->fk_product.':'.(int) $entry->fk_warehouse;
				$pairs[$key] = array('product' => (int) $entry->fk_product, 'warehouse' => (int) $entry->fk_warehouse);
			}
			foreach ($pairs as $pair) {
				$native = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.$pair['product'].' AND fk_entrepot='.$pair['warehouse'].' FOR UPDATE');
				$balance = $this->one('SELECT qty FROM '.$this->table('pi_balance').' WHERE entity='.$this->entity.' AND fk_product='.$pair['product'].' AND fk_warehouse='.$pair['warehouse'].' FOR UPDATE');
				if (abs((float) ($native ? $native->reel : 0) - (float) ($balance ? $balance->qty : 0)) > 0.0000001) {
					throw new DomainException('Stok native dan buku stok belum rekonsiliasi; pembatalan dihentikan.');
				}
			}
			$snapshot = json_encode(array('version' => 1, 'document' => get_object_vars($document), 'lines' => array_map('get_object_vars', $lines), 'ledger' => array_map('get_object_vars', $entries)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if ($snapshot === false) {
				throw new DomainException('Snapshot Stock Entry gagal dibuat.');
			}
			require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
			$previous = self::$context;
			self::$context = array('skip_ledger' => true);
			try {
				foreach ($entries as $entry) {
					$movement = new MouvementStock($this->db);
					$movement->origin_type = 'pistockentry';
					$movement->origin_id = (int) $documentId;
					$qty = -(float) $entry->qty;
					$movementId = $movement->_create($user, (int) $entry->fk_product, (int) $entry->fk_warehouse, $qty, $qty > 0 ? 3 : 2, (float) $entry->unit_cost, 'Cancel '.$document->ref.': '.$reason, 'PI-CANCEL-'.$documentId.'-'.$entry->rowid, '', 0, 0, '', false, 0, 1);
					if ($movementId <= 0) {
						throw new DomainException('Pembalikan stok gagal: '.$movement->error.' '.implode('; ', (array) $movement->errors));
					}
				}
			} finally {
				self::$context = $previous;
			}
			$this->q('DELETE FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity." AND doc_type='stock_entry' AND fk_doc=".(int) $documentId);
			$this->q('DELETE FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND rowid IN ('.$idList.')');
			$this->q('UPDATE '.$this->table('pi_stock_entry_line').' SET fk_pi_entry=0,fk_pi_entry_target=0 WHERE fk_stock_entry='.(int) $documentId);
			$this->recalculateValuation($user, false, true);
			$this->q('UPDATE '.$this->table('pi_stock_entry').' SET status=2 WHERE entity='.$this->entity.' AND rowid='.(int) $documentId);
			$this->recordDocumentAction('stock_entry', (int) $documentId, $document->ref, 'cancel', $reason, $user, $snapshot);
			return count($entries);
		});
	}

	private function nativeMove($product, $warehouse, $qty, $cost, $note, $key, $ctx, $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		$m = new MouvementStock($this->db);
		$m->origin_type = !empty($ctx['stock_entry_id']) ? 'pistockentry' : 'perpetualinventory';
		$m->origin_id = !empty($ctx['stock_entry_id']) ? (int) $ctx['stock_entry_id'] : 0;
		$previous = self::$context;
		self::$context = $ctx;
		try {
			$id = $m->_create($user, (int) $product, (int) $warehouse, (float) $qty, $qty > 0 ? 3 : 2, (float) $cost, $note, 'PI-'.$key, !empty($ctx['posting_date']) ? $ctx['posting_date'] : '', 0, 0, '', false, 0, 1);
			if ($id <= 0) {
				throw new DomainException('Pergerakan gagal: '.$m->error.' '.implode('; ', $m->errors));
			}
			if (!$this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_movement='.(int) $id)) {
				throw new DomainException('Trigger Perpetual Inventory tidak berjalan. Transaksi dibatalkan.');
			}
			return $id;
		} finally {
			self::$context = $previous;
		}
	}

	public function matchInvoice($entryId, $lineId, $qty, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'match')) {
			throw new DomainException('Tidak memiliki izin pencocokan tagihan.');
		}
		return $this->transaction(function () use ($entryId, $lineId, $qty, $user) {
			$c = $this->config(true);
			$this->validateConfig($c);
			$e = $this->one('SELECT e.*, r.fk_soc FROM '.$this->table('pi_entry').' e JOIN '.$this->table('reception').' r ON r.rowid=e.origin_id AND r.entity=e.entity WHERE e.entity='.$this->entity." AND e.kind='receipt' AND e.rowid=".(int) $entryId);
			$l = $this->one('SELECT d.*, f.entity, f.fk_soc, f.fk_statut, f.type invoice_type, f.multicurrency_tx FROM '.$this->table('facture_fourn_det').' d JOIN '.$this->table('facture_fourn').' f ON f.rowid=d.fk_facture_fourn WHERE f.entity='.$this->entity.' AND d.rowid='.(int) $lineId.' FOR UPDATE');
			if (!$e || !$l || $l->fk_statut != 0 || $l->invoice_type != 0 || $e->fk_product != $l->fk_product || $e->fk_soc != $l->fk_soc || abs((float) $l->multicurrency_tx - 1) > 0.00000001 || $qty <= 0 || !is_finite((float) $qty)) {
				throw new DomainException('Pilih penerimaan dan baris tagihan draft standar dengan barang, supplier, dan mata uang dasar yang sama.');
			}
			if ($this->one('SELECT rowid FROM '.$this->table('accounting_bookkeeping')." WHERE doc_type='supplier_invoice' AND entity=".$this->entity.' AND fk_doc='.(int) $l->fk_facture_fourn.' LIMIT 1')) {
				throw new DomainException('Tagihan sudah masuk buku besar.');
			}
			$usedReceipt = $this->one('SELECT COALESCE(SUM(qty),0) qty,COALESCE(SUM(amount),0) amount FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_entry='.(int) $entryId);
			$returns = $this->one('SELECT COALESCE(SUM(ABS(qty)),0) qty,COALESCE(SUM(ABS(value_delta)),0) amount FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND fk_source='.(int) $entryId);
			$usedLine = $this->one('SELECT COALESCE(SUM(qty),0) qty FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice_line='.(int) $lineId);
			if ($qty + $usedReceipt->qty + $returns->qty > $e->qty + 0.00000001 || $qty + $usedLine->qty > $l->qty + 0.00000001) {
				throw new DomainException('Jumlah pencocokan melebihi sisa penerimaan atau tagihan.');
			}
			if ($l->qty <= 0 || abs($l->total_ht / $l->qty - $e->unit_cost) > 0.005) {
				throw new DomainException('Harga bersih tagihan berbeda dari valuasi penerimaan. Selisih harga/landed cost belum didukung versi ini.');
			}
			$amount = (float) price2num($qty * $e->unit_cost, 'MT');
			if (abs($qty + $usedReceipt->qty + $returns->qty - $e->qty) < 0.00000001) {
				$amount = $e->value_delta - $usedReceipt->amount - $returns->amount;
			}
			$this->q('INSERT INTO '.$this->table('pi_match').' (entity,fk_entry,fk_invoice,fk_invoice_line,qty,amount,fk_user) VALUES ('.$this->entity.','.(int) $entryId.','.(int) $l->fk_facture_fourn.','.(int) $lineId.','.(float) $qty.','.(float) $amount.','.(int) $user->id.')');
			$a = $this->account($c->grni_account);
			$this->q('UPDATE '.$this->table('facture_fourn_det').' SET fk_code_ventilation='.(int) $a->rowid.' WHERE rowid='.(int) $lineId);
		});
	}

	/**
	 * Match stock invoice lines automatically against eligible receipts.
	 *
	 * The caller (normally BILL_SUPPLIER_VALIDATE) owns the transaction, so a
	 * failed validation also rolls back every match created here.
	 */
	public function autoMatchInvoice($invoiceId, $user)
	{
		$c = $this->config(true);
		$this->validateConfig($c);
		$f = $this->one('SELECT rowid,fk_soc,fk_statut,type,datef,multicurrency_tx FROM '.$this->table('facture_fourn').' WHERE entity='.$this->entity.' AND rowid='.(int) $invoiceId.' FOR UPDATE');
		if (!$f || $f->type != 0 || abs((float) $f->multicurrency_tx - 1) > 0.00000001) {
			return 0;
		}
		$created = 0;
		$lines = $this->rows('SELECT d.* FROM '.$this->table('facture_fourn_det').' d JOIN '.$this->table('product').' p ON p.rowid=d.fk_product WHERE d.fk_facture_fourn='.(int) $invoiceId.' AND p.fk_product_type=0 AND p.stockable_product=1 ORDER BY d.rang,d.rowid');
		foreach ($lines as $l) {
			$usedLine = $this->one('SELECT COALESCE(SUM(qty),0) qty,COALESCE(SUM(amount),0) amount FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice_line='.(int) $l->rowid);
			$needed = (float) $l->qty - (float) $usedLine->qty;
			if ($needed <= 0.00000001 || $l->qty <= 0) {
				continue;
			}
			$unitPrice = (float) $l->total_ht / (float) $l->qty;
			$dateLimit = substr($f->datef, 0, 10).' 23:59:59';
			// Matching follows the Reception business date.  e.date_post is the
			// immutable ledger posting timestamp and may legitimately be later
			// when an older Reception is entered/validated today.
			$receiptDate = 'COALESCE(r.date_reception,r.date_delivery,e.date_post)';
			$candidates = $this->rows('SELECT e.*,'.
				'COALESCE((SELECT SUM(m.qty) FROM '.$this->table('pi_match').' m WHERE m.entity=e.entity AND m.fk_entry=e.rowid),0) matched_qty,'.
				'COALESCE((SELECT SUM(m.amount) FROM '.$this->table('pi_match').' m WHERE m.entity=e.entity AND m.fk_entry=e.rowid),0) matched_amount,'.
				'COALESCE((SELECT SUM(ABS(x.qty)) FROM '.$this->table('pi_entry').' x WHERE x.entity=e.entity AND x.fk_source=e.rowid),0) returned_qty,'.
				'COALESCE((SELECT SUM(ABS(x.value_delta)) FROM '.$this->table('pi_entry').' x WHERE x.entity=e.entity AND x.fk_source=e.rowid),0) returned_amount,'.
				'EXISTS(SELECT 1 FROM '.$this->table('element_element')." ee WHERE ee.sourcetype='reception' AND ee.fk_source=e.origin_id AND ee.targettype='invoice_supplier' AND ee.fk_target=".(int) $invoiceId.') direct_link '.
				'FROM '.$this->table('pi_entry').' e JOIN '.$this->table('reception').' r ON r.rowid=e.origin_id AND r.entity=e.entity '.
				'WHERE e.entity='.$this->entity." AND e.kind='receipt' AND e.fk_product=".(int) $l->fk_product.' AND r.fk_soc='.(int) $f->fk_soc.
				' AND '.$receiptDate.'<='.$this->str($dateLimit).' AND ABS(e.unit_cost-'.(float) $unitPrice.')<=0.005 '.
				'ORDER BY direct_link DESC,'.$receiptDate.',e.date_post,e.rowid');
			foreach ($candidates as $e) {
				$available = (float) $e->qty - (float) $e->matched_qty - (float) $e->returned_qty;
				if ($available <= 0.00000001) {
					continue;
				}
				$allocate = min($needed, $available);
				$amount = (float) price2num($allocate * (float) $e->unit_cost, 'MT');
				if (abs($allocate - $available) < 0.00000001) {
					$amount = (float) $e->value_delta - (float) $e->matched_amount - (float) $e->returned_amount;
				}
				$lineRemainder = (float) $l->total_ht - (float) $usedLine->amount;
				if (abs($allocate - $needed) < 0.00000001 && abs($lineRemainder - $amount) <= 0.005) {
					$amount = $lineRemainder;
				}
				$pair = $this->one('SELECT rowid FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_entry='.(int) $e->rowid.' AND fk_invoice_line='.(int) $l->rowid.' FOR UPDATE');
				if ($pair) {
					$this->q('UPDATE '.$this->table('pi_match').' SET qty=qty+'.(float) $allocate.',amount=amount+'.(float) $amount.',fk_user='.(int) $user->id.' WHERE rowid='.(int) $pair->rowid);
				} else {
					$this->q('INSERT INTO '.$this->table('pi_match').' (entity,fk_entry,fk_invoice,fk_invoice_line,qty,amount,fk_user) VALUES ('.$this->entity.','.(int) $e->rowid.','.(int) $invoiceId.','.(int) $l->rowid.','.(float) $allocate.','.(float) $amount.','.(int) $user->id.')');
				}
				$created++;
				$needed -= $allocate;
				$usedLine->qty += $allocate;
				$usedLine->amount += $amount;
				if ($needed <= 0.00000001) {
					break;
				}
			}
		}
		return $created;
	}

	public function unmatch($id, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'match')) {
			throw new DomainException('Tidak memiliki izin pencocokan tagihan.');
		}
		$this->transaction(function () use ($id) {
			$this->config(true);
			$m = $this->one('SELECT m.*, f.fk_statut FROM '.$this->table('pi_match').' m JOIN '.$this->table('facture_fourn').' f ON f.rowid=m.fk_invoice AND f.entity=m.entity WHERE m.entity='.$this->entity.' AND m.rowid='.(int) $id.' FOR UPDATE');
			if (!$m || $m->fk_statut != 0) {
				throw new DomainException('Hanya pencocokan tagihan draft yang dapat dilepas.');
			}
			$this->q('DELETE FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND rowid='.(int) $id);
			if (!$this->one('SELECT rowid FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice_line='.(int) $m->fk_invoice_line.' LIMIT 1')) {
				$this->q('UPDATE '.$this->table('facture_fourn_det').' SET fk_code_ventilation=0 WHERE rowid='.(int) $m->fk_invoice_line.' AND fk_facture_fourn='.(int) $m->fk_invoice);
			}
		});
	}

	/** Restore GRNI binding when Dolibarr's accounting allocation screen overwrote a matched invoice line. */
	public function repairInvoiceAccount($invoiceId, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'match')) {
			throw new DomainException('Tidak memiliki izin pencocokan tagihan.');
		}
		return $this->transaction(function () use ($invoiceId) {
			$this->config(true);
			$f = $this->one('SELECT rowid,fk_statut FROM '.$this->table('facture_fourn').' WHERE entity='.$this->entity.' AND rowid='.(int) $invoiceId.' FOR UPDATE');
			if (!$f || $f->fk_statut == 0) {
				throw new DomainException('Pilih Vendor Invoice tervalidasi yang sudah dicocokkan.');
			}
			if ($this->one('SELECT rowid FROM '.$this->table('accounting_bookkeeping')." WHERE entity=".$this->entity." AND doc_type='supplier_invoice' AND fk_doc=".(int) $invoiceId.' LIMIT 1')) {
				throw new DomainException('Vendor Invoice sudah masuk buku besar dan tidak boleh diubah.');
			}
			$this->checkInvoice($invoiceId, true);
		});
	}

	/** Validate every stock line before validation or transfer to the native purchase journal. */
	public function checkInvoice($invoiceId, $bind = false)
	{
		$c = $this->config(true);
		$f = $this->one('SELECT * FROM '.$this->table('facture_fourn').' WHERE entity='.$this->entity.' AND rowid='.(int) $invoiceId);
		if (!$f) {
			throw new DomainException('Tagihan tidak ditemukan pada entitas aktif.');
		}
		$lines = $this->rows('SELECT d.* FROM '.$this->table('facture_fourn_det').' d JOIN '.$this->table('product').' p ON p.rowid=d.fk_product WHERE d.fk_facture_fourn='.(int) $invoiceId.' AND p.fk_product_type=0 AND p.stockable_product=1');
		$a = $this->account($c->grni_account);
		foreach ($lines as $l) {
			$m = $this->one('SELECT COALESCE(SUM(qty),0) qty, COALESCE(SUM(amount),0) amount FROM '.$this->table('pi_match').' WHERE entity='.$this->entity.' AND fk_invoice='.(int) $invoiceId.' AND fk_invoice_line='.(int) $l->rowid);
			$receiptDate = 'COALESCE(r.date_reception,r.date_delivery,e.date_post)';
			$invalid = $this->one('SELECT m.rowid FROM '.$this->table('pi_match').' m JOIN '.$this->table('pi_entry').' e ON e.rowid=m.fk_entry AND e.entity=m.entity JOIN '.$this->table('reception').' r ON r.rowid=e.origin_id AND r.entity=e.entity WHERE m.entity='.$this->entity.' AND m.fk_invoice_line='.(int) $l->rowid.' AND (e.fk_product<>'.(int) $l->fk_product.' OR r.fk_soc<>'.(int) $f->fk_soc.' OR '.$receiptDate.'>'.$this->str(substr($f->datef, 0, 10).' 23:59:59').') LIMIT 1');
			if ($f->type != 0 || abs((float) $f->multicurrency_tx - 1) > 0.00000001 || $l->qty <= 0 || abs($m->qty - $l->qty) > 0.00000001 || abs($m->amount - $l->total_ht) > 0.005 || $invalid) {
				throw new DomainException('Baris tagihan #'.$l->rowid.' belum cocok penuh dengan penerimaan, atau harga/supplier/tanggal berubah. Buka Pencocokan Tagihan.');
			}
			if ($bind) {
				$this->q('UPDATE '.$this->table('facture_fourn_det').' SET fk_code_ventilation='.(int) $a->rowid.' WHERE rowid='.(int) $l->rowid);
			} elseif ($l->fk_code_ventilation != $a->rowid) {
				throw new DomainException('Akun baris persediaan harus Barang Diterima Belum Ditagih.');
			}
		}
		$orphans = $this->one('SELECT m.rowid FROM '.$this->table('pi_match').' m LEFT JOIN '.$this->table('facture_fourn_det').' d ON d.rowid=m.fk_invoice_line AND d.fk_facture_fourn=m.fk_invoice WHERE m.entity='.$this->entity.' AND m.fk_invoice='.(int) $invoiceId.' AND d.rowid IS NULL LIMIT 1');
		if ($orphans) {
			throw new DomainException('Ada pencocokan ke baris tagihan yang sudah dihapus. Lepas pencocokan itu dahulu.');
		}
		$changed = $this->one('SELECT m.rowid FROM '.$this->table('pi_match').' m JOIN '.$this->table('facture_fourn_det').' d ON d.rowid=m.fk_invoice_line JOIN '.$this->table('pi_entry').' e ON e.rowid=m.fk_entry AND e.entity=m.entity LEFT JOIN '.$this->table('product').' p ON p.rowid=d.fk_product WHERE m.entity='.$this->entity.' AND m.fk_invoice='.(int) $invoiceId.' AND (d.fk_product IS NULL OR d.fk_product<>e.fk_product OR p.fk_product_type<>0 OR p.stockable_product<>1 OR p.rowid IS NULL) LIMIT 1');
		if ($changed) {
			throw new DomainException('Barang pada baris tagihan yang dicocokkan telah berubah. Lepas pencocokan dan cocokkan ulang.');
		}
	}

	public function reconciliation()
	{
		$c = $this->config();
		$ledger = $this->one('SELECT COALESCE(SUM(stock_value),0) amount FROM '.$this->table('pi_balance').' WHERE entity='.$this->entity);
		$gl = $this->one('SELECT COALESCE(SUM(debit-credit),0) amount FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity.' AND numero_compte='.$this->str($c ? $c->stock_account : ''));
		$received = $this->one('SELECT COALESCE(SUM(value_delta),0) amount FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND contra_account='.$this->str($c ? $c->grni_account : ''));
		$billed = $this->one('SELECT COALESCE(SUM(m.amount),0) amount FROM '.$this->table('pi_match').' m JOIN '.$this->table('facture_fourn').' f ON f.rowid=m.fk_invoice AND f.entity=m.entity WHERE m.entity='.$this->entity.' AND f.fk_statut<>0');
		$grni = $this->one('SELECT COALESCE(SUM(credit-debit),0) amount FROM '.$this->table('accounting_bookkeeping').' WHERE entity='.$this->entity.' AND numero_compte='.$this->str($c ? $c->grni_account : ''));
		$physical = $this->rows('SELECT p.ref, w.ref warehouse, ps.fk_product, ps.fk_entrepot, ps.reel native_qty, COALESCE(b.qty,0) ledger_qty FROM '.$this->table('product_stock').' ps JOIN '.$this->table('product').' p ON p.rowid=ps.fk_product JOIN '.$this->table('entrepot').' w ON w.rowid=ps.fk_entrepot LEFT JOIN '.$this->table('pi_balance').' b ON b.fk_product=ps.fk_product AND b.fk_warehouse=ps.fk_entrepot AND b.entity='.$this->entity.' WHERE w.entity='.$this->entity.' AND p.fk_product_type=0 AND p.stockable_product=1 AND ABS(ps.reel-COALESCE(b.qty,0))>0.0000001');
		$missing = $this->rows('SELECT p.ref, w.ref warehouse, b.fk_product, b.fk_warehouse fk_entrepot, 0 native_qty, b.qty ledger_qty FROM '.$this->table('pi_balance').' b JOIN '.$this->table('product').' p ON p.rowid=b.fk_product JOIN '.$this->table('entrepot').' w ON w.rowid=b.fk_warehouse LEFT JOIN '.$this->table('product_stock').' ps ON ps.fk_product=b.fk_product AND ps.fk_entrepot=b.fk_warehouse WHERE b.entity='.$this->entity.' AND ps.rowid IS NULL AND ABS(b.qty)>0.0000001');
		$firstEntry = $this->one('SELECT MIN(date_post) date_post FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity);
		$wrongSupplierPostings = array();
		if ($firstEntry && $firstEntry->date_post) {
			$wrongSupplierPostings = $this->rows('SELECT j.doc_date,j.doc_ref,j.numero_compte,j.label_compte,SUM(j.debit-j.credit) amount FROM '.$this->table('accounting_bookkeeping')." j WHERE j.entity=".$this->entity." AND j.doc_type='supplier_invoice' AND j.doc_date>=".$this->str(substr($firstEntry->date_post, 0, 10)).' AND j.numero_compte<>'.$this->str($c ? $c->grni_account : '')." AND (j.numero_compte=".$this->str($c ? $c->stock_account : '')." OR j.numero_compte LIKE '5%') GROUP BY j.doc_date,j.doc_ref,j.numero_compte,j.label_compte ORDER BY j.doc_date,j.doc_ref");
		}
		$grniExpected = $received->amount - $billed->amount;
		return array('ledger' => (float) $ledger->amount, 'gl' => (float) $gl->amount, 'difference' => round($ledger->amount - $gl->amount, 8), 'quantity_errors' => array_merge($physical, $missing), 'grni_expected' => $grniExpected, 'grni_gl' => (float) $grni->amount, 'grni_difference' => round($grniExpected - $grni->amount, 8), 'wrong_supplier_postings' => $wrongSupplierPostings);
	}
}
