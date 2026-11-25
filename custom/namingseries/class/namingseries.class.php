<?php

class NamingSeries
{
	public $db;
	public $entity;
	public $error = '';

	private $documentMap = array(
		'sales_proposal' => array('table' => 'propal', 'field' => 'ref', 'dates' => array('datep', 'date')),
		'sales_order' => array('table' => 'commande', 'field' => 'ref', 'dates' => array('date_commande', 'date')),
		'customer_invoice' => array('table' => 'facture', 'field' => 'ref', 'dates' => array('date', 'datef')),
		'supplier_proposal' => array('table' => 'supplier_proposal', 'field' => 'ref', 'dates' => array('date', 'date_valid')),
		'supplier_order' => array('table' => 'commande_fournisseur', 'field' => 'ref', 'dates' => array('date_commande', 'date')),
		'supplier_invoice' => array('table' => 'facture_fourn', 'field' => 'ref', 'dates' => array('date', 'datef')),
		'shipment' => array('table' => 'expedition', 'field' => 'ref', 'dates' => array('date_expedition', 'date_delivery', 'date')),
		'reception' => array('table' => 'reception', 'field' => 'ref', 'dates' => array('date_delivery', 'date_reception', 'date')),
		'customer_payment' => array('table' => 'paiement', 'field' => 'ref', 'dates' => array('datepaye', 'date')),
		'supplier_payment' => array('table' => 'paiementfourn', 'field' => 'ref', 'dates' => array('datepaye', 'date')),
		'contract' => array('table' => 'contrat', 'field' => 'ref', 'dates' => array('date_contrat', 'date')),
		'intervention' => array('table' => 'fichinter', 'field' => 'ref', 'dates' => array('date', 'datec')),
		'project' => array('table' => 'projet', 'field' => 'ref', 'dates' => array('date_start', 'date')),
		'project_task' => array('table' => 'projet_task', 'field' => 'ref', 'dates' => array('date_start', 'date')),
		'expense_report' => array('table' => 'expensereport', 'field' => 'ref', 'dates' => array('date_debut', 'date_create', 'date')),
		'manufacturing_order' => array('table' => 'mrp_mo', 'field' => 'ref', 'dates' => array('date_start_planned', 'date_creation', 'date')),
		'bom' => array('table' => 'bom_bom', 'field' => 'ref', 'dates' => array('date_creation', 'date')),
		'stock_transfer' => array('table' => 'stocktransfer_stocktransfer', 'field' => 'ref', 'dates' => array('date_transfer', 'date_creation', 'date')),
		'bookkeeping' => array('table' => 'accounting_bookkeeping', 'field' => 'ref', 'dates' => array('doc_date', 'date')),
		'asset' => array('table' => 'asset', 'field' => 'ref', 'dates' => array('date_start', 'date_creation', 'date')),
		'cheque_receipt' => array('table' => 'bordereau_cheque', 'field' => 'ref', 'dates' => array('date_bordereau', 'date')),
		'delivery' => array('table' => 'delivery', 'field' => 'ref', 'dates' => array('delivery_date', 'date')),
		'leave_request' => array('table' => 'holiday', 'field' => 'ref', 'dates' => array('date_create', 'date_debut', 'date')),
		'hr_evaluation' => array('table' => 'hrm_evaluation', 'field' => 'ref', 'dates' => array('date', 'date_creation')),
		'product_lot' => array('table' => 'product_lot', 'field' => 'batch', 'dates' => array('datec', 'date')),
		'product_serial' => array('table' => 'product_lot', 'field' => 'batch', 'dates' => array('datec', 'date')),
		'takepos_invoice' => array('table' => 'facture', 'field' => 'ref', 'dates' => array('date', 'datef')),
		'ticket' => array('table' => 'ticket', 'field' => 'ref', 'dates' => array('datec', 'date')),
		'workstation' => array('table' => 'workstation_workstation', 'field' => 'ref', 'dates' => array('date_creation', 'date')),
	);

