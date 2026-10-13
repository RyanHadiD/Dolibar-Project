<?php

/** ERPNext-inspired daily accounting reports backed by native Dolibarr invoices. */
class PiDailyReport
{
	private $pi;

	public function __construct(PerpetualInventory $pi)
	{
		$this->pi = $pi;
	}

	private function definition($kind)
	{
		if ($kind === 'receivable') {
			return array(
				'invoice' => 'facture',
				'payment_link' => 'paiement_facture',
				'payment' => 'paiement',
				'invoice_key' => 'fk_facture',
				'payment_key' => 'fk_paiement',
				'discount_target' => 'fk_facture',
				'discount_source' => 'fk_facture_source',
				'party_code' => "COALESCE(NULLIF(s.code_client,''),CAST(s.rowid AS CHAR))",
				'supplier_ref' => "''",
			);
		}
		if ($kind === 'payable') {
			return array(
				'invoice' => 'facture_fourn',
				'payment_link' => 'paiementfourn_facturefourn',
				'payment' => 'paiementfourn',
				'invoice_key' => 'fk_facturefourn',
				'payment_key' => 'fk_paiementfourn',
				'discount_target' => 'fk_invoice_supplier',
				'discount_source' => 'fk_invoice_supplier_source',
				'party_code' => "COALESCE(NULLIF(s.code_fournisseur,''),CAST(s.rowid AS CHAR))",
				'supplier_ref' => "COALESCE(f.ref_supplier,'')",
			);
		}
		throw new DomainException('Jenis Daily Report tidak valid.');
	}

