<?php

class CreditLimitService
{
	public $db;
	public $entity;

	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	public function table($name) { return $this->db->prefix().$name; }
	public function str($value) { return "'".$this->db->escape((string) $value)."'"; }
	public function q($sql) { $r = $this->db->query($sql); if (!$r) throw new RuntimeException($this->db->lasterror()); return $r; }
	public function one($sql) { $r = $this->q($sql); return $this->db->fetch_object($r) ?: null; }
	public function rows($sql) { $r = $this->q($sql); $out = array(); while ($o = $this->db->fetch_object($r)) $out[] = $o; return $out; }

	public function config()
	{
		$c = $this->one('SELECT * FROM '.$this->table('cl_config').' WHERE entity='.$this->entity);
		if (!$c) {
			$this->q('INSERT INTO '.$this->table('cl_config').' (entity) VALUES ('.$this->entity.')');
			$c = $this->one('SELECT * FROM '.$this->table('cl_config').' WHERE entity='.$this->entity);
		}
		return $c;
	}

	public function saveConfig($data, $user)
	{
		$mode = in_array($data['default_mode'] ?? '', array('off', 'warn', 'block'), true) ? $data['default_mode'] : 'block';
		$fields = array(
			'enabled='.(empty($data['enabled']) ? 0 : 1),
			'default_limit='.max(0, (float) ($data['default_limit'] ?? 0)),
			'default_mode='.$this->str($mode),
			'max_overdue_days='.max(0, (int) ($data['max_overdue_days'] ?? 0)),
			'overdue_amount_limit='.max(0, (float) ($data['overdue_amount_limit'] ?? 0)),
			'grace_days='.max(0, (int) ($data['grace_days'] ?? 0)),
			'check_order='.(empty($data['check_order']) ? 0 : 1),
			'check_shipment='.(empty($data['check_shipment']) ? 0 : 1),
			'check_invoice='.(empty($data['check_invoice']) ? 0 : 1),
			'include_orders='.(empty($data['include_orders']) ? 0 : 1),
			'override_valid_days='.max(1, min(90, (int) ($data['override_valid_days'] ?? 7))),
			'fk_user_modif='.(int) $user->id,
		);
		$this->q('UPDATE '.$this->table('cl_config').' SET '.implode(',', $fields).' WHERE entity='.$this->entity);
	}

	public function policy($socId)
	{
		$c = $this->config();
		$s = $this->one('SELECT outstanding_limit FROM '.$this->table('societe').' WHERE entity='.$this->entity.' AND rowid='.(int) $socId);
		$p = $this->one('SELECT * FROM '.$this->table('cl_customer_policy').' WHERE entity='.$this->entity.' AND fk_soc='.(int) $socId);
		$mode = $p && $p->control_mode !== 'inherit' ? $p->control_mode : $c->default_mode;
		return (object) array(
			'enabled' => (int) $c->enabled,
			'limit' => $s && (float) $s->outstanding_limit > 0 ? (float) $s->outstanding_limit : (float) $c->default_limit,
			'mode' => $mode,
			'max_days' => $p && $p->max_overdue_days !== null ? (int) $p->max_overdue_days : (int) $c->max_overdue_days,
			'overdue_limit' => $p && $p->overdue_amount_limit !== null ? (float) $p->overdue_amount_limit : (float) $c->overdue_amount_limit,
			'grace_days' => $p && $p->grace_days !== null ? (int) $p->grace_days : (int) $c->grace_days,
			'hold' => $p ? (int) $p->credit_hold : 0,
			'check_order' => (int) $c->check_order,
			'check_shipment' => (int) $c->check_shipment,
			'check_invoice' => (int) $c->check_invoice,
			'include_orders' => (int) $c->include_orders,
			'override_days' => (int) $c->override_valid_days,
		);
	}

