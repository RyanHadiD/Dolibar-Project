<?php

/** Read-only stock ledger and stock balance reports built from the immutable PI ledger. */
class PiStockReport
{
	private $pi;

	public function __construct(PerpetualInventory $pi)
	{
		$this->pi = $pi;
	}

	private function scope($product, $warehouse, $alias = 'e')
	{
		$sql = $alias.'.entity='.$this->pi->entity;
		if ($product) $sql .= ' AND '.$alias.'.fk_product='.(int) $product;
		if ($warehouse) $sql .= ' AND '.$alias.'.fk_warehouse='.(int) $warehouse;
		return $sql;
	}

	private function endExclusive($end)
	{
		return (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
	}

	public function ledger($start, $end, $product = 0, $warehouse = 0, $kind = '', $limit = 0, $offset = 0)
	{
		$endExclusive = $this->endExclusive($end);
		$where = $this->scope($product, $warehouse).' AND e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive);
		if ($kind !== '') $where .= ' AND e.kind='.$this->pi->str($kind);
		$sql = 'SELECT e.*,p.ref product_ref,p.label product_label,w.ref warehouse_ref,'.
			"COALESCE(NULLIF(r.ref,''),NULLIF(s.ref,''),CONCAT('PI-',e.rowid)) voucher_ref ".
			'FROM '.$this->pi->table('pi_entry').' e '.
			'JOIN '.$this->pi->table('product').' p ON p.rowid=e.fk_product AND p.entity=e.entity '.
			'JOIN '.$this->pi->table('entrepot').' w ON w.rowid=e.fk_warehouse AND w.entity=e.entity '.
			'LEFT JOIN '.$this->pi->table('reception')." r ON r.rowid=e.origin_id AND r.entity=e.entity AND e.origin_type='reception' ".
			'LEFT JOIN '.$this->pi->table('expedition')." s ON s.rowid=e.origin_id AND s.entity=e.entity AND e.origin_type='shipping' ".
			'WHERE '.$where.' ORDER BY e.date_post,e.rowid';
		if ($limit > 0) $sql .= $this->pi->db->plimit((int) $limit, max(0, (int) $offset));
		return $this->pi->rows($sql);
	}

	public function ledgerSummary($start, $end, $product = 0, $warehouse = 0, $kind = '')
	{
		$endExclusive = $this->endExclusive($end);
		$scope = $this->scope($product, $warehouse);
		$kindSql = $kind === '' ? '' : ' AND e.kind='.$this->pi->str($kind);
		$period = 'e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive).$kindSql;
		return $this->pi->one('SELECT '.
			'SUM(CASE WHEN e.date_post<'.$this->pi->str($start).' THEN e.value_delta ELSE 0 END) opening_value,'.
			'SUM(CASE WHEN '.$period.' AND e.qty>0 THEN e.qty ELSE 0 END) in_qty,'.
			'SUM(CASE WHEN '.$period.' AND e.qty<0 THEN -e.qty ELSE 0 END) out_qty,'.
			'SUM(CASE WHEN '.$period.' AND e.value_delta>0 THEN e.value_delta ELSE 0 END) in_value,'.
			'SUM(CASE WHEN '.$period.' AND e.value_delta<0 THEN -e.value_delta ELSE 0 END) out_value,'.
			'SUM(CASE WHEN e.date_post<'.$this->pi->str($endExclusive).' THEN e.value_delta ELSE 0 END) closing_value '.
			'FROM '.$this->pi->table('pi_entry').' e WHERE '.$scope);
	}

	public function stockBalance($start, $end, $product = 0, $warehouse = 0, $includeZero = false)
	{
		$endExclusive = $this->endExclusive($end);
		$sql = 'SELECT p.ref product_ref,p.label product_label,w.ref warehouse_ref,e.fk_product,e.fk_warehouse,'.
			'SUM(CASE WHEN e.date_post<'.$this->pi->str($start).' THEN e.qty ELSE 0 END) opening_qty,'.
			'SUM(CASE WHEN e.date_post<'.$this->pi->str($start).' THEN e.value_delta ELSE 0 END) opening_value,'.
			'SUM(CASE WHEN e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive).' AND e.qty>0 THEN e.qty ELSE 0 END) in_qty,'.
			'SUM(CASE WHEN e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive).' AND e.qty<0 THEN -e.qty ELSE 0 END) out_qty,'.
			'SUM(CASE WHEN e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive).' AND e.value_delta>0 THEN e.value_delta ELSE 0 END) in_value,'.
			'SUM(CASE WHEN e.date_post>='.$this->pi->str($start).' AND e.date_post<'.$this->pi->str($endExclusive).' AND e.value_delta<0 THEN -e.value_delta ELSE 0 END) out_value,'.
			'SUM(e.qty) balance_qty,SUM(e.value_delta) balance_value,MAX(e.date_post) last_movement '.
			'FROM '.$this->pi->table('pi_entry').' e '.
			'JOIN '.$this->pi->table('product').' p ON p.rowid=e.fk_product AND p.entity=e.entity '.
			'JOIN '.$this->pi->table('entrepot').' w ON w.rowid=e.fk_warehouse AND w.entity=e.entity '.
			'WHERE '.$this->scope($product, $warehouse).' AND e.date_post<'.$this->pi->str($endExclusive).
			' GROUP BY e.fk_product,e.fk_warehouse,p.ref,p.label,w.ref';
		if (!$includeZero) $sql .= ' HAVING ABS(balance_qty)>0.00000001 OR ABS(balance_value)>0.00000001 OR ABS(in_qty)>0.00000001 OR ABS(out_qty)>0.00000001';
		$sql .= ' ORDER BY p.ref,w.ref';
		return $this->pi->rows($sql);
	}
}
