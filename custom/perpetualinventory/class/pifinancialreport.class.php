<?php

/** Read-only financial statements built from Dolibarr's general ledger. */
class PiFinancialReport
{
	private $pi;
	private $accounts = array();
	private $byNumber = array();
	private $children = array();
	private $roots = array();

	public function __construct(PerpetualInventory $pi)
	{
		$this->pi = $pi;
		$this->loadAccounts();
	}

	private function loadAccounts()
	{
		$sql = 'SELECT rowid,account_number,account_parent,label,pcg_type,active FROM '.$this->pi->table('accounting_account').
			' WHERE entity='.$this->pi->entity.' AND fk_pcg_version='.$this->pi->str($this->pi->chartVersion()).' ORDER BY account_number';
		foreach ($this->pi->rows($sql) as $account) {
			$account->rowid = (int) $account->rowid;
			$account->account_parent = (int) $account->account_parent;
			$account->section = '';
			$this->accounts[$account->rowid] = $account;
			$this->byNumber[$account->account_number] = $account->rowid;
		}
		foreach ($this->accounts as $id => $account) {
			if ($account->account_parent && isset($this->accounts[$account->account_parent])) {
				$this->children[$account->account_parent][] = $id;
			} else {
				$this->roots[] = $id;
			}
		}
		foreach ($this->roots as $rootId) {
			$section = $this->classifyRoot($this->accounts[$rootId]);
			$this->assignSection($rootId, $section);
		}
	}

	private function classifyRoot($account)
	{
		$type = strtolower(trim((string) $account->pcg_type));
		$label = strtolower(trim((string) $account->label));
		$sets = array(
			'asset' => array('asset', 'assets', 'actif', 'actiu', 'receivable', 'fixed asset', 'current asset', 'cash', 'bank', 'stock'),
			'liability' => array('liability', 'liabilities', 'passif', 'passiu', 'payable'),
			'equity' => array('equity', 'capital', 'capitaux'),
			'income' => array('income', 'income account', 'revenue', 'product', 'produit'),
			'expense' => array('expense', 'expense account', 'cost of goods sold', 'depreciation', 'exp. in valuation', 'stock adjustment', 'charge'),
		);
		foreach ($sets as $section => $types) {
			if (in_array($type, $types, true)) {
				return $section;
			}
		}
		foreach (array('asset' => array('aset', 'asset'), 'liability' => array('liabil', 'hutang'), 'equity' => array('ekuitas', 'equity', 'modal'), 'income' => array('pendapatan', 'income', 'revenue'), 'expense' => array('beban', 'hpp', 'expense', 'cost')) as $section => $needles) {
			foreach ($needles as $needle) {
				if (strpos($label, $needle) !== false) {
					return $section;
				}
			}
		}
		$first = substr(ltrim((string) $account->account_number), 0, 1);
		$fallback = array('1' => 'asset', '2' => 'liability', '3' => 'equity', '4' => 'income');
		return isset($fallback[$first]) ? $fallback[$first] : ($first !== '' ? 'expense' : '');
	}

	private function assignSection($id, $section)
	{
		$this->accounts[$id]->section = $section;
		foreach ($this->children[$id] ?? array() as $childId) {
			$this->assignSection($childId, $section);
		}
	}

	public function periods($start, $end)
	{
		$cursor = new DateTimeImmutable(substr($start, 0, 7).'-01');
		$last = new DateTimeImmutable(substr($end, 0, 7).'-01');
		$periods = array();
		while ($cursor <= $last) {
			$key = $cursor->format('Y-m');
			$periods[$key] = array('label' => $cursor->format('M Y'), 'end' => min($end, $cursor->modify('last day of this month')->format('Y-m-d')));
			if (count($periods) > 24) {
				throw new DomainException('Rentang laporan bulanan maksimal 24 bulan.');
			}
			$cursor = $cursor->modify('+1 month');
		}
		return $periods;
	}

	private function emptyValues($periods)
	{
		return array_fill_keys(array_keys($periods), 0.0);
	}

	private function addAncestors(&$values, $accountId, $period, $amount)
	{
		$visited = array();
		while ($accountId && isset($this->accounts[$accountId]) && !isset($visited[$accountId])) {
			$visited[$accountId] = true;
			if (!isset($values[$accountId])) {
				$values[$accountId] = array();
			}
			$values[$accountId][$period] = ($values[$accountId][$period] ?? 0) + $amount;
			$accountId = $this->accounts[$accountId]->account_parent;
		}
	}