	public function saveCustomerPolicy($socId, $data, $user)
	{
		$socId = (int) $socId;
		$mode = in_array($data['control_mode'] ?? '', array('inherit', 'off', 'warn', 'block'), true) ? $data['control_mode'] : 'inherit';
		$nullable = function ($key) use ($data) { return !isset($data[$key]) || $data[$key] === '' ? 'NULL' : max(0, (float) $data[$key]); };
		$this->q('UPDATE '.$this->table('societe').' SET outstanding_limit='.((isset($data['credit_limit']) && $data['credit_limit'] !== '') ? max(0, (float) $data['credit_limit']) : 'NULL').' WHERE entity='.$this->entity.' AND rowid='.$socId);
		$sql = 'INSERT INTO '.$this->table('cl_customer_policy').' (entity,fk_soc,control_mode,max_overdue_days,overdue_amount_limit,grace_days,credit_hold,note,fk_user_modif,datec) VALUES ('.$this->entity.','.$socId.','.$this->str($mode).','.$nullable('max_overdue_days').','.$nullable('overdue_amount_limit').','.$nullable('grace_days').','.(empty($data['credit_hold']) ? 0 : 1).','.$this->str(mb_substr(trim((string) ($data['note'] ?? '')), 0, 1000)).','.(int) $user->id.','.$this->str($this->db->idate(dol_now())).') ON DUPLICATE KEY UPDATE control_mode=VALUES(control_mode),max_overdue_days=VALUES(max_overdue_days),overdue_amount_limit=VALUES(overdue_amount_limit),grace_days=VALUES(grace_days),credit_hold=VALUES(credit_hold),note=VALUES(note),fk_user_modif=VALUES(fk_user_modif)';
		$this->q($sql);
	}

	private function outstandingInvoices($socId, $excludeInvoiceId = 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$amount = 0.0;
		$today = dol_get_first_hour(dol_now('tzuser'));
		$overdue = 0.0; $oldest = 0; $details = array();
		foreach ($this->rows('SELECT rowid,ref,date_lim_reglement FROM '.$this->table('facture').' WHERE entity='.$this->entity.' AND fk_soc='.(int) $socId.' AND fk_statut=1 AND paye=0'.($excludeInvoiceId ? ' AND rowid<>'.(int) $excludeInvoiceId : '').' ORDER BY date_lim_reglement,rowid') as $row) {
			$invoice = new Facture($this->db);
			if ($invoice->fetch((int) $row->rowid) <= 0) continue;
			$remain = (float) price2num($invoice->total_ttc - $invoice->getSommePaiement(0) - $invoice->getSumCreditNotesUsed(0) - $invoice->getSumDepositsUsed(0), 'MT');
			if ($remain > 0) $amount += $remain;
			$due = $row->date_lim_reglement ? strtotime(substr($row->date_lim_reglement, 0, 10)) : 0;
			$days = $due && $due < $today ? (int) floor(($today - $due) / 86400) : 0;
			if ($remain > 0 && $days > 0) { $overdue += $remain; $oldest = max($oldest, $days); }
			if ($remain > 0) $details[] = (object) array('id' => (int) $row->rowid, 'ref' => $row->ref, 'due_date' => $row->date_lim_reglement, 'remain' => $remain, 'overdue_days' => $days);
		}
		return array('amount' => $amount, 'overdue' => $overdue, 'oldest' => $oldest, 'invoices' => $details);
	}

	private function linkedInvoiceTotal($orderId, $excludeInvoiceId = 0)
	{
		$sql = 'SELECT COALESCE(SUM(x.total_ttc),0) amount FROM (SELECT DISTINCT f.rowid,f.total_ttc FROM '.$this->table('facture').' f JOIN '.$this->table('element_element').' ef ON ef.fk_target=f.rowid AND ef.targettype='.$this->str('facture').' LEFT JOIN '.$this->table('element_element').' es ON es.fk_target=ef.fk_source AND es.targettype='.$this->str('shipping').' WHERE f.entity='.$this->entity.' AND f.fk_statut IN (1,2)'.($excludeInvoiceId ? ' AND f.rowid<>'.(int) $excludeInvoiceId : '').' AND ((ef.sourcetype='.$this->str('commande').' AND ef.fk_source='.(int) $orderId.') OR (ef.sourcetype='.$this->str('shipping').' AND es.sourcetype='.$this->str('commande').' AND es.fk_source='.(int) $orderId.'))) x';
		$row = $this->one($sql);
		return (float) ($row->amount ?? 0);
	}

	private function orderExposure($socId, $excludeOrderId = 0, $excludeInvoiceId = 0)
	{
		$total = 0.0;
		foreach ($this->rows('SELECT rowid,total_ttc FROM '.$this->table('commande').' WHERE entity='.$this->entity.' AND fk_soc='.(int) $socId.' AND fk_statut>0'.($excludeOrderId ? ' AND rowid<>'.(int) $excludeOrderId : '')) as $order) {
			$total += max(0, (float) $order->total_ttc - $this->linkedInvoiceTotal($order->rowid, $excludeInvoiceId));
		}
		return $total;
	}

