<?php

/** Safe, preview-first renumbering for document types managed by Naming Series. */
class NamingSeriesDocumentRenumber
{
	private $db;
	private $ns;
	private $entity;
	private $integrations = array(
		'sales_proposal' => array('PROPALE_ADDON', 'mod_propale_namingseries'), 'sales_order' => array('COMMANDE_ADDON', 'mod_commande_namingseries'),
		'customer_invoice' => array('FACTURE_ADDON', 'mod_facture_namingseries'), 'supplier_proposal' => array('SUPPLIER_PROPOSAL_ADDON', 'mod_supplier_proposal_namingseries'),
		'supplier_order' => array('COMMANDE_SUPPLIER_ADDON_NUMBER', 'mod_commande_fournisseur_namingseries'), 'supplier_invoice' => array('INVOICE_SUPPLIER_ADDON_NUMBER', 'mod_facture_fournisseur_namingseries'),
		'shipment' => array('EXPEDITION_ADDON_NUMBER', 'mod_expedition_namingseries'), 'reception' => array('RECEPTION_ADDON_NUMBER', 'mod_reception_namingseries'),
		'customer_payment' => array('PAYMENT_ADDON', 'mod_payment_namingseries'), 'supplier_payment' => array('SUPPLIER_PAYMENT_ADDON', 'mod_supplier_payment_namingseries'),
		'contract' => array('CONTRACT_ADDON', 'mod_contract_namingseries'), 'intervention' => array('FICHEINTER_ADDON', 'mod_fichinter_namingseries'),
		'project' => array('PROJECT_ADDON', 'mod_project_namingseries'), 'project_task' => array('PROJECT_TASK_ADDON', 'mod_task_namingseries'),
		'expense_report' => array('EXPENSEREPORT_ADDON', 'mod_expensereport_namingseries'), 'manufacturing_order' => array('MRP_MO_ADDON', 'mod_mo_namingseries'),
		'bom' => array('BOM_ADDON', 'mod_bom_namingseries'), 'stock_transfer' => array('STOCKTRANSFER_STOCKTRANSFER_ADDON', 'mod_stocktransfer_namingseries'),
		'bookkeeping' => array('BOOKKEEPING_ADDON', 'mod_bookkeeping_namingseries'), 'asset' => array('ASSET_ASSET_ADDON', 'mod_asset_namingseries'),
		'cheque_receipt' => array('CHEQUERECEIPTS_ADDON', 'mod_chequereceipt_namingseries'), 'delivery' => array('DELIVERY_ADDON_NUMBER', 'mod_delivery_namingseries'),
		'leave_request' => array('HOLIDAY_ADDON', 'mod_holiday_namingseries'), 'hr_evaluation' => array('HRMTEST_EVALUATION_ADDON', 'mod_evaluation_namingseries'),
		'product_lot' => array('PRODUCTBATCH_LOT_ADDON', 'mod_lot_namingseries'), 'product_serial' => array('PRODUCTBATCH_SN_ADDON', 'mod_sn_namingseries'),
		'takepos_invoice' => array('TAKEPOS_REF_ADDON', 'mod_takepos_ref_namingseries'), 'ticket' => array('TICKET_ADDON', 'mod_ticket_namingseries'),
		'workstation' => array('WORKSTATION_WORKSTATION_ADDON', 'mod_workstation_namingseries'),
	);
	private $definitions = array(
		'sales_proposal' => array('table' => 'propal', 'field' => 'ref', 'dates' => array('datep', 'datec'), 'root' => 'propale'),
		'sales_order' => array('table' => 'commande', 'field' => 'ref', 'dates' => array('date_commande', 'date_creation'), 'root' => 'commande'),
		'customer_invoice' => array('table' => 'facture', 'field' => 'ref', 'dates' => array('datef', 'datec'), 'root' => 'facture', 'where' => "(pos_source IS NULL OR pos_source='')"),
		'supplier_proposal' => array('table' => 'supplier_proposal', 'field' => 'ref', 'dates' => array('datec', 'date_valid'), 'root' => 'supplier_proposal'),
		'supplier_order' => array('table' => 'commande_fournisseur', 'field' => 'ref', 'dates' => array('date_commande', 'date_creation'), 'root' => 'fournisseur/commande'),
		'supplier_invoice' => array('table' => 'facture_fourn', 'field' => 'ref', 'dates' => array('datef', 'datec'), 'root' => 'fournisseur/facture'),
		'shipment' => array('table' => 'expedition', 'field' => 'ref', 'dates' => array('date_expedition', 'date_delivery', 'date_creation'), 'root' => 'expedition/sending'),
		'reception' => array('table' => 'reception', 'field' => 'ref', 'dates' => array('date_reception', 'date_delivery', 'date_creation'), 'root' => 'reception'),
		'customer_payment' => array('table' => 'paiement', 'field' => 'ref', 'dates' => array('datep', 'datec')),
		'supplier_payment' => array('table' => 'paiementfourn', 'field' => 'ref', 'dates' => array('datep', 'datec'), 'root' => 'fournisseur/payment'),
		'contract' => array('table' => 'contrat', 'field' => 'ref', 'dates' => array('date_contrat', 'datec'), 'root' => 'contract'),
		'intervention' => array('table' => 'fichinter', 'field' => 'ref', 'dates' => array('datei', 'datec'), 'root' => 'ficheinter'),
		'project' => array('table' => 'projet', 'field' => 'ref', 'dates' => array('dateo', 'datec'), 'root' => 'projet'),
		'project_task' => array('table' => 'projet_task', 'field' => 'ref', 'dates' => array('dateo', 'datec')),
		'expense_report' => array('table' => 'expensereport', 'field' => 'ref', 'dates' => array('date_debut', 'date_create'), 'root' => 'expensereport'),
		'manufacturing_order' => array('table' => 'mrp_mo', 'field' => 'ref', 'dates' => array('date_start_planned', 'date_creation'), 'root' => 'mrp'),
		'bom' => array('table' => 'bom_bom', 'field' => 'ref', 'dates' => array('date_creation'), 'root' => 'bom'),
		'stock_transfer' => array('table' => 'stocktransfer_stocktransfer', 'field' => 'ref', 'dates' => array('date_reelle_depart', 'date_prevue_depart', 'date_creation'), 'root' => 'stocktransfer'),
		'bookkeeping' => array('table' => 'accounting_bookkeeping', 'field' => 'ref', 'dates' => array('doc_date', 'date_creation'), 'root' => 'accounting', 'group_ref' => true),
		'asset' => array('table' => 'asset', 'field' => 'ref', 'dates' => array('date_start', 'date_creation'), 'root' => 'asset'),
		'cheque_receipt' => array('table' => 'bordereau_cheque', 'field' => 'ref', 'dates' => array('date_bordereau', 'datec'), 'root' => 'bank/checkdeposits'),
		'delivery' => array('table' => 'delivery', 'field' => 'ref', 'dates' => array('date_delivery', 'date_creation'), 'root' => 'delivery'),
		'leave_request' => array('table' => 'holiday', 'field' => 'ref', 'dates' => array('date_debut', 'date_create'), 'root' => 'holiday'),
		'hr_evaluation' => array('table' => 'hrm_evaluation', 'field' => 'ref', 'dates' => array('date_eval', 'date_creation'), 'root' => 'hrm/evaluation'),
		'product_lot' => array('table' => 'product_lot', 'field' => 'batch', 'dates' => array('datec'), 'root' => 'produit', 'pattern_filter' => true),
		// Lot and serial records share one native table without a discriminator.
		// Serial uses the same safe engine but is filtered by the literal prefix of its active pattern.
		'product_serial' => array('table' => 'product_lot', 'field' => 'batch', 'dates' => array('datec'), 'root' => 'produit', 'pattern_filter' => true),
		'takepos_invoice' => array('table' => 'facture', 'field' => 'ref', 'dates' => array('datef', 'datec'), 'root' => 'facture', 'where' => "pos_source IS NOT NULL AND pos_source<>''"),
		'ticket' => array('table' => 'ticket', 'field' => 'ref', 'dates' => array('datec'), 'root' => 'ticket'),
		'workstation' => array('table' => 'workstation_workstation', 'field' => 'ref', 'dates' => array('date_creation'), 'root' => 'workstation'),
	);