	private function signedAmount($section, $debit, $credit)
	{
		return in_array($section, array('income', 'liability', 'equity'), true) ? $credit - $debit : $debit - $credit;
	}

	public function profitLoss($start, $end)
	{
		$periods = $this->periods($start, $end);
		$values = array();
		$sql = 'SELECT numero_compte,DATE_FORMAT(doc_date,\'%Y-%m\') period,SUM(debit) debit,SUM(credit) credit FROM '.$this->pi->table('accounting_bookkeeping').
			' WHERE entity='.$this->pi->entity.' AND doc_date>='.$this->pi->str($start).' AND doc_date<='.$this->pi->str($end).' GROUP BY numero_compte,period';
		foreach ($this->pi->rows($sql) as $line) {
			$id = $this->byNumber[$line->numero_compte] ?? 0;
			if (!$id || !in_array($this->accounts[$id]->section, array('income', 'expense'), true) || !isset($periods[$line->period])) {
				continue;
			}
			$this->addAncestors($values, $id, $line->period, $this->signedAmount($this->accounts[$id]->section, (float) $line->debit, (float) $line->credit));
		}
		return $this->result(array('income', 'expense'), $periods, $values);
	}

	public function balanceSheet($start, $end)
	{
		$periods = $this->periods($start, $end);
		$movements = array();
		$sql = 'SELECT numero_compte,CASE WHEN doc_date<'.$this->pi->str($start)." THEN 'opening' ELSE DATE_FORMAT(doc_date,'%Y-%m') END period,SUM(debit) debit,SUM(credit) credit FROM ".$this->pi->table('accounting_bookkeeping').
			' WHERE entity='.$this->pi->entity.' AND doc_date<='.$this->pi->str($end).' GROUP BY numero_compte,period';
		foreach ($this->pi->rows($sql) as $line) {
			$id = $this->byNumber[$line->numero_compte] ?? 0;
			if (!$id || !in_array($this->accounts[$id]->section, array('asset', 'liability', 'equity'), true)) {
				continue;
			}
			$amount = $this->signedAmount($this->accounts[$id]->section, (float) $line->debit, (float) $line->credit);
			if ($line->period === 'opening') {
				foreach ($periods as $period => $unused) {
					$this->addAncestors($movements, $id, $period, $amount);
				}
			} elseif (isset($periods[$line->period])) {
				$apply = false;
				foreach ($periods as $period => $unused) {
					if ($period === $line->period) {
						$apply = true;
					}
					if ($apply) {
						$this->addAncestors($movements, $id, $period, $amount);
					}
				}
			}
		}
		$result = $this->result(array('asset', 'liability', 'equity'), $periods, $movements);
		$result['profit_loss'] = $this->fiscalYearProfit($periods);
		$result['difference'] = array();
		foreach ($periods as $period => $unused) {
			$result['difference'][$period] = ($result['totals']['asset'][$period] ?? 0) - ($result['totals']['liability'][$period] ?? 0) - ($result['totals']['equity'][$period] ?? 0) - ($result['profit_loss'][$period] ?? 0);
		}
		return $result;
	}

	/** Selectable ledger accounts from the active chart of accounts. */
	public function generalLedgerAccounts()
	{
		$options = array();
		foreach ($this->accounts as $account) {
			if ($account->active && empty($this->children[$account->rowid])) {
				$options[(string) $account->account_number] = $account->account_number.' — '.$account->label;
			}
		}
		ksort($options, SORT_NATURAL);
		return $options;
	}

