<?php

class PiStockEntry
{
	const DEFAULT_NUMBER_MASK = 'STE-{YYYY}-{ID:7}';

	public $pi;

	public function __construct(PerpetualInventory $pi)
	{
		$this->pi = $pi;
	}

	public static function purposes()
	{
		return array(
			'material_receipt' => 'Material Receipt',
			'material_issue' => 'Material Issue',
			'material_transfer' => 'Material Transfer',
			'count' => 'Stock Reconciliation',
			'adjustment' => 'Stock Adjustment',
			'return' => 'Stock Return',
			'reversal' => 'Stock Reversal',
		);
	}

	public static function validateNumberMask($mask)
	{
		$mask = trim((string) $mask);
		if ($mask === '' || strlen($mask) > 50 || !preg_match('/^[A-Za-z0-9._\-\/{}:]+$/', $mask) || !preg_match('/\{ID:([1-9]|1[0-2])\}/', $mask)) {
			throw new DomainException('Format nomor wajib memuat {ID:n} (n = 1–12) dan hanya boleh memakai huruf, angka, titik, garis, slash, serta token tanggal.');
		}
		$withoutTokens = preg_replace('/\{(?:YYYY|YY|MM|DD|ID:(?:[1-9]|1[0-2]))\}/', '', $mask);
		if (strpos($withoutTokens, '{') !== false || strpos($withoutTokens, '}') !== false) {
			throw new DomainException('Token format nomor tidak dikenal. Gunakan {YYYY}, {YY}, {MM}, {DD}, dan {ID:n}.');
		}
		return $mask;
	}

	public static function formatReference($mask, $id, $timestamp)
	{
		$mask = self::validateNumberMask($mask ?: self::DEFAULT_NUMBER_MASK);
		$values = array(
			'{YYYY}' => dol_print_date($timestamp, '%Y'),
			'{YY}' => dol_print_date($timestamp, '%y'),
			'{MM}' => dol_print_date($timestamp, '%m'),
			'{DD}' => dol_print_date($timestamp, '%d'),
		);
		$reference = strtr($mask, $values);
		$reference = preg_replace_callback('/\{ID:([1-9]|1[0-2])\}/', function ($match) use ($id) {
			return str_pad((string) $id, (int) $match[1], '0', STR_PAD_LEFT);
		}, $reference);
		if (strlen($reference) > 64) {
			throw new DomainException('Hasil nomor Stock Entry melebihi 64 karakter. Perpendek format nomor.');
		}
		return $reference;
	}

	public function numberMask()
	{
		$mask = function_exists('getDolGlobalString') ? getDolGlobalString('PI_STOCKENTRY_NUMBER_MASK') : '';
		return $mask ?: self::DEFAULT_NUMBER_MASK;
	}