	/** Validated invoice rows with payments and credit allocations measured at the report date. */
	public function register($kind, $start, $end, $partyId = 0, $includeOpening = false)
	{
		$d = $this->definition($kind);
		$endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
		$payment = '(SELECT pf.'.$d['invoice_key'].' invoice_id,SUM(pf.amount) amount FROM '.$this->pi->table($d['payment_link']).' pf'.
			' JOIN '.$this->pi->table($d['payment']).' p ON p.rowid=pf.'.$d['payment_key'].' AND p.entity='.$this->pi->entity.
			' WHERE p.datep<'.$this->pi->str($endExclusive).' GROUP BY pf.'.$d['invoice_key'].') paid';
		$discountTarget = '(SELECT '.$d['discount_target'].' invoice_id,SUM(amount_ttc) amount FROM '.$this->pi->table('societe_remise_except').
			' WHERE entity='.$this->pi->entity.' AND '.$d['discount_target'].' IS NOT NULL AND datec<'.$this->pi->str($endExclusive).' GROUP BY '.$d['discount_target'].') used_discount';
		$discountSource = '(SELECT '.$d['discount_source'].' invoice_id,SUM(amount_ttc) amount FROM '.$this->pi->table('societe_remise_except').
			' WHERE entity='.$this->pi->entity.' AND '.$d['discount_source'].' IS NOT NULL AND datec<'.$this->pi->str($endExclusive).' GROUP BY '.$d['discount_source'].') source_discount';
		$sql = 'SELECT f.rowid,f.ref,'.$d['supplier_ref'].' supplier_ref,f.type,f.datef posting_date,COALESCE(f.date_lim_reglement,f.datef) due_date,'.
			'f.fk_soc party_id,'.$d['party_code'].' party_code,s.nom party_name,s.tva_intra tax_id,'.
			'f.total_ht net_total,f.total_tva tax_total,(COALESCE(f.localtax1,0)+COALESCE(f.localtax2,0)+COALESCE(f.revenuestamp,0)) other_tax,f.total_ttc grand_total,'.
			'COALESCE(paid.amount,0) payment_amount,COALESCE(used_discount.amount,0) allocated_credit,COALESCE(source_discount.amount,0) credit_used,'.
			$this->pi->str(getDolGlobalString('MAIN_MONNAIE', 'IDR')).' currency,'.
			'COALESCE(cp.code,\'\') payment_mode,COALESCE(pr.ref,\'\') project_ref,f.close_code,f.date_closing '.
			'FROM '.$this->pi->table($d['invoice']).' f JOIN '.$this->pi->table('societe').' s ON s.rowid=f.fk_soc '.
			'LEFT JOIN '.$payment.' ON paid.invoice_id=f.rowid LEFT JOIN '.$discountTarget.' ON used_discount.invoice_id=f.rowid '.
			'LEFT JOIN '.$discountSource.' ON source_discount.invoice_id=f.rowid LEFT JOIN '.$this->pi->table('c_paiement').' cp ON cp.id=f.fk_mode_reglement '.
			'LEFT JOIN '.$this->pi->table('projet').' pr ON pr.rowid=f.fk_projet '.
			'WHERE f.entity='.$this->pi->entity.' AND f.fk_statut>0 AND f.datef>='.$this->pi->str($start).' AND f.datef<'.$this->pi->str($endExclusive);
		if (!$includeOpening) {
			$sql .= " AND COALESCE(f.ref_ext,'') NOT LIKE 'OPENINGBALANCE:%'";
		}
		if ($partyId > 0) {
			$sql .= ' AND f.fk_soc='.(int) $partyId;
		}
		$sql .= ' ORDER BY f.datef,f.ref,f.rowid';

		$rows = array();
		foreach ($this->pi->rows($sql) as $row) {
			foreach (array('net_total', 'tax_total', 'other_tax', 'grand_total', 'payment_amount', 'allocated_credit', 'credit_used') as $field) {
				$row->{$field} = (float) $row->{$field};
			}
			if ($row->type == 2) {
				$row->paid_amount = $row->credit_used;
				$row->outstanding_amount = $row->grand_total + $row->credit_used;
			} else {
				$row->paid_amount = $row->payment_amount + $row->allocated_credit;
				$row->outstanding_amount = $row->grand_total - $row->paid_amount;
			}
			if ($row->close_code && $row->date_closing && substr($row->date_closing, 0, 10) <= $end) {
				$row->outstanding_amount = 0.0;
			}
			if (abs($row->outstanding_amount) < 0.005) {
				$row->outstanding_amount = 0.0;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Party-level outstanding summary with standard ageing buckets. */
	public function summary($kind, $reportDate, $partyId = 0)
	{
		// Opening invoices are excluded from period sales/purchase registers, but must
		// remain in receivable/payable ageing until they are settled.
		$rows = $this->register($kind, '1000-01-01', $reportDate, $partyId, true);
		$parties = array();
		foreach ($rows as $row) {
			$key = (int) $row->party_id;
			if (!isset($parties[$key])) {
				$parties[$key] = (object) array(
					'party_id' => $key,
					'party_code' => $row->party_code,
					'party_name' => $row->party_name,
					'invoiced' => 0.0,
					'paid' => 0.0,
					'credit_note' => 0.0,
					'outstanding' => 0.0,
					'not_due' => 0.0,
					'range_1' => 0.0,
					'range_2' => 0.0,
					'range_3' => 0.0,
					'range_4' => 0.0,
				);
			}
			$party = $parties[$key];
			if ($row->type == 2) {
				$party->credit_note += abs($row->grand_total);
			} else {
				$party->invoiced += $row->grand_total;
				$party->paid += $row->paid_amount;
			}
			$party->outstanding += $row->outstanding_amount;
			if ($row->outstanding_amount <= 0) {
				continue;
			}
			$days = (int) (new DateTimeImmutable($row->due_date))->diff(new DateTimeImmutable($reportDate))->format('%r%a');
			if ($days < 0) {
				$party->not_due += $row->outstanding_amount;
			} elseif ($days <= 30) {
				$party->range_1 += $row->outstanding_amount;
			} elseif ($days <= 60) {
				$party->range_2 += $row->outstanding_amount;
			} elseif ($days <= 90) {
				$party->range_3 += $row->outstanding_amount;
			} else {
				$party->range_4 += $row->outstanding_amount;
			}
		}
		$parties = array_values(array_filter($parties, function ($party) {
			return abs($party->outstanding) >= 0.005;
		}));
		usort($parties, function ($a, $b) {
			return strnatcasecmp($a->party_name, $b->party_name);
		});
		return $parties;
	}

	public function parties($kind)
	{
		$d = $this->definition($kind);
		$sql = 'SELECT DISTINCT s.rowid,s.nom FROM '.$this->pi->table('societe').' s JOIN '.$this->pi->table($d['invoice']).' f ON f.fk_soc=s.rowid'.
			' WHERE f.entity='.$this->pi->entity.' AND f.fk_statut>0 ORDER BY s.nom';
		$options = array();
		foreach ($this->pi->rows($sql) as $party) {
			$options[(int) $party->rowid] = $party->nom;
		}
		return $options;
	}
}