	public static function documentTypes()
	{
		return array(
			'sales_proposal' => array('label' => 'Customer Proposal', 'code' => 'PROP_MONTHLY', 'pattern' => 'PROP-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'sales_order' => array('label' => 'Sales Order', 'code' => 'SO_MONTHLY', 'pattern' => 'SO-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'customer_invoice' => array('label' => 'Customer Invoice', 'code' => 'INV_MONTHLY', 'pattern' => 'INV-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'supplier_proposal' => array('label' => 'Supplier Proposal', 'code' => 'SUPQ_MONTHLY', 'pattern' => 'SUPQ-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'supplier_order' => array('label' => 'Supplier Order', 'code' => 'PO_MONTHLY', 'pattern' => 'PO-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'supplier_invoice' => array('label' => 'Supplier Invoice', 'code' => 'SINV_MONTHLY', 'pattern' => 'SINV-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'shipment' => array('label' => 'Shipment', 'code' => 'SHIP_MONTHLY', 'pattern' => 'SHIP-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'reception' => array('label' => 'Reception', 'code' => 'REC_MONTHLY', 'pattern' => 'REC-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'customer_payment' => array('label' => 'Customer Payment', 'code' => 'PAY_MONTHLY', 'pattern' => 'PAY-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'supplier_payment' => array('label' => 'Supplier Payment', 'code' => 'SPAY_MONTHLY', 'pattern' => 'SPAY-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'contract' => array('label' => 'Contract', 'code' => 'CTR_MONTHLY', 'pattern' => 'CTR-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'intervention' => array('label' => 'Intervention', 'code' => 'INT_MONTHLY', 'pattern' => 'INT-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'project' => array('label' => 'Project', 'code' => 'PRJ_YEARLY', 'pattern' => 'PRJ-{YYYY}-{####}', 'reset' => 'yearly'),
			'project_task' => array('label' => 'Project Task', 'code' => 'TASK_YEARLY', 'pattern' => 'TASK-{YYYY}-{####}', 'reset' => 'yearly'),
			'expense_report' => array('label' => 'Expense Report', 'code' => 'EXP_MONTHLY', 'pattern' => 'EXP-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'manufacturing_order' => array('label' => 'Manufacturing Order', 'code' => 'MO_MONTHLY', 'pattern' => 'MO-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'bom' => array('label' => 'Bill of Materials', 'code' => 'BOM_YEARLY', 'pattern' => 'BOM-{YYYY}-{####}', 'reset' => 'yearly'),
			'stock_transfer' => array('label' => 'Stock Transfer', 'code' => 'ST_MONTHLY', 'pattern' => 'ST-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'bookkeeping' => array('label' => 'Bookkeeping Entry', 'code' => 'BK_MONTHLY', 'pattern' => 'BK-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'asset' => array('label' => 'Asset', 'code' => 'AST_YEARLY', 'pattern' => 'AST-{YYYY}-{####}', 'reset' => 'yearly'),
			'cheque_receipt' => array('label' => 'Cheque Receipt', 'code' => 'CHQ_MONTHLY', 'pattern' => 'CHQ-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'delivery' => array('label' => 'Delivery', 'code' => 'DEL_MONTHLY', 'pattern' => 'DEL-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'leave_request' => array('label' => 'Leave Request', 'code' => 'LEAVE_YEARLY', 'pattern' => 'LEAVE-{YYYY}-{####}', 'reset' => 'yearly'),
			'hr_evaluation' => array('label' => 'HR Evaluation', 'code' => 'EVAL_YEARLY', 'pattern' => 'EVAL-{YYYY}-{####}', 'reset' => 'yearly'),
			'product_lot' => array('label' => 'Product Lot', 'code' => 'LOT_MONTHLY', 'pattern' => 'LOT-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'product_serial' => array('label' => 'Product Serial Number', 'code' => 'SN_MONTHLY', 'pattern' => 'SN-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'takepos_invoice' => array('label' => 'TakePOS Invoice', 'code' => 'POS_DAILY', 'pattern' => 'POS-{YYYY}-{MM}-{DD}-{####}', 'reset' => 'daily'),
			'ticket' => array('label' => 'Ticket', 'code' => 'TKT_MONTHLY', 'pattern' => 'TKT-{YYYY}-{MM}-{####}', 'reset' => 'monthly'),
			'workstation' => array('label' => 'Workstation', 'code' => 'WS_YEARLY', 'pattern' => 'WS-{YYYY}-{####}', 'reset' => 'yearly'),
		);
	}

	public function __construct($db, $entity = 0)
	{
		global $conf;
		$this->db = $db;
		$this->entity = $entity > 0 ? (int) $entity : (int) $conf->entity;
	}

	public function table($name)
	{
		return MAIN_DB_PREFIX.$name;
	}

	public function getSeries($id)
	{
		$sql = 'SELECT * FROM '.$this->table('ns_series').' WHERE rowid='.(int) $id.' AND entity='.$this->entity;
		$res = $this->db->query($sql);
		return $res ? $this->db->fetch_object($res) : null;
	}

	public function listSeries($documentType = '')
	{
		$sql = 'SELECT s.*, COUNT(c.rowid) counter_count FROM '.$this->table('ns_series').' s'
			.' LEFT JOIN '.$this->table('ns_counter').' c ON c.fk_series=s.rowid AND c.entity=s.entity'
			.' WHERE s.entity='.$this->entity;
		if ($documentType !== '') {
			$sql .= " AND s.document_type='".$this->db->escape($documentType)."'";
		}
		$sql .= ' GROUP BY s.rowid ORDER BY s.document_type,s.is_default DESC,s.label';
		$res = $this->db->query($sql);
		$out = array();
		if ($res) {
			while ($row = $this->db->fetch_object($res)) $out[] = $row;
		}
		return $out;
	}

	public function resolveSeries($documentType, $object = null)
	{
		$requested = '';
		$requestedId = 0;
		if (is_object($object)) {
			if (!empty($object->naming_series_code)) $requested = (string) $object->naming_series_code;
			if (!$requested && !empty($object->array_options['options_naming_series'])) $requested = (string) $object->array_options['options_naming_series'];
			if (!$requested && !empty($object->id)) {
				$choice = $this->documentChoice($documentType, (int) $object->id);
				if ($choice) $requestedId = (int) $choice->fk_series;
			}
		}
		$sql = 'SELECT * FROM '.$this->table('ns_series').' WHERE entity='.$this->entity
			." AND document_type='".$this->db->escape($documentType)."' AND active=1";
		if ($requestedId > 0) $sql .= ' AND rowid='.$requestedId;
		elseif ($requested !== '') $sql .= " AND code='".$this->db->escape($requested)."'";
		$sql .= ' ORDER BY is_default DESC,rowid ASC LIMIT 1';
		$res = $this->db->query($sql);
		return $res ? $this->db->fetch_object($res) : null;
	}

	public static function documentTypeForObject($object)
	{
		$element = is_object($object) && !empty($object->element) ? (string) $object->element : '';
		if ($element === 'facture' && !empty($object->pos_source)) return 'takepos_invoice';
		$map = array(
			'propal' => 'sales_proposal', 'commande' => 'sales_order', 'facture' => 'customer_invoice',
			'supplier_proposal' => 'supplier_proposal', 'order_supplier' => 'supplier_order', 'invoice_supplier' => 'supplier_invoice',
			'shipping' => 'shipment', 'reception' => 'reception', 'payment' => 'customer_payment', 'payment_supplier' => 'supplier_payment',
			'contrat' => 'contract', 'fichinter' => 'intervention', 'project' => 'project', 'project_task' => 'project_task',
			'expensereport' => 'expense_report', 'mo' => 'manufacturing_order', 'bom' => 'bom', 'stocktransfer' => 'stock_transfer',
			'accountingbookkeeping' => 'bookkeeping', 'asset' => 'asset', 'chequereceipt' => 'cheque_receipt', 'delivery' => 'delivery',
			'holiday' => 'leave_request', 'evaluation' => 'hr_evaluation', 'productlot' => 'product_lot',
			'ticket' => 'ticket', 'workstation' => 'workstation',
		);
		return isset($map[$element]) ? $map[$element] : '';
	}

	public static function numberingModelConstant($documentType)
	{
		$map = array(
			'sales_proposal' => 'PROPALE_ADDON', 'sales_order' => 'COMMANDE_ADDON', 'customer_invoice' => 'FACTURE_ADDON',
			'supplier_proposal' => 'SUPPLIER_PROPOSAL_ADDON', 'supplier_order' => 'COMMANDE_SUPPLIER_ADDON_NUMBER',
			'supplier_invoice' => 'INVOICE_SUPPLIER_ADDON_NUMBER', 'shipment' => 'EXPEDITION_ADDON_NUMBER',
			'reception' => 'RECEPTION_ADDON_NUMBER', 'customer_payment' => 'PAYMENT_ADDON', 'supplier_payment' => 'SUPPLIER_PAYMENT_ADDON',
			'contract' => 'CONTRACT_ADDON', 'intervention' => 'FICHEINTER_ADDON', 'project' => 'PROJECT_ADDON',
			'project_task' => 'PROJECT_TASK_ADDON', 'expense_report' => 'EXPENSEREPORT_ADDON', 'manufacturing_order' => 'MRP_MO_ADDON',
			'bom' => 'BOM_ADDON', 'stock_transfer' => 'STOCKTRANSFER_STOCKTRANSFER_ADDON', 'bookkeeping' => 'BOOKKEEPING_ADDON',
			'asset' => 'ASSET_ASSET_ADDON', 'cheque_receipt' => 'CHEQUERECEIPTS_ADDON', 'delivery' => 'DELIVERY_ADDON_NUMBER',
			'leave_request' => 'HOLIDAY_ADDON', 'hr_evaluation' => 'HRMTEST_EVALUATION_ADDON', 'product_lot' => 'PRODUCTBATCH_LOT_ADDON',
			'product_serial' => 'PRODUCTBATCH_SN_ADDON', 'takepos_invoice' => 'TAKEPOS_REF_ADDON', 'ticket' => 'TICKET_ADDON',
			'workstation' => 'WORKSTATION_WORKSTATION_ADDON',
		);
		return isset($map[$documentType]) ? $map[$documentType] : '';
	}

	public static function usesNamingSeriesModel($documentType)
	{
		$constant = self::numberingModelConstant($documentType);
		return $constant !== '' && strpos(strtolower(getDolGlobalString($constant)), 'namingseries') !== false;
	}

	public function documentChoice($documentType, $objectId)
	{
		$sql = 'SELECT * FROM '.$this->table('ns_document_choice').' WHERE entity='.$this->entity
			." AND document_type='".$this->db->escape($documentType)."' AND fk_object=".(int) $objectId.' LIMIT 1';
		$res = $this->db->query($sql);
		return $res ? $this->db->fetch_object($res) : null;
	}

	public function saveDocumentChoice($documentType, $objectId, $seriesCode, $userId)
	{
		if ($objectId < 1 || $seriesCode === '') return 0;
		$probe = (object) array('naming_series_code' => $seriesCode);
		$series = $this->resolveSeries($documentType, $probe);
		if (!$series) throw new Exception('Naming Series yang dipilih tidak aktif atau tidak sesuai dengan jenis dokumen.');
		$sql = 'INSERT INTO '.$this->table('ns_document_choice')
			.' (entity,document_type,fk_object,fk_series,fk_user,datec) VALUES ('
			.$this->entity.",'".$this->db->escape($documentType)."',".(int) $objectId.','.(int) $series->rowid.','.(int) $userId.",'".$this->db->idate(dol_now())."')"
			.' ON DUPLICATE KEY UPDATE fk_series=VALUES(fk_series),fk_user=VALUES(fk_user)';
		if (!$this->db->query($sql)) throw new Exception($this->db->lasterror());
		return (int) $series->rowid;
	}

	public function deleteDocumentChoice($documentType, $objectId)
	{
		return $this->db->query('DELETE FROM '.$this->table('ns_document_choice').' WHERE entity='.$this->entity
			." AND document_type='".$this->db->escape($documentType)."' AND fk_object=".(int) $objectId);
	}

	public function validatePattern($pattern)
	{
		if (!preg_match('/\{(#+)\}/', $pattern, $counter)) {
			throw new Exception('Pattern wajib memiliki satu counter seperti {####}.');
		}
		if (preg_match_all('/\{(#+)\}/', $pattern, $all) && count($all[0]) !== 1) {
			throw new Exception('Pattern hanya boleh memiliki satu counter.');
		}
		if (preg_match_all('/\{([^}]+)\}/', $pattern, $tokens)) {
			$allowed = array('YYYY', 'YY', 'MM', 'DD', 'ENTITY', 'CUSTOMER_CODE', 'SUPPLIER_CODE', 'PRODUCT_REF', 'DOCUMENT_TYPE', 'USER');
			foreach ($tokens[1] as $token) {
				if (preg_match('/^#+$/', $token)) continue;
				if (!in_array(strtoupper($token), $allowed, true)) throw new Exception('Token tidak didukung: {'.$token.'}.');
			}
		}
		if (strlen($pattern) > 255) throw new Exception('Pattern terlalu panjang.');
		return true;
	}

	public function scopeKey($series, $timestamp)
	{
		$period = strtolower((string) $series->reset_period);
		$parts = $this->calendarParts($timestamp);
		if ($period === 'daily') return $parts['Y'].'-'.$parts['m'].'-'.$parts['d'];
		if ($period === 'monthly') return $parts['Y'].'-'.$parts['m'];
		if ($period === 'yearly') return $parts['Y'];
		return 'global';
	}

	private function calendarParts($timestamp)
	{
		$value = dol_print_date($timestamp, '%Y-%m-%d', 'tzuserrel');
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) $value = date('Y-m-d', $timestamp);
		if (!isset($match[1])) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match);
		return array('Y' => $match[1], 'y' => substr($match[1], -2), 'm' => $match[2], 'd' => $match[3]);
	}

	public function render($series, $number, $timestamp, $object = null, $thirdparty = null)
	{
		$pattern = (string) $series->pattern;
		$parts = $this->calendarParts($timestamp);
		preg_match('/\{(#+)\}/', $pattern, $counter);
		$width = strlen($counter[1]);
		$customerCode = '';
		$supplierCode = '';
		$productRef = '';
		if (is_object($thirdparty)) {
			$customerCode = (string) (!empty($thirdparty->code_client) ? $thirdparty->code_client : '');
			$supplierCode = (string) (!empty($thirdparty->code_fournisseur) ? $thirdparty->code_fournisseur : '');
		}
		if (is_object($object)) {
			if (!empty($object->product) && is_object($object->product)) $productRef = (string) $object->product->ref;
			if (!$productRef && !empty($object->product_ref)) $productRef = (string) $object->product_ref;
		}
		global $user;
		$replace = array(
			'{YYYY}' => $parts['Y'], '{YY}' => $parts['y'],
			'{MM}' => $parts['m'], '{DD}' => $parts['d'],
			'{ENTITY}' => (string) $this->entity, '{CUSTOMER_CODE}' => $customerCode, '{SUPPLIER_CODE}' => $supplierCode,
			'{PRODUCT_REF}' => $productRef, '{DOCUMENT_TYPE}' => (string) $series->document_type,
			'{USER}' => (!empty($user->login) ? (string) $user->login : ''),
			$counter[0] => str_pad((string) $number, $width, '0', STR_PAD_LEFT),
		);
		$value = strtr($pattern, $replace);
		$value = strtr($value, array_change_key_case($replace, CASE_LOWER));
		return $value;
	}

	private function documentTimestamp($object, $documentType = '')
	{
		if (is_object($object)) {
			$fields = !empty($this->documentMap[$documentType]['dates']) ? $this->documentMap[$documentType]['dates'] : array();
			$fields = array_merge($fields, array('date_commande', 'date', 'date_creation', 'datec'));
			foreach (array_unique($fields) as $field) {
				if (!empty($object->$field)) return is_numeric($object->$field) ? (int) $object->$field : strtotime((string) $object->$field);
			}
		}
		return dol_now();
	}

	private function referenceExists($documentType, $reference)
	{
		if (empty($this->documentMap[$documentType])) return false;
		$map = $this->documentMap[$documentType];
		$sql = 'SELECT rowid FROM '.$this->table($map['table'])." WHERE ".$map['field']."='".$this->db->escape($reference)."' AND entity=".$this->entity.' LIMIT 1';
		$res = $this->db->query($sql);
		return $res && $this->db->num_rows($res) > 0;
	}

	public function preview($series, $object = null, $thirdparty = null)
	{
		$this->validatePattern($series->pattern);
		$timestamp = $this->documentTimestamp($object, $series->document_type);
		$scope = $this->scopeKey($series, $timestamp);
		$sql = 'SELECT current_value FROM '.$this->table('ns_counter').' WHERE entity='.$this->entity.' AND fk_series='.(int) $series->rowid
			." AND scope_key='".$this->db->escape($scope)."'";
		$res = $this->db->query($sql);
		$row = $res ? $this->db->fetch_object($res) : null;
		$number = $row ? ((int) $row->current_value + 1) : max(1, (int) $series->start_value);
		do {
			$reference = $this->render($series, $number, $timestamp, $object, $thirdparty);
			$number++;
		} while ($this->referenceExists($series->document_type, $reference));
		return $reference;
	}

	public function next($documentType, $object = null, $thirdparty = null)
	{
		$series = $this->resolveSeries($documentType, $object);
		if (!$series) throw new Exception('Tidak ada Naming Series aktif untuk '.str_replace('_', ' ', $documentType).'.');
		$this->validatePattern($series->pattern);
		$timestamp = $this->documentTimestamp($object, $documentType);
		$scope = $this->scopeKey($series, $timestamp);
		$initial = max(0, (int) $series->start_value - 1);
		$sql = 'INSERT IGNORE INTO '.$this->table('ns_counter').' (entity,fk_series,scope_key,current_value,datec) VALUES ('
			.$this->entity.','.(int) $series->rowid.",'".$this->db->escape($scope)."',".$initial.",'".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) throw new Exception($this->db->lasterror());
		$sql = 'SELECT rowid,current_value FROM '.$this->table('ns_counter').' WHERE entity='.$this->entity.' AND fk_series='.(int) $series->rowid
			." AND scope_key='".$this->db->escape($scope)."' FOR UPDATE";
		$res = $this->db->query($sql);
		$counter = $res ? $this->db->fetch_object($res) : null;
		if (!$counter) throw new Exception('Counter Naming Series tidak dapat dikunci.');
		$number = (int) $counter->current_value + 1;
		do {
			$reference = $this->render($series, $number, $timestamp, $object, $thirdparty);
			$number++;
		} while ($this->referenceExists($documentType, $reference));
		$used = $number - 1;
		if (!$this->db->query('UPDATE '.$this->table('ns_counter').' SET current_value='.$used.' WHERE rowid='.(int) $counter->rowid)) {
			throw new Exception($this->db->lasterror());
		}
		$this->audit((int) $series->rowid, 'ALLOCATE', $scope, (string) $counter->current_value, (string) $used, $documentType, (is_object($object) && !empty($object->id)) ? (int) $object->id : 0, $reference);
		return $reference;
	}

	public function last($documentType, $object = null, $thirdparty = null)
	{
		$series = $this->resolveSeries($documentType, $object);
		if (!$series) return '';
		$timestamp = $this->documentTimestamp($object, $documentType);
		$scope = $this->scopeKey($series, $timestamp);

		// Dolibarr uses mode=last to decide whether a definitive invoice may be
		// removed.  The counter is deliberately monotonic, so it can point to a
		// reference whose document was rolled back or has already been deleted.
		// Return the latest allocation that still has a matching document instead.
		// This also keeps working when an administrator changes the series pattern:
		// the audit row retains the reference that was actually assigned.
		if (!empty($this->documentMap[$documentType])) {
			$map = $this->documentMap[$documentType];
			$sql = 'SELECT a.document_ref FROM '.$this->table('ns_audit').' a'
				.' INNER JOIN '.$this->table($map['table']).' d ON d.rowid=a.document_id AND d.entity=a.entity'
				." AND d.".$map['field']."=a.document_ref"
				.' WHERE a.entity='.$this->entity.' AND a.fk_series='.(int) $series->rowid
				." AND a.event_type='ALLOCATE' AND a.document_type='".$this->db->escape($documentType)."'"
				." AND a.scope_key='".$this->db->escape($scope)."'"
				.' ORDER BY a.rowid DESC LIMIT 1';
			$res = $this->db->query($sql);
			$row = $res ? $this->db->fetch_object($res) : null;
			if ($row && $row->document_ref !== '') return $row->document_ref;

			// Documents created before audit tracking (or by an import) have no
			// allocation row.  For invoice models, fall back to the latest real
			// document in the same reset period.
			$dateField = in_array($documentType, array('customer_invoice', 'supplier_invoice', 'takepos_invoice'), true) ? 'datef' : '';
			if ($dateField !== '') {
				$parts = $this->calendarParts($timestamp);
				$period = strtolower((string) $series->reset_period);
				$dateFilter = '';
				if ($period === 'daily') {
					$dateFilter = " AND d.".$dateField."='".$parts['Y'].'-'.$parts['m'].'-'.$parts['d']."'";
				} elseif ($period === 'monthly') {
					$start = $parts['Y'].'-'.$parts['m'].'-01';
					$end = date('Y-m-d', strtotime($start.' +1 month'));
					$dateFilter = " AND d.".$dateField.">='".$start."' AND d.".$dateField."<'".$end."'";
				} elseif ($period === 'yearly') {
					$dateFilter = " AND d.".$dateField.">='".$parts['Y']."-01-01' AND d.".$dateField."<'".((int) $parts['Y'] + 1)."-01-01'";
				}
				$sql = 'SELECT d.'.$map['field'].' document_ref FROM '.$this->table($map['table']).' d'
					.' WHERE d.entity='.$this->entity." AND d.".$map['field']." NOT LIKE '(PROV%'"
					.$dateFilter.' ORDER BY d.rowid DESC LIMIT 1';
				$res = $this->db->query($sql);
				$row = $res ? $this->db->fetch_object($res) : null;
				if ($row && $row->document_ref !== '') return $row->document_ref;
			}
		}

		$sql = 'SELECT current_value FROM '.$this->table('ns_counter').' WHERE entity='.$this->entity.' AND fk_series='.(int) $series->rowid
			." AND scope_key='".$this->db->escape($scope)."'";
		$res = $this->db->query($sql);
		$row = $res ? $this->db->fetch_object($res) : null;
		return $row && (int) $row->current_value > 0 ? $this->render($series, (int) $row->current_value, $timestamp, $object, $thirdparty) : '';
	}

	public function saveSeries($data, $userId)
	{
		$this->validatePattern($data['pattern']);
		$allowedPeriods = array('never', 'yearly', 'monthly', 'daily');
		if (!in_array($data['reset_period'], $allowedPeriods, true)) throw new Exception('Periode reset tidak valid.');
		if (!isset(self::documentTypes()[$data['document_type']])) throw new Exception('Document type tidak didukung.');
		$id = (int) $data['rowid'];
		if (!empty($data['is_default'])) {
			$this->db->query('UPDATE '.$this->table('ns_series').' SET is_default=0 WHERE entity='.$this->entity
				." AND document_type='".$this->db->escape($data['document_type'])."'");
		}
		$fields = "code='".$this->db->escape($data['code'])."',label='".$this->db->escape($data['label'])."',document_type='"
			.$this->db->escape($data['document_type'])."',pattern='".$this->db->escape($data['pattern'])."',reset_period='"
			.$this->db->escape($data['reset_period'])."',start_value=".max(1, (int) $data['start_value']).',is_default='.(empty($data['is_default']) ? 0 : 1)
			.',active='.(empty($data['active']) ? 0 : 1).',fk_user_modif='.(int) $userId;
		if ($id > 0) {
			$old = $this->getSeries($id);
			if (!$old) throw new Exception('Naming Series tidak ditemukan.');
			$sql = 'UPDATE '.$this->table('ns_series').' SET '.$fields.' WHERE rowid='.$id.' AND entity='.$this->entity;
		} else {
			$sql = 'INSERT INTO '.$this->table('ns_series').' SET entity='.$this->entity.','.$fields.",datec='".$this->db->idate(dol_now())."',fk_user_creat=".(int) $userId;
		}
		if (!$this->db->query($sql)) throw new Exception($this->db->lasterror());
		$id = $id ?: (int) $this->db->last_insert_id($this->table('ns_series'));
		$this->audit($id, 'SAVE_SERIES', null, isset($old) ? json_encode($old) : null, json_encode($data));
		return $id;
	}

	public function setCounter($seriesId, $scope, $value, $userId)
	{
		$series = $this->getSeries($seriesId);
		if (!$series) throw new Exception('Naming Series tidak ditemukan.');
		$value = max(0, (int) $value);
		$scope = trim((string) $scope);
		if (!preg_match('/^(global|\d{4}|\d{4}-\d{2}|\d{4}-\d{2}-\d{2})$/', $scope)) throw new Exception('Scope counter tidak valid.');
		$sql = 'SELECT current_value FROM '.$this->table('ns_counter').' WHERE entity='.$this->entity.' AND fk_series='.(int) $seriesId
			." AND scope_key='".$this->db->escape($scope)."'";
		$res = $this->db->query($sql);
		$old = $res && ($row = $this->db->fetch_object($res)) ? (int) $row->current_value : null;
		$sql = 'INSERT INTO '.$this->table('ns_counter').' (entity,fk_series,scope_key,current_value,datec) VALUES ('
			.$this->entity.','.(int) $seriesId.",'".$this->db->escape($scope)."',".$value.",'".$this->db->idate(dol_now())."')"
			.' ON DUPLICATE KEY UPDATE current_value='.$value;
		if (!$this->db->query($sql)) throw new Exception($this->db->lasterror());
		$this->audit((int) $seriesId, 'SET_COUNTER', $scope, $old === null ? null : (string) $old, (string) $value, null, 0, null, $userId);
	}

	public function counters($seriesId = 0)
	{
		$sql = 'SELECT c.*,s.code,s.label FROM '.$this->table('ns_counter').' c JOIN '.$this->table('ns_series').' s ON s.rowid=c.fk_series'
			.' WHERE c.entity='.$this->entity;
		if ($seriesId > 0) $sql .= ' AND c.fk_series='.(int) $seriesId;
		$sql .= ' ORDER BY s.label,c.scope_key DESC';
		$res = $this->db->query($sql);
		$out = array();
		if ($res) while ($row = $this->db->fetch_object($res)) $out[] = $row;
		return $out;
	}

	public function currentValue($seriesId, $scope)
	{
		$sql = 'SELECT current_value FROM '.$this->table('ns_counter').' WHERE entity='.$this->entity.' AND fk_series='.(int) $seriesId
			." AND scope_key='".$this->db->escape($scope)."'";
		$res = $this->db->query($sql);
		$row = $res ? $this->db->fetch_object($res) : null;
		return $row ? (int) $row->current_value : 0;
	}

	public function audit($seriesId, $event, $scope = null, $old = null, $new = null, $documentType = null, $documentId = 0, $documentRef = null, $userId = null)
	{
		global $user;
		$uid = $userId === null ? (!empty($user->id) ? (int) $user->id : 0) : (int) $userId;
		$sql = 'INSERT INTO '.$this->table('ns_audit').' (entity,fk_series,event_type,scope_key,old_value,new_value,document_type,document_id,document_ref,fk_user,datec) VALUES ('
			.$this->entity.','.($seriesId ? (int) $seriesId : 'NULL').",'".$this->db->escape($event)."',"
			.($scope === null ? 'NULL' : "'".$this->db->escape($scope)."'").','
			.($old === null ? 'NULL' : "'".$this->db->escape($old)."'").','
			.($new === null ? 'NULL' : "'".$this->db->escape($new)."'").','
			.($documentType === null ? 'NULL' : "'".$this->db->escape($documentType)."'").','
			.($documentId ? (int) $documentId : 'NULL').','
			.($documentRef === null ? 'NULL' : "'".$this->db->escape($documentRef)."'").','
			.($uid ? $uid : 'NULL').",'".$this->db->idate(dol_now())."')";
		$this->db->query($sql);
	}
}