	public function normalizePostingDate($date, $time)
	{
		$date = trim((string) $date);
		$time = trim((string) $time);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
			throw new DomainException('Posting Date tidak valid.');
		}
		if (!preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $t) || (int) $t[1] > 23 || (int) $t[2] > 59 || (isset($t[3]) && (int) $t[3] > 59)) {
			throw new DomainException('Posting Time tidak valid.');
		}
		$timestamp = dol_mktime((int) $t[1], (int) $t[2], isset($t[3]) ? (int) $t[3] : 0, (int) $d[2], (int) $d[3], (int) $d[1]);
		if (!$timestamp) {
			throw new DomainException('Posting Date dan Time tidak valid.');
		}
		return array($timestamp, $this->pi->db->idate($timestamp));
	}

	public function accounts()
	{
		$accounts = array();
		foreach ($this->pi->rows('SELECT account_number,label FROM '.$this->pi->table('accounting_account').' WHERE entity='.$this->pi->entity.' AND active=1 AND fk_pcg_version='.$this->pi->str($this->pi->chartVersion()).' ORDER BY account_number') as $account) {
			$accounts[$account->account_number] = $account->account_number.' — '.$account->label;
		}
		return $accounts;
	}

	public function fetch($id, $lock = false)
	{
		$document = $this->pi->one('SELECT * FROM '.$this->pi->table('pi_stock_entry').' WHERE entity='.$this->pi->entity.' AND rowid='.(int) $id.($lock ? ' FOR UPDATE' : ''));
		if (!$document) {
			throw new DomainException('Dokumen Stock Entry tidak ditemukan.');
		}
		$document->lines = $this->pi->rows(
			'SELECT l.*,p.ref product_ref,p.label product_label,p.fk_unit,sw.ref source_warehouse_ref,tw.ref target_warehouse_ref,a.label difference_account_label,'.
			' e.unit_cost posted_rate,e.value_delta posted_amount FROM '.$this->pi->table('pi_stock_entry_line').' l'.
			' LEFT JOIN '.$this->pi->table('product').' p ON p.rowid=l.fk_product'.
			' LEFT JOIN '.$this->pi->table('entrepot').' sw ON sw.rowid=l.fk_warehouse'.
			' LEFT JOIN '.$this->pi->table('entrepot').' tw ON tw.rowid=l.fk_target_warehouse'.
			' LEFT JOIN '.$this->pi->table('accounting_account').' a ON a.entity='.$this->pi->entity.' AND a.account_number=l.difference_account AND a.fk_pcg_version='.$this->pi->str($this->pi->chartVersion()).
			' LEFT JOIN '.$this->pi->table('pi_entry').' e ON e.rowid=l.fk_pi_entry AND e.entity='.$this->pi->entity.
			' WHERE l.fk_stock_entry='.(int) $id.' ORDER BY l.rang,l.rowid'
		);
		return $document;
	}

	public function saveDraft($id, $purpose, $note, array $lines, $user, $postingDate = '', $postingTime = '')
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin membuat Stock Entry.');
		}
		if (!isset(self::purposes()[$purpose])) {
			throw new DomainException('Stock Entry Type tidak valid.');
		}
		if (!$lines || count($lines) > 100 || !trim((string) $note)) {
			throw new DomainException('Isi 1 sampai 100 baris item dan alasan/catatan dokumen.');
		}
		if ($postingDate === '') $postingDate = dol_print_date(dol_now(), '%Y-%m-%d');
		if ($postingTime === '') $postingTime = dol_print_date(dol_now(), '%H:%M:%S');
		list($postingTimestamp, $postingStamp) = $this->normalizePostingDate($postingDate, $postingTime);
		return $this->pi->transaction(function () use ($id, $purpose, $note, $lines, $user, $postingTimestamp, $postingStamp) {
			$now = $this->pi->db->idate(dol_now());
			if ($id) {
				$document = $this->fetch($id, true);
				if ((int) $document->status !== 0) {
					throw new DomainException('Hanya Stock Entry Draft yang dapat diubah.');
				}
				$this->pi->q('UPDATE '.$this->pi->table('pi_stock_entry').' SET purpose='.$this->pi->str($purpose).',date_post='.$this->pi->str($postingStamp).',note='.$this->pi->str(mb_substr(trim($note), 0, 255)).' WHERE entity='.$this->pi->entity.' AND rowid='.(int) $id);
				$this->pi->q('DELETE FROM '.$this->pi->table('pi_stock_entry_line').' WHERE fk_stock_entry='.(int) $id);
			} else {
				$key = bin2hex(random_bytes(16));
				$this->pi->config(true);
				$this->pi->q('INSERT INTO '.$this->pi->table('pi_stock_entry').' (entity,ref,purpose,date_post,status,note,request_key,fk_user_author,datec) VALUES ('.$this->pi->entity.','.$this->pi->str('TMP-'.$key).','.$this->pi->str($purpose).','.$this->pi->str($postingStamp).',0,'.$this->pi->str(mb_substr(trim($note), 0, 255)).','.$this->pi->str($key).','.(int) $user->id.','.$this->pi->str($now).')');
				$id = (int) $this->pi->db->last_insert_id($this->pi->table('pi_stock_entry'));
				$ref = self::formatReference($this->numberMask(), $id, $postingTimestamp);
				$this->pi->q('UPDATE '.$this->pi->table('pi_stock_entry').' SET ref='.$this->pi->str($ref).' WHERE entity='.$this->pi->entity.' AND rowid='.$id);
			}
			foreach (array_values($lines) as $index => $line) {
				$qty = (float) ($line['qty'] ?? 0);
				$rawCost = array_key_exists('cost', $line) ? $line['cost'] : null;
				$cost = $rawCost === null ? 'NULL' : (float) $rawCost;
				if (!is_finite($qty) || ($qty == 0 && $purpose !== 'count') || ($rawCost !== null && (!is_finite((float) $rawCost) || (float) $rawCost < 0))) {
					throw new DomainException('Jumlah/modal tidak valid pada baris '.($index + 1).'.');
				}
				$this->pi->q('INSERT INTO '.$this->pi->table('pi_stock_entry_line').' (fk_stock_entry,rang,fk_product,fk_warehouse,fk_target_warehouse,qty,unit_cost,difference_account,fk_source_entry,note) VALUES ('.$id.','.(($index + 1) * 10).','.(int) ($line['product'] ?? 0).','.(int) ($line['warehouse'] ?? 0).','.(int) ($line['target'] ?? 0).','.$qty.','.$cost.','.$this->pi->str(trim((string) ($line['account'] ?? ''))).','.(int) ($line['source'] ?? 0).','.$this->pi->str(mb_substr(trim((string) ($line['note'] ?? '')), 0, 255)).')');
			}
			return $id;
		});
	}

	public function deleteDraft($id, $user)
	{
		if (!$user->hasRight('perpetualinventory', 'write')) {
			throw new DomainException('Tidak memiliki izin menghapus Stock Entry.');
		}
		$this->pi->transaction(function () use ($id) {
			$document = $this->fetch($id, true);
			if ((int) $document->status !== 0) {
				throw new DomainException('Hanya Stock Entry Draft yang dapat dihapus.');
			}
			$this->pi->q('DELETE FROM '.$this->pi->table('pi_stock_entry_line').' WHERE fk_stock_entry='.(int) $id);
			$this->pi->q('DELETE FROM '.$this->pi->table('pi_stock_entry').' WHERE entity='.$this->pi->entity.' AND rowid='.(int) $id);
		});
	}
}