	public function __construct($db, NamingSeries $ns, $entity)
	{
		$this->db = $db;
		$this->ns = $ns;
		$this->entity = (int) $entity;
	}

	public function catalog()
	{
		$catalog = NamingSeries::documentTypes();
		foreach ($catalog as $type => &$item) {
			$item['available'] = !empty($this->definitions[$type]) && $this->tableExists($this->definitions[$type]['table']);
			$integration = $this->integrations[$type] ?? array('', '');
			$item['numbering_model'] = $integration[0] ? getDolGlobalString($integration[0]) : '';
			$item['integration_active'] = $item['numbering_model'] === $integration[1];
		}
		return $catalog;
	}

	public function defaultScope($type)
	{
		$series = $this->ns->resolveSeries($type);
		if (!$series) return '';
		return $this->ns->scopeKey($series, dol_now());
	}

	public function supportsType($type)
	{
		return !empty($this->definitions[$type]) && $this->tableExists($this->definitions[$type]['table']);
	}

	public function document($type, $id)
	{
		if (!$this->supportsType($type)) throw new InvalidArgumentException('Document type belum didukung untuk rename.');
		$d = $this->definitions[$type];
		$columns = $this->columns($d['table']);
		$dateField = '';
		foreach ($d['dates'] as $candidate) if (in_array($candidate, $columns, true)) { $dateField = $candidate; break; }
		$sql = 'SELECT rowid,`'.$d['field'].'` document_ref'.($dateField ? ',`'.$dateField.'` document_date' : ',NULL document_date')
			.' FROM '.MAIN_DB_PREFIX.$d['table'].' WHERE entity='.$this->entity.' AND rowid='.(int) $id;
		if (!empty($d['where'])) $sql .= ' AND ('.$d['where'].')';
		$res = $this->db->query($sql);
		$row = $res ? $this->db->fetch_object($res) : null;
		if (!$row) throw new RuntimeException('Dokumen tidak ditemukan pada entity aktif.');
		return $row;
	}