	/** Values used by General Ledger filters. */
	public function generalLedgerFilters()
	{
		$filters = array('journals' => array(), 'voucher_types' => array(), 'parties' => array());
		$base = ' FROM '.$this->pi->table('accounting_bookkeeping').' WHERE entity='.$this->pi->entity;
		foreach ($this->pi->rows('SELECT DISTINCT code_journal,journal_label'.$base.' ORDER BY code_journal') as $row) {
			if ((string) $row->code_journal !== '') {
				$filters['journals'][$row->code_journal] = $row->code_journal.($row->journal_label ? ' — '.$row->journal_label : '');
			}
		}
		foreach ($this->pi->rows("SELECT DISTINCT COALESCE(doc_type,'') doc_type".$base." ORDER BY doc_type") as $row) {
			$key = $row->doc_type === '' ? '__manual__' : $row->doc_type;
			$filters['voucher_types'][$key] = $row->doc_type === '' ? 'Manual Journal' : $this->voucherTypeLabel($row->doc_type);
		}
		$partyKey = "COALESCE(NULLIF(TRIM(subledger_account),''),NULLIF(TRIM(thirdparty_code),''))";
		$partyLabel = "COALESCE(NULLIF(TRIM(subledger_label),''),NULLIF(TRIM(thirdparty_code),''),NULLIF(TRIM(subledger_account),''))";
		foreach ($this->pi->rows('SELECT '.$partyKey.' party_key,MAX('.$partyLabel.') party_label'.$base.' AND '.$partyKey.' IS NOT NULL GROUP BY '.$partyKey.' ORDER BY party_label') as $row) {
			$filters['parties'][$row->party_key] = $row->party_key.' — '.$row->party_label;
		}
		return $filters;
	}