	private function linkedOrderReserveForInvoice($invoiceId, $invoiceAmount)
	{
		$sql = 'SELECT DISTINCT c.rowid,c.total_ttc FROM '.$this->table('commande').' c JOIN '.$this->table('element_element').' eo ON eo.fk_source=c.rowid AND eo.sourcetype='.$this->str('commande').' LEFT JOIN '.$this->table('element_element').' ei ON ((eo.targettype='.$this->str('facture').' AND ei.rowid=eo.rowid) OR (eo.targettype='.$this->str('shipping').' AND ei.sourcetype='.$this->str('shipping').' AND ei.fk_source=eo.fk_target)) WHERE c.entity='.$this->entity.' AND ((eo.targettype='.$this->str('facture').' AND eo.fk_target='.(int) $invoiceId.') OR (ei.targettype='.$this->str('facture').' AND ei.fk_target='.(int) $invoiceId.'))';
		$reserve = 0.0;
		foreach ($this->rows($sql) as $order) $reserve += max(0, (float) $order->total_ttc - $this->linkedInvoiceTotal($order->rowid, $invoiceId));
		return min(max(0, (float) $invoiceAmount), $reserve);
	}

	public function elementType($object)
	{
		if (($object->element ?? '') === 'commande') return 'customer_order';
		if (($object->element ?? '') === 'shipping') return 'shipment';
		return 'customer_invoice';
	}

	public function customerId($object) { return (int) ($object->socid ?? $object->fk_soc ?? 0); }
	public function documentAmount($object) { return max(0, (float) ($object->total_ttc ?? $object->multicurrency_total_ttc ?? 0)); }

	public function evaluate($object, $includeDocument = true)
	{
		$type = $this->elementType($object); $socId = $this->customerId($object); $policy = $this->policy($socId);
		$excludeInvoiceId = $includeDocument && $type === 'customer_invoice' ? (int) $object->id : 0;
		$excludeOrderId = $includeDocument && $type === 'customer_order' ? (int) $object->id : 0;
		$invoice = $this->outstandingInvoices($socId, $excludeInvoiceId);
		$orders = $policy->include_orders ? $this->orderExposure($socId, $excludeOrderId, $excludeInvoiceId) : 0.0;
		$exposure = $invoice['amount'] + $orders;
		$docAmount = $includeDocument ? $this->documentAmount($object) : 0.0;
		$delta = 0.0;
		if ($includeDocument && $type === 'customer_order') $delta = $policy->include_orders ? $docAmount : 0.0;
		if ($includeDocument && $type === 'customer_invoice') $delta = max(0, $docAmount - ($policy->include_orders ? $this->linkedOrderReserveForInvoice((int) $object->id, $docAmount) : 0));
		$projected = $exposure + $delta;
		$checked = $policy->enabled && $policy->mode !== 'off' && (($type === 'customer_order' && $policy->check_order) || ($type === 'shipment' && $policy->check_shipment) || ($type === 'customer_invoice' && $policy->check_invoice));
		$limitViolation = $policy->limit > 0 && $projected > $policy->limit + 0.000001;
		$ageViolation = $invoice['oldest'] > ($policy->max_days + $policy->grace_days) && $invoice['overdue'] > $policy->overdue_limit + 0.000001;
		$violations = array();
		if ($policy->hold) $violations[] = 'Customer sedang Credit Hold';
		if ($limitViolation) $violations[] = 'Projected exposure '.price($projected).' melebihi limit '.price($policy->limit);
		if ($ageViolation) $violations[] = 'Piutang overdue '.price($invoice['overdue']).' dengan umur tertua '.$invoice['oldest'].' hari';
		$blocked = $checked && $violations && $policy->mode === 'block';
		return array('type' => $type, 'socid' => $socId, 'policy' => $policy, 'invoice_outstanding' => $invoice['amount'], 'order_exposure' => $orders, 'exposure' => $exposure, 'document_amount' => $docAmount, 'delta' => $delta, 'projected' => $projected, 'available' => $policy->limit > 0 ? $policy->limit - $exposure : null, 'overdue' => $invoice['overdue'], 'oldest_days' => $invoice['oldest'], 'invoices' => $invoice['invoices'], 'violations' => $violations, 'blocked' => (bool) $blocked, 'warning' => (bool) ($checked && $violations && !$blocked));
	}