	/** Rename one document reference without changing its internal ID. */
	public function renameOne($type, $id, $newReference, $expectedOldReference, $userId)
	{
		$document = $this->document($type, $id);
		$oldReference = (string) $document->document_ref;
		$newReference = trim((string) $newReference);
		if (!hash_equals($oldReference, (string) $expectedOldReference)) throw new RuntimeException('Nomor dokumen berubah sejak halaman dibuka. Muat ulang lalu coba lagi.');
		if ($newReference === '' || preg_match('/[\x00-\x1F\x7F]/', $newReference)) throw new InvalidArgumentException('Nomor dokumen baru tidak valid.');
		if ($newReference === $oldReference) throw new InvalidArgumentException('Nomor baru masih sama dengan nomor sekarang.');
		if (strpos($newReference, '__NS_') === 0) throw new InvalidArgumentException('Prefix nomor tersebut dipakai secara internal.');

		$d = $this->definitions[$type];
		$maxLength = $this->columnLength($d['table'], $d['field']);
		if ($maxLength > 0 && mb_strlen($newReference) > $maxLength) throw new InvalidArgumentException('Nomor dokumen maksimal '.$maxLength.' karakter.');

		$ids = array((int) $id);
		if (!empty($d['group_ref'])) {
			$res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$d['table'].' WHERE entity='.$this->entity
				." AND `".$d['field']."`='".$this->db->escape($oldReference)."'");
			$ids = array();
			if ($res) while ($row = $this->db->fetch_object($res)) $ids[] = (int) $row->rowid;
		}
		$this->assertReferenceUnambiguous($type, $ids, $oldReference);
		$res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$d['table'].' WHERE entity='.$this->entity
			." AND `".$d['field']."`='".$this->db->escape($newReference)."' AND rowid NOT IN (".implode(',', $ids).') LIMIT 1');
		if (!$res) throw new RuntimeException($this->db->lasterror());
		if ($this->db->num_rows($res)) throw new RuntimeException('Nomor tujuan sudah digunakan oleh dokumen lain: '.$newReference);

		$series = $this->ns->resolveSeries($type, (object) array('id' => (int) $id));
		$timestamp = !empty($document->document_date) ? strtotime((string) $document->document_date) : dol_now();
		$scope = $series ? $this->ns->scopeKey($series, $timestamp) : 'manual';
		$plan = array(
			'type' => $type, 'scope' => $scope, 'series_id' => $series ? (int) $series->rowid : 0,
			'table' => $d['table'], 'field' => $d['field'],
			'mappings' => array(array('id' => (int) $id, 'ids' => $ids, 'old' => $oldReference, 'new' => $newReference,
				'date' => $timestamp ? date('Y-m-d', $timestamp) : '')),
		);
		$plan['hash'] = hash('sha256', json_encode($plan));

		$textColumns = $this->relatedTextColumns($d);
		$filesystem = array();
		try {
			$this->stageDirectories($d, $plan, '', $filesystem);
		} catch (Throwable $e) {
			$this->rollbackDirectories($filesystem);
			throw $e;
		}

		if (!$this->db->begin()) {
			$this->rollbackDirectories($filesystem);
			throw new RuntimeException('Transaksi database tidak dapat dimulai.');
		}
		try {
			$locked = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$d['table'].' WHERE entity='.$this->entity
				.' AND rowid IN ('.implode(',', $ids).") AND `".$d['field']."`='".$this->db->escape($oldReference)."' FOR UPDATE");
			if (!$locked || $this->db->num_rows($locked) !== count($ids)) throw new RuntimeException('Dokumen berubah saat proses rename; transaksi dibatalkan.');
			$tmp = '__NS_'.$id.'_'.substr($plan['hash'], 0, 8).'__';
			$primaryRowId = count($ids) === 1 ? (int) $id : 0;
			$this->setPrimaryReference($d, $ids, $oldReference, $tmp);
			foreach ($textColumns as $table => $columns) {
				$this->replace($table, $columns, $oldReference, $tmp, $table === $d['table'] ? $primaryRowId : 0);
			}
			$this->setPrimaryReference($d, $ids, $tmp, $newReference);
			foreach ($textColumns as $table => $columns) {
				$this->replace($table, $columns, $tmp, $newReference, $table === $d['table'] ? $primaryRowId : 0);
			}
			$this->finishDirectories($plan, $filesystem);
			$this->ns->audit($series ? (int) $series->rowid : 0, 'RENAME', $scope, $oldReference, $newReference, $type, (int) $id, $newReference, $userId);
			$check = $this->document($type, $id);
			if ((string) $check->document_ref !== $newReference) throw new RuntimeException('Verifikasi hasil rename gagal.');
			if (!$this->db->commit()) throw new RuntimeException('Commit database gagal.');
		} catch (Throwable $e) {
			$this->db->rollback();
			$this->rollbackDirectories($filesystem);
			throw $e;
		}
		return array('old' => $oldReference, 'new' => $newReference, 'documents' => count($filesystem));
	}

	public function preview($type, $scope)
	{
		if (empty($this->definitions[$type])) throw new InvalidArgumentException('Document type belum didukung untuk renumber.');
		$d = $this->definitions[$type];
		if (!$this->tableExists($d['table'])) throw new RuntimeException('Tabel modul untuk document type ini belum tersedia.');
		$series = $this->ns->resolveSeries($type);
		if (!$series) throw new RuntimeException('Naming Series aktif tidak ditemukan.');
		$this->ns->validatePattern($series->pattern);
		$bounds = $this->scopeBounds($series, trim($scope));
		$columns = $this->columns($d['table']);
		if (!in_array($d['field'], $columns, true)) throw new RuntimeException('Kolom referensi tidak tersedia.');
		$dateField = '';
		foreach ($d['dates'] as $candidate) if (in_array($candidate, $columns, true)) { $dateField = $candidate; break; }
		if (!$dateField) throw new RuntimeException('Kolom tanggal dokumen tidak tersedia.');

		$creationField = in_array('datec', $columns, true) ? 'datec' : (in_array('date_creation', $columns, true) ? 'date_creation' : '');
		if (!empty($d['group_ref'])) {
			$select = 'MIN(d.rowid) rowid,GROUP_CONCAT(d.rowid ORDER BY d.rowid) row_ids,d.`'.$d['field'].'` old_ref,MIN(d.`'.$dateField.'`) document_date';
			$select .= $creationField ? ',MIN(d.`'.$creationField.'`) creation_date' : ',NULL creation_date';
			$select .= ",'' code_client,'' code_fournisseur,'' product_ref";
		} else {
			$select = 'd.rowid,CAST(d.rowid AS CHAR) row_ids,d.`'.$d['field'].'` old_ref,d.`'.$dateField.'` document_date';
			$select .= $creationField ? ',d.`'.$creationField.'` creation_date' : ',NULL creation_date';
			if ($type === 'customer_payment') {
				$select .= ',(SELECT s2.code_client FROM '.MAIN_DB_PREFIX.'paiement_facture l2 JOIN '.MAIN_DB_PREFIX.'facture i2 ON i2.rowid=l2.fk_facture JOIN '.MAIN_DB_PREFIX.'societe s2 ON s2.rowid=i2.fk_soc WHERE l2.fk_paiement=d.rowid ORDER BY l2.rowid LIMIT 1) code_client,\'\' code_fournisseur';
			} elseif ($type === 'supplier_payment') {
				$select .= ',\'\' code_client,(SELECT s2.code_fournisseur FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn l2 JOIN '.MAIN_DB_PREFIX.'facture_fourn i2 ON i2.rowid=l2.fk_facturefourn JOIN '.MAIN_DB_PREFIX.'societe s2 ON s2.rowid=i2.fk_soc WHERE l2.fk_paiementfourn=d.rowid ORDER BY l2.rowid LIMIT 1) code_fournisseur';
			} else {
				$select .= in_array('fk_soc', $columns, true) ? ',s.code_client,s.code_fournisseur' : ",'' code_client,'' code_fournisseur";
			}
			$select .= in_array('fk_product', $columns, true) ? ',p.ref product_ref' : ",'' product_ref";
		}
		$sql = 'SELECT '.$select.' FROM '.MAIN_DB_PREFIX.$d['table'].' d';
		if (in_array('fk_soc', $columns, true)) $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc';
		if (in_array('fk_product', $columns, true)) $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=d.fk_product';
		$sql .= ' WHERE d.entity='.$this->entity;
		if ($bounds['start']) $sql .= " AND d.`".$dateField."`>='".$this->db->escape($bounds['start'])."'";
		if ($bounds['end']) $sql .= " AND d.`".$dateField."`<'".$this->db->escape($bounds['end'])."'";
		if (!empty($d['where'])) $sql .= ' AND ('.$d['where'].')';
		if (!empty($d['pattern_filter'])) {
			$prefix = strstr($series->pattern, '{', true);
			if ($prefix === false || $prefix === '') throw new RuntimeException('Product Serial memerlukan pattern dengan prefix literal agar dapat dibedakan dari lot.');
			$sql .= " AND d.`".$d['field']."` LIKE '".$this->db->escape($prefix)."%'";
		}
		if (!empty($d['group_ref'])) $sql .= ' GROUP BY d.`'.$d['field'].'` ORDER BY document_date ASC,creation_date ASC,rowid ASC';
		else $sql .= ' ORDER BY d.`'.$dateField.'` ASC,creation_date ASC,d.rowid ASC';
		$res = $this->db->query($sql);
		if (!$res) throw new RuntimeException($this->db->lasterror());
		$mappings = array();
		$number = max(1, (int) $series->start_value);
		while ($row = $this->db->fetch_object($res)) {
			$timestamp = strtotime((string) $row->document_date);
			$object = (object) array('id' => (int) $row->rowid, 'product_ref' => (string) $row->product_ref);
			$party = (object) array('code_client' => (string) ($row->code_client ?? ''), 'code_fournisseur' => (string) ($row->code_fournisseur ?? ''));
			$new = $this->ns->render($series, $number++, $timestamp, $object, $party);
			$mappings[] = array('id' => (int) $row->rowid, 'ids' => array_map('intval', explode(',', (string) $row->row_ids)), 'old' => (string) $row->old_ref, 'new' => $new, 'date' => date('Y-m-d', $timestamp));
		}
		$plan = array('type' => $type, 'scope' => $bounds['scope'], 'series_id' => (int) $series->rowid, 'pattern' => $series->pattern,
			'reset_period' => $series->reset_period, 'table' => $d['table'], 'field' => $d['field'], 'date_field' => $dateField, 'mappings' => $mappings);
		$plan['hash'] = hash('sha256', json_encode($plan));
		return $plan;
	}

	public function apply($type, $scope, $expectedHash, $userId, $allowInactiveModel = false)
	{
		$integration = $this->integrations[$type] ?? array('', '');
		if (!$allowInactiveModel && $integration[0] && getDolGlobalString($integration[0]) !== $integration[1]) {
			throw new RuntimeException('Numbering model untuk document type ini belum memakai NamingSeries. Aktifkan model NamingSeries terlebih dahulu.');
		}
		$plan = $this->preview($type, $scope);
		if (!$plan['mappings']) throw new RuntimeException('Tidak ada dokumen pada scope tersebut.');
		if (!hash_equals($plan['hash'], (string) $expectedHash)) throw new RuntimeException('Data berubah sejak preview. Tampilkan preview ulang.');
		$d = $this->definitions[$type];
		$targets = array_column($plan['mappings'], 'new');
		if (count($targets) !== count(array_unique($targets))) throw new RuntimeException('Pattern menghasilkan referensi duplikat.');
		$ids = array(); foreach ($plan['mappings'] as $mapping) $ids = array_merge($ids, $mapping['ids']);
		$ids = array_values(array_unique(array_map('intval', $ids)));
		foreach ($targets as $target) {
			$res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$d['table'].' WHERE entity='.$this->entity
				." AND `".$d['field']."`='".$this->db->escape($target)."' AND rowid NOT IN (".implode(',', $ids).') LIMIT 1');
			if (!$res || $this->db->num_rows($res)) throw new RuntimeException('Referensi tujuan sudah digunakan: '.$target);
		}

		$textColumns = $this->relatedTextColumns($d);
		$backup = $this->backup($plan, $textColumns);
		$filesystem = array();
		try {
			$this->stageDirectories($d, $plan, $backup['directory'], $filesystem);
		} catch (Throwable $e) {
			$this->rollbackDirectories($filesystem);
			throw $e;
		}
		if (!$this->db->begin()) {
			$this->rollbackDirectories($filesystem);
			throw new RuntimeException('Transaksi database tidak dapat dimulai.');
		}
		try {
			foreach ($plan['mappings'] as $mapping) {
				$tmp = '__NS_'.$mapping['id'].'_'.substr($plan['hash'], 0, 8).'__';
				$primaryRowId = count($mapping['ids']) === 1 ? $mapping['id'] : 0;
				$this->setPrimaryReference($d, $mapping['ids'], $mapping['old'], $tmp);
				foreach ($textColumns as $table => $columns) {
					if ($table === $d['table']) $this->replace($table, $columns, $mapping['old'], $tmp, $primaryRowId);
					else $this->replace($table, $columns, $mapping['old'], $tmp);
				}
			}
			foreach ($plan['mappings'] as $mapping) {
				$tmp = '__NS_'.$mapping['id'].'_'.substr($plan['hash'], 0, 8).'__';
				$primaryRowId = count($mapping['ids']) === 1 ? $mapping['id'] : 0;
				$this->setPrimaryReference($d, $mapping['ids'], $tmp, $mapping['new']);
				foreach ($textColumns as $table => $columns) {
					if ($table === $d['table']) $this->replace($table, $columns, $tmp, $mapping['new'], $primaryRowId);
					else $this->replace($table, $columns, $tmp, $mapping['new']);
				}
				if ($mapping['old'] !== $mapping['new']) $this->ns->audit($plan['series_id'], 'RENUMBER', $plan['scope'], $mapping['old'], $mapping['new'], $type, $mapping['id'], $mapping['new'], $userId);
			}
			$this->finishDirectories($plan, $filesystem);
			$series = $this->ns->getSeries($plan['series_id']);
			$this->ns->setCounter($plan['series_id'], $plan['scope'], max(0, count($plan['mappings']) + max(1, (int) $series->start_value) - 1), $userId);
			$verification = $this->preview($type, $scope);
			if (array_column($verification['mappings'], 'id') !== array_column($plan['mappings'], 'id') || array_column($verification['mappings'], 'new') !== array_column($verification['mappings'], 'old')) {
				throw new RuntimeException('Verifikasi hasil renumber gagal.');
			}
			if (!$this->db->commit()) throw new RuntimeException('Commit database gagal.');
		} catch (Throwable $e) {
			$this->db->rollback();
			$this->rollbackDirectories($filesystem);
			throw $e;
		}
		return array('plan' => $plan, 'backup' => $backup['file'], 'documents' => count($filesystem));
	}

	private function scopeBounds($series, $scope)
	{
		$reset = strtolower((string) $series->reset_period);
		if ($reset === 'daily') {
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scope)) throw new InvalidArgumentException('Scope harian wajib YYYY-MM-DD.');
			return array('scope' => $scope, 'start' => $scope.' 00:00:00', 'end' => date('Y-m-d H:i:s', strtotime($scope.' +1 day')));
		}
		if ($reset === 'monthly') {
			if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $scope)) throw new InvalidArgumentException('Scope bulanan wajib YYYY-MM.');
			return array('scope' => $scope, 'start' => $scope.'-01 00:00:00', 'end' => date('Y-m-d H:i:s', strtotime($scope.'-01 +1 month')));
		}
		if ($reset === 'yearly') {
			if (!preg_match('/^\d{4}$/', $scope)) throw new InvalidArgumentException('Scope tahunan wajib YYYY.');
			return array('scope' => $scope, 'start' => $scope.'-01-01 00:00:00', 'end' => ((int) $scope + 1).'-01-01 00:00:00');
		}
		if ($scope !== 'global') throw new InvalidArgumentException('Series tanpa reset memakai scope global.');
		return array('scope' => 'global', 'start' => '', 'end' => '');
	}

	private function replace($table, $columns, $from, $to, $rowId = 0)
	{
		foreach ($columns as $column) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.$table.' SET `'.$column.'`=REPLACE(`'.$column."`,'".$this->db->escape($from)."','".$this->db->escape($to)."')"
				." WHERE `".$column."` LIKE '%".$this->db->escape($from)."%'".($rowId ? ' AND rowid='.(int) $rowId : '');
			if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
		}
	}

	private function setPrimaryReference($definition, $ids, $from, $to)
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$definition['table']." SET `".$definition['field']."`='".$this->db->escape($to)."'"
			.' WHERE rowid IN ('.implode(',', $ids).") AND `".$definition['field']."`='".$this->db->escape($from)."'";
		$res = $this->db->query($sql);
		if (!$res) throw new RuntimeException($this->db->lasterror());
		if ($this->db->affected_rows($res) !== count($ids)) throw new RuntimeException('Dokumen berubah saat proses renumber; transaksi dibatalkan.');
	}

	private function backup($plan, $textColumns)
	{
		$base = DOL_DATA_ROOT.'/namingseries_backups/document_renumber_'.date('Ymd_His').'_'.preg_replace('/[^a-z0-9_-]/i', '_', $plan['type'].'_'.$plan['scope']);
		$dir = $base;
		for ($suffix = 2; is_dir($dir); $suffix++) $dir = $base.'_'.$suffix;
		if (!mkdir($dir, 0750, true)) throw new RuntimeException('Folder backup tidak dapat dibuat.');
		$payload = array('created_at' => date(DATE_ATOM), 'plan' => $plan, 'counter_before' => $this->ns->currentValue($plan['series_id'], $plan['scope']), 'documents' => array(), 'related_text_rows' => array());
		foreach ($plan['mappings'] as $mapping) {
			$res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.$plan['table'].' WHERE rowid IN ('.implode(',', array_map('intval', $mapping['ids'])).') ORDER BY rowid');
			$rows = array(); if ($res) while ($row = $this->db->fetch_array($res)) $rows[] = $row;
			$payload['documents'][] = $rows;
		}
		$refs = array_column($plan['mappings'], 'old');
		foreach ($textColumns as $table => $columns) {
			$where = array(); foreach ($columns as $column) foreach ($refs as $ref) $where[] = '`'.$column."` LIKE '%".$this->db->escape($ref)."%'";
			$res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' WHERE '.implode(' OR ', $where));
			if (!$res) throw new RuntimeException($this->db->lasterror());
			$payload['related_text_rows'][$table] = array(); while ($row = $this->db->fetch_array($res)) $payload['related_text_rows'][$table][] = $row;
		}
		$file = $dir.'/database.json';
		if (file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) throw new RuntimeException('Backup database tidak dapat ditulis.');
		return array('directory' => $dir, 'file' => $file);
	}

	private function stageDirectories($d, $plan, $backupDir, &$state)
	{
		if (empty($d['root'])) return;
		$root = DOL_DATA_ROOT.'/'.$d['root'];
		$sources = array();
		foreach ($plan['mappings'] as $mapping) $sources[] = $root.'/'.dol_sanitizeFileName($mapping['old']);
		foreach ($plan['mappings'] as $mapping) {
			$source = $root.'/'.dol_sanitizeFileName($mapping['old']);
			$destination = $root.'/'.dol_sanitizeFileName($mapping['new']);
			if (is_dir($source) && $mapping['old'] !== $mapping['new'] && file_exists($destination) && !in_array($destination, $sources, true)) {
				throw new RuntimeException('Folder tujuan dokumen sudah digunakan: '.$destination);
			}
		}
		foreach ($plan['mappings'] as $mapping) {
			$source = $root.'/'.dol_sanitizeFileName($mapping['old']);
			if (!is_dir($source) || $mapping['old'] === $mapping['new']) continue;
			$destination = $root.'/'.dol_sanitizeFileName($mapping['new']);
			$temporary = $root.'/.ns_renumber_'.$mapping['id'].'_'.substr($plan['hash'], 0, 8);
			if (file_exists($temporary)) throw new RuntimeException('Folder sementara sudah ada: '.$temporary);
			if ($backupDir !== '') $this->copyTree($source, $backupDir.'/documents/'.dol_sanitizeFileName($mapping['old']));
			if (!rename($source, $temporary)) throw new RuntimeException('Folder dokumen gagal dipindahkan: '.$source);
			$state[] = array('old' => $source, 'new' => $destination, 'tmp' => $temporary, 'old_ref' => $mapping['old'], 'new_ref' => $mapping['new'], 'stage' => 'tmp');
		}
	}

	private function finishDirectories($plan, &$state)
	{
		foreach ($state as &$item) {
			$this->renameTreeEntries($item['tmp'], $item['old_ref'], $item['new_ref']);
			if (file_exists($item['new']) || !rename($item['tmp'], $item['new'])) throw new RuntimeException('Folder tujuan dokumen sudah ada: '.$item['new']);
			$item['stage'] = 'new';
		}
	}

	private function rollbackDirectories(&$state)
	{
		foreach (array_reverse(array_keys($state)) as $key) {
			$item = &$state[$key]; $current = $item['stage'] === 'new' ? $item['new'] : $item['tmp'];
			if ($item['stage'] === 'new' && is_dir($current)) $this->renameTreeEntries($current, $item['new_ref'], $item['old_ref']);
			if (is_dir($current) && !file_exists($item['old'])) @rename($current, $item['old']);
		}
	}

	private function renameTreeEntries($root, $from, $to)
	{
		if (!is_dir($root)) return;
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $entry) if (strpos($entry->getBasename(), $from) !== false) {
			$target = $entry->getPath().'/'.str_replace($from, $to, $entry->getBasename());
			if (file_exists($target) || !rename($entry->getPathname(), $target)) throw new RuntimeException('File dokumen gagal diubah: '.$entry->getPathname());
		}
	}

	private function copyTree($source, $target)
	{
		if (!is_dir($target) && !mkdir($target, 0750, true)) throw new RuntimeException('Backup dokumen gagal dibuat.');
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($it as $entry) {
			$dest = $target.'/'.substr($entry->getPathname(), strlen($source) + 1);
			if ($entry->isDir()) { if (!is_dir($dest) && !mkdir($dest, 0750, true)) throw new RuntimeException('Backup folder gagal.'); }
			elseif (!copy($entry->getPathname(), $dest)) throw new RuntimeException('Backup file gagal.');
		}
	}

	private function availableTextColumns($wanted)
	{
		$out = array(); foreach ($wanted as $table => $columns) {
			if (!$this->tableExists($table)) continue; $available = $this->columns($table);
			$found = array_values(array_intersect($columns, $available)); if ($found) $out[$table] = $found;
		} return $out;
	}

	private function relatedTextColumns($definition)
	{
		$wanted = array(
			'actioncomm' => array('label', 'note'), 'accounting_bookkeeping' => array('doc_ref', 'label_operation'),
			'bank' => array('label', 'note'), 'ecm_files' => array('filepath', 'filename', 'fullpath_orig'),
			'ns_audit' => array('document_ref'), 'pi_document_action' => array('element_ref', 'snapshot_json'),
			'stock_mouvement' => array('label'),
		);
		if (empty($wanted[$definition['table']])) $wanted[$definition['table']] = array();
		$wanted[$definition['table']][] = 'last_main_doc';
		return $this->availableTextColumns($wanted);
	}

	private function columnLength($table, $column)
	{
		$res = $this->db->query('SHOW COLUMNS FROM '.MAIN_DB_PREFIX.$table." LIKE '".$this->db->escape($column)."'");
		$row = $res ? $this->db->fetch_object($res) : null;
		return $row && preg_match('/(?:var)?char\((\d+)\)/i', (string) $row->Type, $match) ? (int) $match[1] : 0;
	}

	private function assertReferenceUnambiguous($selectedType, $selectedIds, $reference)
	{
		$seen = array();
		foreach ($this->definitions as $type => $definition) {
			$key = $definition['table'].'|'.$definition['field'].'|'.(!empty($definition['where']) ? $definition['where'] : '');
			if (isset($seen[$key]) || !$this->tableExists($definition['table'])) continue;
			$seen[$key] = true;
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$definition['table'].' WHERE entity='.$this->entity
				." AND `".$definition['field']."`='".$this->db->escape($reference)."'";
			if (!empty($definition['where'])) $sql .= ' AND ('.$definition['where'].')';
			if ($definition['table'] === $this->definitions[$selectedType]['table'] && $definition['field'] === $this->definitions[$selectedType]['field']) {
				$sql .= ' AND rowid NOT IN ('.implode(',', array_map('intval', $selectedIds)).')';
			}
			$sql .= ' LIMIT 1';
			$res = $this->db->query($sql);
			if ($res && $this->db->num_rows($res)) {
				throw new RuntimeException('Nomor lama juga dipakai oleh dokumen lain. Rename langsung diblokir karena referensi teks terkait menjadi ambigu. Bedakan nomor tersebut terlebih dahulu.');
			}
		}
	}

	private function columns($table)
	{
		$res = $this->db->query('SHOW COLUMNS FROM '.MAIN_DB_PREFIX.$table); if (!$res) return array();
		$out = array(); while ($row = $this->db->fetch_object($res)) $out[] = $row->Field; return $out;
	}

	private function tableExists($table)
	{
		$res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$table)."'"); return $res && $this->db->num_rows($res) > 0;
	}
}