	/** Detailed accounting trail with opening rows and debit-minus-credit running balance. */
	public function generalLedger($start, $end, $filters = array())
	{
		$account = trim((string) ($filters['account'] ?? ''));
		$journal = trim((string) ($filters['journal'] ?? ''));
		$voucherType = trim((string) ($filters['voucher_type'] ?? ''));
		$voucher = trim((string) ($filters['voucher'] ?? ''));
		$party = trim((string) ($filters['party'] ?? ''));
		$groupBy = (string) ($filters['group_by'] ?? 'voucher');
		$showOpening = !empty($filters['show_opening']);
		if (!in_array($groupBy, array('none', 'voucher', 'account'), true)) {
			throw new DomainException('Pengelompokan General Ledger tidak valid.');
		}
		$conditions = array('j.entity='.$this->pi->entity);
		if ($account !== '') {
			$conditions[] = 'j.numero_compte='.$this->pi->str($account);
		}
		if ($journal !== '') {
			$conditions[] = 'j.code_journal='.$this->pi->str($journal);
		}
		if ($voucherType !== '') {
			$conditions[] = $voucherType === '__manual__' ? "COALESCE(j.doc_type,'')=''" : 'j.doc_type='.$this->pi->str($voucherType);
		}
		if ($voucher !== '') {
			$conditions[] = 'j.doc_ref LIKE '.$this->pi->str('%'.$voucher.'%');
		}
		if ($party !== '') {
			$conditions[] = '(j.subledger_account='.$this->pi->str($party).' OR j.thirdparty_code='.$this->pi->str($party).')';
		}
		$where = implode(' AND ', $conditions);
		$table = $this->pi->table('accounting_bookkeeping');
		$against = '(SELECT GROUP_CONCAT(DISTINCT CONCAT(x.numero_compte,\' — \',x.label_compte) ORDER BY x.numero_compte SEPARATOR \', \') FROM '.$table.' x WHERE x.entity=j.entity AND x.piece_num=j.piece_num AND x.numero_compte<>j.numero_compte)';
		if ($groupBy === 'voucher') {
			$sql = 'SELECT MIN(j.rowid) rowid,j.piece_num,j.doc_date,COALESCE(j.doc_type,\'\') doc_type,j.doc_ref,MAX(j.fk_doc) fk_doc,'.
				'j.numero_compte,j.label_compte,SUM(j.debit) debit,SUM(j.credit) credit,j.code_journal,'.
				"COALESCE(NULLIF(TRIM(j.subledger_account),''),NULLIF(TRIM(j.thirdparty_code),''),'') party_code,".
				"COALESCE(NULLIF(TRIM(j.subledger_label),''),NULLIF(TRIM(j.thirdparty_code),''),NULLIF(TRIM(j.subledger_account),''),'') party_name,".
				'GROUP_CONCAT(DISTINCT NULLIF(j.label_operation,\'\') ORDER BY j.rowid SEPARATOR \'; \') remarks,'.$against.' against_account '.
				'FROM '.$table.' j WHERE '.$where.' AND j.doc_date>='.$this->pi->str($start).' AND j.doc_date<='.$this->pi->str($end).
				' GROUP BY j.piece_num,j.doc_date,j.doc_type,j.doc_ref,j.numero_compte,j.label_compte,j.code_journal,j.subledger_account,j.thirdparty_code,j.subledger_label'.
				' ORDER BY j.doc_date,j.piece_num,j.numero_compte,MIN(j.rowid)';
		} else {
			$sql = 'SELECT j.rowid,j.piece_num,j.doc_date,COALESCE(j.doc_type,\'\') doc_type,j.doc_ref,j.fk_doc,j.numero_compte,j.label_compte,j.debit,j.credit,j.code_journal,'.
				"COALESCE(NULLIF(TRIM(j.subledger_account),''),NULLIF(TRIM(j.thirdparty_code),''),'') party_code,".
				"COALESCE(NULLIF(TRIM(j.subledger_label),''),NULLIF(TRIM(j.thirdparty_code),''),NULLIF(TRIM(j.subledger_account),''),'') party_name,".
				'COALESCE(j.label_operation,\'\') remarks,'.$against.' against_account FROM '.$table.' j WHERE '.$where.
				' AND j.doc_date>='.$this->pi->str($start).' AND j.doc_date<='.$this->pi->str($end).
				' ORDER BY '.($groupBy === 'account' ? 'j.numero_compte,j.doc_date,j.piece_num,j.rowid' : 'j.doc_date,j.piece_num,j.rowid');
		}
		$movements = $this->pi->rows($sql);
		$openingSql = 'SELECT j.numero_compte,MAX(j.label_compte) label_compte,SUM(j.debit) debit,SUM(j.credit) credit FROM '.$table.' j WHERE '.$where.' AND j.doc_date<'.$this->pi->str($start).' GROUP BY j.numero_compte ORDER BY j.numero_compte';
		$openings = array();
		foreach ($this->pi->rows($openingSql) as $row) {
			$row->debit = (float) $row->debit;
			$row->credit = (float) $row->credit;
			$openings[$row->numero_compte] = $row;
		}

		$rows = array();
		$openingTotal = 0.0;
		foreach ($openings as $opening) {
			$openingTotal += $opening->debit - $opening->credit;
		}
		$running = $openingTotal;
		$currentAccount = null;
		$accountsWithMovement = array();
		foreach ($movements as $movement) {
			$accountsWithMovement[$movement->numero_compte] = true;
		}
		if ($groupBy === 'account') {
			$openingAccounts = array();
			foreach ($openings as $number => $opening) {
				if (abs($opening->debit - $opening->credit) >= 0.0000001) {
					$openingAccounts[] = $number;
				}
			}
			$accountNumbers = array_unique(array_merge($openingAccounts, array_keys($accountsWithMovement)));
			sort($accountNumbers, SORT_NATURAL);
			$byAccount = array();
			foreach ($movements as $movement) {
				$byAccount[$movement->numero_compte][] = $movement;
			}
			foreach ($accountNumbers as $number) {
				$opening = $openings[$number] ?? null;
				$running = $opening ? $opening->debit - $opening->credit : 0.0;
				if ($showOpening) {
					$rows[] = $this->openingRow($number, $opening ? $opening->label_compte : ($byAccount[$number][0]->label_compte ?? ''), $running);
				}
				foreach ($byAccount[$number] ?? array() as $movement) {
					$movement->debit = (float) $movement->debit;
					$movement->credit = (float) $movement->credit;
					$running += $movement->debit - $movement->credit;
					$movement->balance = $running;
					$movement->is_opening = false;
					$rows[] = $movement;
				}
			}
		} else {
			if ($showOpening) {
				$rows[] = $this->openingRow('', 'Opening', $openingTotal);
			}
			foreach ($movements as $movement) {
				$movement->debit = (float) $movement->debit;
				$movement->credit = (float) $movement->credit;
				$running += $movement->debit - $movement->credit;
				$movement->balance = $running;
				$movement->is_opening = false;
				$rows[] = $movement;
			}
		}
		$debit = $credit = 0.0;
		foreach ($movements as $movement) {
			$debit += (float) $movement->debit;
			$credit += (float) $movement->credit;
		}
		return array('rows' => $rows, 'opening' => $openingTotal, 'debit' => $debit, 'credit' => $credit, 'closing' => $openingTotal + $debit - $credit);
	}

	private function openingRow($account, $label, $balance)
	{
		return (object) array('rowid' => 0, 'piece_num' => 0, 'doc_date' => '', 'doc_type' => '', 'doc_ref' => '', 'fk_doc' => 0,
			'numero_compte' => $account, 'label_compte' => $label, 'debit' => 0.0, 'credit' => 0.0, 'code_journal' => '',
			'party_code' => '', 'party_name' => '', 'remarks' => '', 'against_account' => '', 'balance' => (float) $balance, 'is_opening' => true);
	}