	public function approveOverride($object, $reason, $user)
	{
		if (mb_strlen(trim($reason)) < 5) throw new DomainException('Alasan override wajib diisi minimal 5 karakter.');
		$check = $this->evaluate($object, true);
		if (!$check['blocked']) throw new DomainException('Dokumen saat ini tidak memerlukan override kredit.');
		$this->q('UPDATE '.$this->table('cl_override').' SET status='.$this->str('revoked').' WHERE entity='.$this->entity.' AND element_type='.$this->str($check['type']).' AND fk_element='.(int) $object->id." AND status='approved'");
		$expiry = date('Y-m-d H:i:s', dol_now() + 86400 * max(1, $check['policy']->override_days));
		$snapshot = json_encode($this->serializableCheck($check), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->q('INSERT INTO '.$this->table('cl_override').' (entity,element_type,fk_element,element_ref,fk_soc,document_amount,reason,snapshot_json,status,fk_user_approve,date_approve,date_expiry) VALUES ('.$this->entity.','.$this->str($check['type']).','.(int) $object->id.','.$this->str($object->ref ?? '').','.$check['socid'].','.$check['document_amount'].','.$this->str(mb_substr(trim($reason),0,1000)).','.$this->str($snapshot).','.$this->str('approved').','.(int) $user->id.','.$this->str($this->db->idate(dol_now())).','.$this->str($expiry).')');
	}

	private function serializableCheck($check)
	{
		$out = $check; unset($out['invoices']); return $out;
	}

	private function approvedOverride($object, $check)
	{
		return $this->one('SELECT * FROM '.$this->table('cl_override').' WHERE entity='.$this->entity.' AND element_type='.$this->str($check['type']).' AND fk_element='.(int) $object->id.' AND fk_soc='.(int) $check['socid']." AND status='approved' AND date_expiry>=".$this->str($this->db->idate(dol_now())).' AND ABS(document_amount-'.$check['document_amount'].')<0.000001 ORDER BY rowid DESC LIMIT 1');
	}

	/** Run before the native validation transaction so a blocked attempt remains auditable. */
	public function preflight($object, $user)
	{
		if ($this->elementType($object) === 'customer_invoice' && (int) ($object->type ?? 0) === 2) return;
		if ($this->customerId($object) <= 0) return;
		$check = $this->evaluate($object, true);
		if (!$check['blocked'] || $this->approvedOverride($object, $check)) return;
		$reason = implode('; ', $check['violations']);
		$this->log($object, $check, 'blocked', $reason, $user);
		throw new DomainException('Credit Control: '.$reason.'. Minta Credit Manager membuat override pada dokumen ini.');
	}

	public function enforce($object, $user)
	{
		if ($this->elementType($object) === 'customer_invoice' && (int) ($object->type ?? 0) === 2) return;
		$socId = $this->customerId($object);
		if ($socId <= 0) return;
		$this->q('SELECT rowid FROM '.$this->table('societe').' WHERE entity='.$this->entity.' AND rowid='.$socId.' FOR UPDATE');
		$check = $this->evaluate($object, true);
		if (!$check['blocked'] && !$check['warning']) return;
		$override = $this->approvedOverride($object, $check);
		$decision = $check['blocked'] ? 'blocked' : 'warning';
		if ($check['blocked'] && $override) {
			$decision = 'override';
			$this->q('UPDATE '.$this->table('cl_override').' SET status='.$this->str('used').',fk_user_use='.(int) $user->id.',date_use='.$this->str($this->db->idate(dol_now())).' WHERE rowid='.(int) $override->rowid);
		}
		$this->log($object, $check, $decision, implode('; ', $check['violations']), $user);
		if ($check['warning']) $object->warnings[] = 'Credit Control: '.implode('; ', $check['violations']);
		if ($check['blocked'] && !$override) throw new DomainException('Credit Control: '.implode('; ', $check['violations']).'. Minta Credit Manager membuat override pada dokumen ini.');
	}

	private function log($object, $check, $decision, $reason, $user)
	{
		$this->q('INSERT INTO '.$this->table('cl_check_log').' (entity,fk_soc,element_type,fk_element,element_ref,decision,exposure,document_amount,projected_exposure,credit_limit,overdue_amount,oldest_overdue_days,reason,fk_user,date_check) VALUES ('.$this->entity.','.$check['socid'].','.$this->str($check['type']).','.(int) $object->id.','.$this->str($object->ref ?? '').','.$this->str($decision).','.$check['exposure'].','.$check['document_amount'].','.$check['projected'].','.$check['policy']->limit.','.$check['overdue'].','.$check['oldest_days'].','.$this->str($reason).','.(int) $user->id.','.$this->str($this->db->idate(dol_now())).')');
	}
}