	public function voucherTypeLabel($type)
	{
		$labels = array('customer_invoice' => 'Customer Invoice', 'supplier_invoice' => 'Supplier Invoice', 'bank' => 'Payment / Bank', 'reception' => 'Reception', 'shipment' => 'Shipment', 'expense_report' => 'Expense Report', 'asset' => 'Asset', 'loan' => 'Loan', 'perpetualinventory' => 'Perpetual Inventory');
		return $labels[$type] ?? ucwords(str_replace('_', ' ', (string) $type));
	}

	/** Accounts suitable for a customer receivable or supplier payable subledger report. */
	public function thirdPartyAccounts($kind)
	{
		if (!in_array($kind, array('receivable', 'payable'), true)) {
			throw new DomainException('Jenis laporan hutang/piutang tidak valid.');
		}
		$section = $kind === 'receivable' ? 'asset' : 'liability';
		$default = getDolGlobalString($kind === 'receivable' ? 'ACCOUNTING_ACCOUNT_CUSTOMER' : 'ACCOUNTING_ACCOUNT_SUPPLIER');
		$used = array();
		$sql = 'SELECT DISTINCT numero_compte FROM '.$this->pi->table('accounting_bookkeeping').
			' WHERE entity='.$this->pi->entity." AND (COALESCE(subledger_account,'')<>'' OR COALESCE(thirdparty_code,'')<>'')";
		foreach ($this->pi->rows($sql) as $line) {
			$used[(string) $line->numero_compte] = true;
		}
		$options = array();
		foreach ($this->accounts as $account) {
			$number = (string) $account->account_number;
			$type = strtolower(trim((string) $account->pcg_type));
			$label = strtolower(trim((string) $account->label));
			$typeMatch = $kind === 'receivable'
				? (strpos($type, 'receiv') !== false || strpos($label, 'piutang') !== false || strpos($label, 'receiv') !== false)
				: (strpos($type, 'payable') !== false || strpos($label, 'hutang') !== false || strpos($label, 'utang') !== false || strpos($label, 'payable') !== false);
			if ($number === $default || $typeMatch || ($account->section === $section && isset($used[$number]))) {
				$options[$number] = $number.' — '.$account->label;
			}
		}
		ksort($options, SORT_NATURAL);
		return $options;
	}

	/** Opening, increases, reductions and closing balance per auxiliary account. */
	public function receivablePayable($kind, $account, $start, $end)
	{
		if (!in_array($kind, array('receivable', 'payable'), true)) {
			throw new DomainException('Jenis laporan hutang/piutang tidak valid.');
		}
		$account = trim((string) $account);
		if ($account === '') {
			throw new DomainException('Pilih akun buku besar untuk laporan.');
		}
		$isReceivable = $kind === 'receivable';
		$opening = $isReceivable ? 'debit-credit' : 'credit-debit';
		$increase = $isReceivable ? 'debit' : 'credit';
		$reduction = $isReceivable ? 'credit' : 'debit';
		$partyCode = "COALESCE(NULLIF(TRIM(subledger_account),''),NULLIF(TRIM(thirdparty_code),''),NULLIF(TRIM(subledger_label),''),'Tanpa akun pembantu')";
		$partyName = "COALESCE(NULLIF(TRIM(subledger_label),''),NULLIF(TRIM(thirdparty_code),''),NULLIF(TRIM(subledger_account),''),'Tanpa akun pembantu')";
		$sql = 'SELECT '.$partyCode.' party_code,'.$partyName.' party_name,'.
			'SUM(CASE WHEN doc_date<'.$this->pi->str($start).' THEN '.$opening.' ELSE 0 END) opening_balance,'.
			'SUM(CASE WHEN doc_date>='.$this->pi->str($start).' AND doc_date<='.$this->pi->str($end).' THEN '.$increase.' ELSE 0 END) increases,'.
			'SUM(CASE WHEN doc_date>='.$this->pi->str($start).' AND doc_date<='.$this->pi->str($end).' THEN '.$reduction.' ELSE 0 END) reductions '.
			'FROM '.$this->pi->table('accounting_bookkeeping').' WHERE entity='.$this->pi->entity.
			' AND numero_compte='.$this->pi->str($account).' AND doc_date<='.$this->pi->str($end).
			' GROUP BY '.$partyCode.','.$partyName.' ORDER BY party_name,party_code';
		$rows = array();
		foreach ($this->pi->rows($sql) as $row) {
			$row->opening_balance = (float) $row->opening_balance;
			$row->increases = (float) $row->increases;
			$row->reductions = (float) $row->reductions;
			$row->closing_balance = $row->opening_balance + $row->increases - $row->reductions;
			if (abs($row->opening_balance) + abs($row->increases) + abs($row->reductions) < 0.0000001) {
				continue;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Gross profit per delivered product, valued with the actual perpetual-inventory shipment cost. */
	public function grossProfit($start, $end)
	{
		$endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
		$invoice = '(SELECT ee.fk_source shipment_id,fd.fk_product,GROUP_CONCAT(DISTINCT f.ref ORDER BY f.ref SEPARATOR ", ") invoice_refs,SUM(fd.qty) invoice_qty,SUM(fd.total_ht) invoice_amount'.
			' FROM '.$this->pi->table('element_element').' ee JOIN '.$this->pi->table('facture').' f ON f.rowid=ee.fk_target AND f.entity='.$this->pi->entity." AND f.fk_statut>0 AND f.type=0".
			' JOIN '.$this->pi->table('facturedet')." fd ON fd.fk_facture=f.rowid AND fd.fk_product IS NOT NULL WHERE ee.sourcetype='shipping' AND ee.targettype='facture' GROUP BY ee.fk_source,fd.fk_product) inv";
		$order = '(SELECT ed.fk_expedition shipment_id,ed.fk_product,ed.fk_entrepot,GROUP_CONCAT(DISTINCT c.ref ORDER BY c.ref SEPARATOR ", ") order_refs'.
			' FROM '.$this->pi->table('expeditiondet').' ed LEFT JOIN '.$this->pi->table('commandedet')." cd ON cd.rowid=ed.fk_elementdet AND ed.element_type='commande'".
			' LEFT JOIN '.$this->pi->table('commande').' c ON c.rowid=cd.fk_commande AND c.entity='.$this->pi->entity.' GROUP BY ed.fk_expedition,ed.fk_product,ed.fk_entrepot) ord';
		$shipped = '(SELECT origin_id shipment_id,fk_product,SUM(ABS(qty)) shipped_qty FROM '.$this->pi->table('pi_entry')." WHERE entity=".$this->pi->entity." AND origin_type='shipping' AND kind='delivery' GROUP BY origin_id,fk_product) shp";
		$sql = 'SELECT e.ref delivery_note,COALESCE(ord.order_refs,\'\') sales_order,inv.invoice_refs sales_invoice,p.ref item_code,p.label item_name,s.nom customer,'.
			'DATE(pe.date_post) posting_date,w.ref warehouse,SUM(ABS(pe.qty))*LEAST(1,inv.invoice_qty/shp.shipped_qty) qty,SUM(-pe.value_delta)*LEAST(1,inv.invoice_qty/shp.shipped_qty) buying_amount,'.
			'CASE WHEN inv.invoice_qty<>0 THEN inv.invoice_amount/inv.invoice_qty ELSE 0 END selling_rate '.
			'FROM '.$this->pi->table('pi_entry').' pe JOIN '.$this->pi->table('expedition')." e ON e.rowid=pe.origin_id AND e.entity=pe.entity AND pe.origin_type='shipping' AND pe.kind='delivery' ".
			'JOIN '.$this->pi->table('product').' p ON p.rowid=pe.fk_product JOIN '.$this->pi->table('societe').' s ON s.rowid=e.fk_soc JOIN '.$this->pi->table('entrepot').' w ON w.rowid=pe.fk_warehouse '.
			'JOIN '.$invoice.' ON inv.shipment_id=e.rowid AND inv.fk_product=pe.fk_product JOIN '.$shipped.' ON shp.shipment_id=e.rowid AND shp.fk_product=pe.fk_product LEFT JOIN '.$order.' ON ord.shipment_id=e.rowid AND ord.fk_product=pe.fk_product AND (ord.fk_entrepot=pe.fk_warehouse OR ord.fk_entrepot IS NULL) '.
			'WHERE pe.entity='.$this->pi->entity.' AND pe.date_post>='.$this->pi->str($start).' AND pe.date_post<'.$this->pi->str($endExclusive).
			' GROUP BY e.rowid,e.ref,ord.order_refs,inv.invoice_refs,inv.invoice_qty,inv.invoice_amount,shp.shipped_qty,p.rowid,p.ref,p.label,s.rowid,s.nom,DATE(pe.date_post),w.rowid,w.ref ORDER BY pe.date_post,e.ref,p.ref,w.ref';
		$rows = array();
		foreach ($this->pi->rows($sql) as $row) {
			$row->qty = (float) $row->qty;
			$row->selling_rate = (float) $row->selling_rate;
			$row->buying_amount = (float) $row->buying_amount;
			$row->valuation_rate = $row->qty ? $row->buying_amount / $row->qty : 0;
			$row->selling_amount = $row->selling_rate * $row->qty;
			$row->gross_profit = $row->selling_amount - $row->buying_amount;
			$row->gross_profit_percent = $row->selling_amount ? $row->gross_profit / $row->selling_amount * 100 : 0;
			$rows[] = $row;
		}
		return $rows;
	}

	private function fiscalStart($date)
	{
		$month = max(1, min(12, (int) getDolGlobalInt('SOCIETE_FISCAL_MONTH_START')));
		$month = $month ?: 1;
		$year = (int) substr($date, 0, 4);
		if ((int) substr($date, 5, 2) < $month) {
			$year--;
		}
		return sprintf('%04d-%02d-01', $year, $month);
	}

	/** Return provisional profit/loss from the fiscal-year start through each period end. */
	private function fiscalYearProfit($periods)
	{
		$first = reset($periods);
		$last = end($periods);
		$start = $this->fiscalStart($first['end']);
		$monthly = array();
		$sql = 'SELECT numero_compte,DATE_FORMAT(doc_date,\'%Y-%m\') period,SUM(debit) debit,SUM(credit) credit FROM '.$this->pi->table('accounting_bookkeeping').
			' WHERE entity='.$this->pi->entity.' AND doc_date>='.$this->pi->str($start).' AND doc_date<='.$this->pi->str($last['end']).' GROUP BY numero_compte,period';
		foreach ($this->pi->rows($sql) as $line) {
			$id = $this->byNumber[$line->numero_compte] ?? 0;
			if (!$id || !in_array($this->accounts[$id]->section, array('income', 'expense'), true)) {
				continue;
			}
			$amount = $this->signedAmount($this->accounts[$id]->section, (float) $line->debit, (float) $line->credit);
			$monthly[$line->period] = ($monthly[$line->period] ?? 0) + ($this->accounts[$id]->section === 'income' ? $amount : -$amount);
		}
		$result = array();
		foreach ($periods as $period => $meta) {
			$fiscalMonth = substr($this->fiscalStart($meta['end']), 0, 7);
			$result[$period] = 0;
			foreach ($monthly as $month => $amount) {
				if ($month >= $fiscalMonth && $month <= $period) {
					$result[$period] += $amount;
				}
			}
		}
		return $result;
	}

	private function result($sections, $periods, $values)
	{
		$result = array('periods' => $periods, 'sections' => array(), 'totals' => array(), 'net' => $this->emptyValues($periods));
		foreach ($sections as $section) {
			$result['sections'][$section] = array();
			$result['totals'][$section] = $this->emptyValues($periods);
			foreach ($this->roots as $rootId) {
				if ($this->accounts[$rootId]->section !== $section) {
					continue;
				}
				$rootValues = array_replace($this->emptyValues($periods), $values[$rootId] ?? array());
				if (array_sum(array_map('abs', $rootValues)) < 0.0000001) {
					continue;
				}
				$result['sections'][$section][] = $this->treeNode($rootId, $periods, $values);
				foreach ($rootValues as $period => $amount) {
					$result['totals'][$section][$period] += $amount;
				}
			}
		}
		foreach ($periods as $period => $unused) {
			$result['net'][$period] = ($result['totals']['income'][$period] ?? 0) - ($result['totals']['expense'][$period] ?? 0);
		}
		return $result;
	}

	private function treeNode($id, $periods, $values)
	{
		$node = array('account' => $this->accounts[$id], 'values' => array_replace($this->emptyValues($periods), $values[$id] ?? array()), 'children' => array());
		foreach ($this->children[$id] ?? array() as $childId) {
			$childValues = array_replace($this->emptyValues($periods), $values[$childId] ?? array());
			if (array_sum(array_map('abs', $childValues)) >= 0.0000001) {
				$node['children'][] = $this->treeNode($childId, $periods, $values);
			}
		}
		return $node;
	}
}
