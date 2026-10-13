<?php

/** Controlled opening balance batches from Draft through native finalization. */
class OpeningBalance
{
	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_POSTED = 2;
	const STATUS_LOCKED = 3;

	public $db;
	public $entity;

	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	public function table($name)
	{
		return $this->db->prefix().$name;
	}

	public function str($value)
	{
		return "'".$this->db->escape((string) $value)."'";
	}

	public function q($sql)
	{
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException($this->db->lasterror());
		}
		return $result;
	}

	public function rows($sql)
	{
		$result = $this->q($sql);
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $row;
		}
		return $rows;
	}

	public function one($sql)
	{
		$result = $this->q($sql);
		return $this->db->fetch_object($result) ?: null;
	}

	public function transaction($callback)
	{
		$this->db->begin();
		try {
			$value = $callback();
			if ($this->db->commit() < 0) {
				throw new RuntimeException($this->db->lasterror());
			}
			return $value;
		} catch (Throwable $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	public function listBatches()
	{
		return $this->rows('SELECT b.*,u.login author_login FROM '.$this->table('ob_batch').' b LEFT JOIN '.$this->table('user').' u ON u.rowid=b.fk_user_author WHERE b.entity='.$this->entity.' ORDER BY b.rowid DESC');
	}

	public function batch($id, $lock = false)
	{
		$batch = $this->one('SELECT * FROM '.$this->table('ob_batch').' WHERE entity='.$this->entity.' AND rowid='.(int) $id.($lock ? ' FOR UPDATE' : ''));
		if (!$batch) {
			throw new DomainException('Batch saldo awal tidak ditemukan.');
		}
		return $batch;
	}

	public function createBatch($cutoverDate, $label, $user)
	{
		$this->validDate($cutoverDate, 'Tanggal cutover');
		if ($cutoverDate > date('Y-m-d', dol_now())) {
			throw new DomainException('Tanggal cutover tidak boleh di masa depan.');
		}
		$label = trim((string) $label);
		if ($label === '') {
			throw new DomainException('Nama batch wajib diisi.');
		}
		for ($attempt = 0; $attempt < 5; $attempt++) {
			$ref = 'OB-'.str_replace('-', '', $cutoverDate).'-'.strtoupper(bin2hex(random_bytes(3)));
			if (!$this->one('SELECT rowid FROM '.$this->table('ob_batch').' WHERE entity='.$this->entity.' AND ref='.$this->str($ref))) {
				break;
			}
		}
		$this->q('INSERT INTO '.$this->table('ob_batch').' (entity,ref,label,cutover_date,status,fk_user_author,datec) VALUES ('.$this->entity.','.$this->str($ref).','.$this->str(substr($label, 0, 255)).','.$this->str($cutoverDate).',0,'.(int) $user->id.','.$this->str($this->db->idate(dol_now())).')');
		return (int) $this->db->last_insert_id($this->table('ob_batch'));
	}

	public function saveHeader($id, $data)
	{
		$batch = $this->batch($id);
		$this->assertDraft($batch);
		$this->validDate($data['cutover_date'], 'Tanggal cutover');
		if ($data['cutover_date'] > date('Y-m-d', dol_now())) {
			throw new DomainException('Tanggal cutover tidak boleh di masa depan.');
		}
		if (trim($data['label']) === '') {
			throw new DomainException('Nama batch wajib diisi.');
		}
		$fields = array('label', 'cutover_date', 'clearing_account', 'stock_account', 'receivable_account', 'payable_account', 'journal_code', 'note');
		$sets = array();
		foreach ($fields as $field) {
			$limit = $field === 'note' ? 2000 : 255;
			$sets[] = $field.'='.$this->str(substr(trim((string) $data[$field]), 0, $limit));
		}
		$this->q('UPDATE '.$this->table('ob_batch').' SET '.implode(',', $sets).' WHERE entity='.$this->entity.' AND rowid='.(int) $id);
	}

	public function stockLines($id)
	{
		return $this->rows('SELECT l.*,p.ref product_ref,p.label product_label,w.ref warehouse_ref FROM '.$this->table('ob_stock_line').' l JOIN '.$this->table('product').' p ON p.rowid=l.fk_product JOIN '.$this->table('entrepot').' w ON w.rowid=l.fk_warehouse WHERE l.entity='.$this->entity.' AND l.fk_batch='.(int) $id.' ORDER BY l.rowid');
	}

	public function partyLines($id, $kind)
	{
		$this->partyKind($kind);
		return $this->rows('SELECT l.*,s.nom party_name,s.code_client,s.code_fournisseur FROM '.$this->table('ob_party_line').' l JOIN '.$this->table('societe').' s ON s.rowid=l.fk_soc WHERE l.entity='.$this->entity.' AND l.fk_batch='.(int) $id.' AND l.kind='.$this->str($kind).' ORDER BY l.rowid');
	}

	public function glLines($id)
	{
		return $this->rows('SELECT l.*,a.label account_label FROM '.$this->table('ob_gl_line').' l LEFT JOIN '.$this->table('accounting_account').' a ON a.entity=l.entity AND a.account_number=l.account_number AND a.fk_pcg_version IN (SELECT s.pcg_version FROM '.$this->table('accounting_system').' s WHERE s.rowid='.(int) getDolGlobalInt('CHARTOFACCOUNTS').') WHERE l.entity='.$this->entity.' AND l.fk_batch='.(int) $id.' ORDER BY l.rowid');
	}

	/** Import a complete section from CSV. Existing rows in that section are replaced only after all rows validate. */
	public function importCsvSection($id, $section, $content)
	{
		$schemas = array(
			'stock' => array('product_ref', 'warehouse_ref', 'qty', 'unit_cost', 'note'),
			'ar' => array('party_code', 'source_ref', 'invoice_date', 'due_date', 'original_amount', 'prior_paid', 'outstanding', 'currency', 'note'),
			'ap' => array('party_code', 'source_ref', 'invoice_date', 'due_date', 'original_amount', 'prior_paid', 'outstanding', 'currency', 'note'),
			'gl' => array('account_number', 'debit', 'credit', 'note'),
		);
		if (!isset($schemas[$section])) {
			throw new DomainException('Jenis import CSV tidak valid.');
		}
		if (!is_string($content) || $content === '') {
			throw new DomainException('File CSV kosong.');
		}
		if (strlen($content) > 2 * 1024 * 1024) {
			throw new DomainException('File CSV terlalu besar. Maksimal 2 MB.');
		}

		$records = $this->parseCsv($content, $schemas[$section]);
		$lines = array();
		foreach ($records as $index => $record) {
			$rowNumber = $index + 2;
			if ($section === 'stock') {
				$product = $this->one('SELECT rowid FROM '.$this->table('product').' WHERE entity='.$this->entity.' AND ref='.$this->str($record['product_ref']).' AND fk_product_type=0 AND stockable_product=1');
				if (!$product) {
					throw new DomainException('CSV baris '.$rowNumber.': produk '.$record['product_ref'].' tidak ditemukan atau bukan produk stok.');
				}
				$warehouse = $this->one('SELECT rowid FROM '.$this->table('entrepot').' WHERE entity='.$this->entity.' AND statut=1 AND ref='.$this->str($record['warehouse_ref']));
				if (!$warehouse) {
					throw new DomainException('CSV baris '.$rowNumber.': gudang '.$record['warehouse_ref'].' tidak ditemukan.');
				}
				$lines[] = array('product' => $product->rowid, 'warehouse' => $warehouse->rowid, 'qty' => $record['qty'], 'unit_cost' => $record['unit_cost'], 'note' => $record['note']);
			} elseif ($section === 'ar' || $section === 'ap') {
				$codeField = $section === 'ar' ? 'code_client' : 'code_fournisseur';
				$flag = $section === 'ar' ? 'client' : 'fournisseur';
				$party = $this->one('SELECT rowid FROM '.$this->table('societe').' WHERE entity='.$this->entity.' AND '.$codeField.'='.$this->str($record['party_code']).' AND '.$flag.'>0');
				if (!$party) {
					throw new DomainException('CSV baris '.$rowNumber.': kode '.($section === 'ar' ? 'customer ' : 'supplier ').$record['party_code'].' tidak ditemukan.');
				}
				$lines[] = array(
					'party' => $party->rowid, 'source_ref' => $record['source_ref'], 'invoice_date' => $record['invoice_date'],
					'due_date' => $record['due_date'], 'original_amount' => $record['original_amount'], 'prior_paid' => $record['prior_paid'],
					'outstanding' => $record['outstanding'], 'currency' => $record['currency'], 'note' => $record['note'],
				);
			} else {
				$lines[] = array('account' => $record['account_number'], 'debit' => $record['debit'], 'credit' => $record['credit'], 'note' => $record['note']);
			}
		}

		if (!$lines) {
			throw new DomainException('CSV tidak memiliki baris data.');
		}
		if ($section === 'stock') {
			$this->saveStockLines($id, $lines);
		} elseif ($section === 'ar' || $section === 'ap') {
			$this->savePartyLines($id, $section, $lines);
		} else {
			$this->saveGlLines($id, $lines);
		}
		return count($lines);
	}

	private function parseCsv($content, $requiredHeaders)
	{
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		$firstLine = strtok($content, "\r\n");
		$delimiter = ',';
		$bestCount = 0;
		foreach (array(',', ';', "\t") as $candidate) {
			$count = count(str_getcsv((string) $firstLine, $candidate, '"', '\\'));
			if ($count > $bestCount) {
				$bestCount = $count;
				$delimiter = $candidate;
			}
		}

		$stream = fopen('php://temp', 'w+');
		if (!$stream) {
			throw new RuntimeException('CSV tidak dapat diproses.');
		}
		fwrite($stream, $content);
		rewind($stream);
		$header = fgetcsv($stream, 0, $delimiter, '"', '\\');
		if (!is_array($header)) {
			fclose($stream);
			throw new DomainException('Header CSV tidak ditemukan.');
		}
		$header = array_map(function ($value) {
			return strtolower(trim((string) $value));
		}, $header);
		if (count($header) !== count(array_unique($header))) {
			fclose($stream);
			throw new DomainException('Header CSV tidak boleh duplikat.');
		}
		foreach ($requiredHeaders as $required) {
			if (!in_array($required, $header, true)) {
				fclose($stream);
				throw new DomainException('Kolom CSV wajib tidak ditemukan: '.$required.'.');
			}
		}

		$records = array();
		while (($values = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
			$nonEmpty = array_filter($values, function ($value) { return trim((string) $value) !== ''; });
			if (!$nonEmpty) {
				continue;
			}
			if (count($records) >= 5000) {
				fclose($stream);
				throw new DomainException('CSV maksimal berisi 5.000 baris data.');
			}
			$record = array();
			foreach ($requiredHeaders as $field) {
				$position = array_search($field, $header, true);
				$record[$field] = isset($values[$position]) ? trim((string) $values[$position]) : '';
			}
			$records[] = $record;
		}
		fclose($stream);
		return $records;
	}

	public function saveStockLines($id, $lines)
	{
		return $this->transaction(function () use ($id, $lines) {
			$batch = $this->batch($id, true);
			$this->assertDraft($batch);
			$clean = array();
			$seen = array();
			foreach ($lines as $index => $line) {
				$product = (int) $line['product'];
				$warehouse = (int) $line['warehouse'];
				if (!$product && !$warehouse && trim((string) $line['qty']) === '' && trim((string) $line['unit_cost']) === '') {
					continue;
				}
				$qty = $this->number($line['qty'], 'Qty baris '.($index + 1));
				$cost = $this->number($line['unit_cost'], 'Modal baris '.($index + 1));
				if (!$product || !$warehouse || $qty <= 0 || $cost < 0) {
					throw new DomainException('Baris persediaan '.($index + 1).' harus memiliki produk, gudang, qty positif, dan modal tidak negatif.');
				}
				$master = $this->one('SELECT p.rowid FROM '.$this->table('product').' p JOIN '.$this->table('entrepot').' w ON w.rowid='.(int) $warehouse.' WHERE p.rowid='.(int) $product.' AND p.entity='.$this->entity.' AND p.fk_product_type=0 AND p.stockable_product=1 AND w.entity='.$this->entity);
				if (!$master) {
					throw new DomainException('Produk atau gudang baris '.($index + 1).' tidak valid.');
				}
				$key = $product.':'.$warehouse;
				if (isset($seen[$key])) {
					throw new DomainException('Produk dan gudang yang sama tidak boleh muncul dua kali.');
				}
				$seen[$key] = true;
				$clean[] = array($product, $warehouse, $qty, $cost, substr(trim((string) $line['note']), 0, 255));
			}
			$this->q('DELETE FROM '.$this->table('ob_stock_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id);
			foreach ($clean as $line) {
				$this->q('INSERT INTO '.$this->table('ob_stock_line').' (entity,fk_batch,fk_product,fk_warehouse,qty,unit_cost,note) VALUES ('.$this->entity.','.(int) $id.','.$line[0].','.$line[1].','.$line[2].','.$line[3].','.$this->str($line[4]).')');
			}
		});
	}

	public function savePartyLines($id, $kind, $lines)
	{
		$this->partyKind($kind);
		return $this->transaction(function () use ($id, $kind, $lines) {
			$batch = $this->batch($id, true);
			$this->assertDraft($batch);
			$clean = array();
			$seen = array();
			foreach ($lines as $index => $line) {
				$party = (int) $line['party'];
				$ref = trim((string) $line['source_ref']);
				if (!$party && $ref === '' && trim((string) $line['outstanding']) === '') {
					continue;
				}
				$this->validDate($line['invoice_date'], 'Tanggal invoice baris '.($index + 1));
				$this->validDate($line['due_date'], 'Jatuh tempo baris '.($index + 1));
				if ($line['invoice_date'] > $batch->cutover_date) {
					throw new DomainException('Tanggal invoice baris '.($index + 1).' melewati tanggal cutover.');
				}
				if ($line['due_date'] < $line['invoice_date']) {
					throw new DomainException('Jatuh tempo baris '.($index + 1).' tidak boleh sebelum tanggal invoice.');
				}
				$original = $this->number($line['original_amount'], 'Nilai awal baris '.($index + 1));
				$paid = $this->number($line['prior_paid'], 'Sudah dibayar baris '.($index + 1));
				$outstanding = $this->number($line['outstanding'], 'Outstanding baris '.($index + 1));
				if (!$party || $ref === '' || $original <= 0 || $paid < 0 || $outstanding <= 0 || abs($original - $paid - $outstanding) > 0.005) {
					throw new DomainException('Baris '.($index + 1).' harus memiliki pihak, referensi, dan Nilai Awal − Sudah Dibayar = Outstanding.');
				}
				$flag = $kind === 'ar' ? 'client' : 'fournisseur';
				if (!$this->one('SELECT rowid FROM '.$this->table('societe').' WHERE entity='.$this->entity.' AND rowid='.$party.' AND '.$flag.'>0')) {
					throw new DomainException(($kind === 'ar' ? 'Customer' : 'Supplier').' baris '.($index + 1).' tidak valid.');
				}
				$key = $party.':'.strtolower($ref);
				if (isset($seen[$key])) {
					throw new DomainException('Referensi yang sama untuk pihak yang sama tidak boleh muncul dua kali.');
				}
				$seen[$key] = true;
				$currency = strtoupper(trim((string) $line['currency']));
				if (!preg_match('/^[A-Z]{3}$/', $currency)) {
					throw new DomainException('Mata uang baris '.($index + 1).' harus berupa kode tiga huruf.');
				}
				$clean[] = array($party, substr($ref, 0, 128), $line['invoice_date'], $line['due_date'], $original, $paid, $outstanding, $currency, substr(trim((string) $line['note']), 0, 255));
			}
			$this->q('DELETE FROM '.$this->table('ob_party_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id.' AND kind='.$this->str($kind));
			foreach ($clean as $line) {
				$this->q('INSERT INTO '.$this->table('ob_party_line').' (entity,fk_batch,kind,fk_soc,source_ref,invoice_date,due_date,original_amount,prior_paid,outstanding,currency,note) VALUES ('.$this->entity.','.(int) $id.','.$this->str($kind).','.$line[0].','.$this->str($line[1]).','.$this->str($line[2]).','.$this->str($line[3]).','.$line[4].','.$line[5].','.$line[6].','.$this->str($line[7]).','.$this->str($line[8]).')');
			}
		});
	}

	public function saveGlLines($id, $lines)
	{
		return $this->transaction(function () use ($id, $lines) {
			$batch = $this->batch($id, true);
			$this->assertDraft($batch);
			$forbidden = array_filter(array($batch->clearing_account, $batch->stock_account, $batch->receivable_account, $batch->payable_account));
			$clean = array();
			foreach ($lines as $index => $line) {
				$account = trim((string) $line['account']);
				if ($account === '' && trim((string) $line['debit']) === '' && trim((string) $line['credit']) === '') {
					continue;
				}
				$debit = trim((string) $line['debit']) === '' ? 0.0 : $this->number($line['debit'], 'Debit baris '.($index + 1));
				$credit = trim((string) $line['credit']) === '' ? 0.0 : $this->number($line['credit'], 'Kredit baris '.($index + 1));
				if ($account === '' || $debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
					throw new DomainException('Baris saldo akun '.($index + 1).' harus berisi tepat satu nilai debit atau kredit positif.');
				}
				if (in_array($account, $forbidden, true)) {
					throw new DomainException('Akun kontrol/clearing tidak boleh dimasukkan ulang pada saldo akun.');
				}
				if (!$this->accountExists($account)) {
					throw new DomainException('Akun '.$account.' tidak aktif atau tidak tersedia.');
				}
				$clean[] = array($account, $debit, $credit, substr(trim((string) $line['note']), 0, 255));
			}
			$this->q('DELETE FROM '.$this->table('ob_gl_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id);
			foreach ($clean as $line) {
				$this->q('INSERT INTO '.$this->table('ob_gl_line').' (entity,fk_batch,account_number,debit,credit,note) VALUES ('.$this->entity.','.(int) $id.','.$this->str($line[0]).','.$line[1].','.$line[2].','.$this->str($line[3]).')');
			}
		});
	}

	public function summary($id)
	{
		$this->batch($id);
		$stock = $this->one('SELECT COUNT(*) count,COALESCE(SUM(qty*unit_cost),0) amount FROM '.$this->table('ob_stock_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id);
		$ar = $this->one('SELECT COUNT(*) count,COALESCE(SUM(outstanding),0) amount FROM '.$this->table('ob_party_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id." AND kind='ar'");
		$ap = $this->one('SELECT COUNT(*) count,COALESCE(SUM(outstanding),0) amount FROM '.$this->table('ob_party_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id." AND kind='ap'");
		$gl = $this->one('SELECT COUNT(*) count,COALESCE(SUM(debit),0) debit,COALESCE(SUM(credit),0) credit FROM '.$this->table('ob_gl_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id);
		$debit = (float) $stock->amount + (float) $ar->amount + (float) $gl->debit;
		$credit = (float) $ap->amount + (float) $gl->credit;
		return array(
			'stock_count' => (int) $stock->count, 'stock' => (float) $stock->amount,
			'ar_count' => (int) $ar->count, 'ar' => (float) $ar->amount,
			'ap_count' => (int) $ap->count, 'ap' => (float) $ap->amount,
			'gl_count' => (int) $gl->count, 'gl_debit' => (float) $gl->debit, 'gl_credit' => (float) $gl->credit,
			'debit' => $debit, 'credit' => $credit, 'difference' => round($debit - $credit, 8),
		);
	}

	public function validateBatch($id, $user)
	{
		return $this->transaction(function () use ($id, $user) {
			$batch = $this->batch($id, true);
			$this->assertDraft($batch);
			$accounts = array($batch->clearing_account, $batch->stock_account, $batch->receivable_account, $batch->payable_account);
			if (count(array_filter($accounts)) !== 4 || count(array_unique($accounts)) !== 4) {
				throw new DomainException('Empat akun konfigurasi wajib dipilih dan harus berbeda.');
			}
			foreach ($accounts as $account) {
				if (!$this->accountExists($account)) {
					throw new DomainException('Akun '.$account.' tidak aktif atau tidak tersedia.');
				}
			}
			if (!$batch->journal_code || !$this->one('SELECT rowid FROM '.$this->table('accounting_journal').' WHERE entity='.$this->entity.' AND active=1 AND code='.$this->str($batch->journal_code))) {
				throw new DomainException('Jurnal saldo awal wajib dipilih dan harus aktif.');
			}
			$summary = $this->summary($id);
			if (!$summary['stock_count'] && !$summary['ar_count'] && !$summary['ap_count'] && !$summary['gl_count']) {
				throw new DomainException('Batch belum memiliki baris saldo awal.');
			}
			if (abs($summary['difference']) > 0.005) {
				throw new DomainException('Batch belum seimbang. Selisih debit dan kredit: '.price($summary['difference']).'.');
			}
			$this->q('UPDATE '.$this->table('ob_batch').' SET status=1,fk_user_valid='.(int) $user->id.',date_validated='.$this->str($this->db->idate(dol_now())).' WHERE entity='.$this->entity.' AND rowid='.(int) $id);
		});
	}

	public function reopen($id)
	{
		$batch = $this->batch($id);
		if ((int) $batch->status !== self::STATUS_VALIDATED) {
			throw new DomainException('Hanya batch tervalidasi yang dapat dikembalikan ke Draft.');
		}
		$this->q('UPDATE '.$this->table('ob_batch').' SET status=0,fk_user_valid=NULL,date_validated=NULL WHERE entity='.$this->entity.' AND rowid='.(int) $id);
	}

	/** Return every condition that still blocks irreversible posting. */
	public function postingProblems($id)
	{
		global $conf;
		$batch = $this->batch($id);
		$problems = array();
		if ((int) $batch->status !== self::STATUS_VALIDATED) {
			$problems[] = 'Batch harus berstatus Validated.';
		}
		$summary = $this->summary($id);
		if (abs($summary['difference']) > 0.005) {
			$problems[] = 'Total debit dan kredit tidak seimbang.';
		}
		if ($this->one('SELECT rowid FROM '.$this->table('accounting_fiscalyear').' WHERE entity='.$this->entity.' AND statut=1 AND '.$this->str($batch->cutover_date).' BETWEEN date_start AND date_end LIMIT 1')) {
			$problems[] = 'Tanggal cutover berada dalam periode akuntansi yang sudah ditutup.';
		}
		if ($this->one('SELECT rowid FROM '.$this->table('ob_posting_link').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id.' LIMIT 1')) {
			$problems[] = 'Batch sudah memiliki hasil posting.';
		}
		$currency = strtoupper((string) $conf->currency);
		if ($this->one('SELECT rowid FROM '.$this->table('ob_party_line').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id.' AND currency<>'.$this->str($currency).' LIMIT 1')) {
			$problems[] = 'Versi ini hanya dapat memposting mata uang dasar '.$currency.'.';
		}
		foreach ($this->partyLines($id, 'ar') as $line) {
			$party = $this->partyAccounting($line->fk_soc, 'ar');
			if (!$party || $party->general_account !== $batch->receivable_account) {
				$problems[] = 'Akun kontrol customer '.$line->party_name.' harus sama dengan akun Piutang batch ('.$batch->receivable_account.').';
			}
			if (!$party || trim((string) $party->party_code) === '') {
				$problems[] = 'Customer '.$line->party_name.' belum memiliki kode customer.';
			}
			if ($this->one('SELECT rowid FROM '.$this->table('facture').' WHERE entity='.$this->entity.' AND ref_ext='.$this->str($this->invoiceMarker($id, 'ar', $line->rowid)).' LIMIT 1')) {
				$problems[] = 'Opening invoice piutang '.$line->source_ref.' sudah pernah dibuat.';
			}
		}
		foreach ($this->partyLines($id, 'ap') as $line) {
			$party = $this->partyAccounting($line->fk_soc, 'ap');
			if (!$party || $party->general_account !== $batch->payable_account) {
				$problems[] = 'Akun kontrol supplier '.$line->party_name.' harus sama dengan akun Hutang batch ('.$batch->payable_account.').';
			}
			if (!$party || trim((string) $party->party_code) === '') {
				$problems[] = 'Supplier '.$line->party_name.' belum memiliki kode supplier.';
			}
			if ($this->one('SELECT rowid FROM '.$this->table('facture_fourn').' WHERE entity='.$this->entity.' AND ref_ext='.$this->str($this->invoiceMarker($id, 'ap', $line->rowid)).' LIMIT 1')) {
				$problems[] = 'Opening invoice hutang '.$line->source_ref.' sudah pernah dibuat.';
			}
		}

		if ($summary['stock_count']) {
			$config = $this->one('SELECT * FROM '.$this->table('pi_config').' WHERE entity='.$this->entity);
			if (!$config) {
				$problems[] = 'Konfigurasi Perpetual Inventory belum tersedia.';
			} elseif ($config->stock_account !== $batch->stock_account) {
				$problems[] = 'Akun Persediaan batch harus sama dengan akun Persediaan Perpetual Inventory.';
			}
			$running = $config && ((int) $config->enabled !== 0 || $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' LIMIT 1'));
			if ($this->one('SELECT p.rowid FROM '.$this->table('ob_stock_line').' l JOIN '.$this->table('product').' p ON p.rowid=l.fk_product WHERE l.entity='.$this->entity.' AND l.fk_batch='.(int) $id.' AND COALESCE(p.tobatch,0)<>0 LIMIT 1')) {
				$problems[] = 'Produk lot/serial belum didukung oleh finalisasi saldo awal.';
			}
			if (!$running && $this->one('SELECT ps.rowid FROM '.$this->table('product_stock').' ps JOIN '.$this->table('product').' p ON p.rowid=ps.fk_product JOIN '.$this->table('entrepot').' w ON w.rowid=ps.fk_entrepot WHERE p.entity='.$this->entity.' AND w.entity='.$this->entity.' AND ABS(ps.reel)>0.00000001 AND NOT EXISTS (SELECT l.rowid FROM '.$this->table('ob_stock_line').' l WHERE l.entity='.$this->entity.' AND l.fk_batch='.(int) $id.' AND l.fk_product=ps.fk_product AND l.fk_warehouse=ps.fk_entrepot) LIMIT 1')) {
				$problems[] = 'Semua stok native yang tidak nol harus dicantumkan dalam batch persediaan.';
			}
			foreach (!$running ? $this->stockLines($id) : array() as $line) {
				$native = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.(int) $line->fk_product.' AND fk_entrepot='.(int) $line->fk_warehouse);
				if ($native && abs((float) $native->reel) > 0.00000001 && abs((float) $native->reel - (float) $line->qty) > 0.00000001) {
					$problems[] = 'Qty native '.$line->product_ref.'/'.$line->warehouse_ref.' harus nol atau sama dengan qty batch.';
				}
			}
		}
		return array_values(array_unique($problems));
	}

	/** Atomically create native opening invoices, inventory ledger and finalized bookkeeping. */
	public function finalizeBatch($id, $user)
	{
		if (!$user->hasRight('openingbalance', 'post')) {
			throw new DomainException('Tidak memiliki izin finalisasi saldo awal.');
		}
		return $this->transaction(function () use ($id, $user) {
			$batch = $this->batch($id, true);
			if ((int) $batch->status !== self::STATUS_VALIDATED) {
				throw new DomainException('Hanya batch Validated yang dapat difinalisasi.');
			}
			$problems = $this->postingProblems($id);
			if ($problems) {
				throw new DomainException(implode(' ', $problems));
			}
			$summary = $this->summary($id);
			if ($summary['stock_count'] && !$user->admin) {
				throw new DomainException('Posting saldo awal persediaan hanya dapat dilakukan administrator.');
			}
			$canValidateCustomerInvoice = getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') ? $user->hasRight('facture', 'invoice_advance', 'validate') : $user->hasRight('facture', 'creer');
			if ($summary['ar_count'] && !$canValidateCustomerInvoice) {
				throw new DomainException('Hak native untuk memvalidasi customer invoice diperlukan.');
			}
			if ($summary['ap_count'] && !$user->admin && !$user->hasRight('fournisseur', 'facture', 'creer')) {
				throw new DomainException('Hak membuat dan memvalidasi supplier invoice diperlukan.');
			}

			$validatedJournalIds = array();
			$this->finalizeStock($batch, $user, $validatedJournalIds);
			foreach ($this->partyLines($id, 'ar') as $line) {
				$this->finalizePartyLine($batch, $line, 'ar', $user, $validatedJournalIds);
			}
			foreach ($this->partyLines($id, 'ap') as $line) {
				$this->finalizePartyLine($batch, $line, 'ap', $user, $validatedJournalIds);
			}
			foreach ($this->glLines($id) as $line) {
				$label = $batch->ref.' — Saldo awal akun '.$line->account_number.' #'.$line->rowid;
				$lines = (float) $line->debit > 0
					? array(array($line->account_number, (float) $line->debit, 0), array($batch->clearing_account, 0, (float) $line->debit))
					: array(array($batch->clearing_account, (float) $line->credit, 0), array($line->account_number, 0, (float) $line->credit));
				$ids = $this->postPiece($batch, 'openingbalance', $batch->ref, $batch->rowid, $line->rowid, $batch->cutover_date, null, $label, $lines, $user);
				$validatedJournalIds = array_merge($validatedJournalIds, $ids);
				foreach ($ids as $journalId) $this->link($batch->rowid, 'gl', $line->rowid, 'bookkeeping', $journalId);
			}
			if ($validatedJournalIds) {
				$this->q('UPDATE '.$this->table('accounting_bookkeeping').' SET date_validated='.$this->str($this->db->idate(dol_now())).',fk_user='.(int) $user->id.' WHERE entity='.$this->entity.' AND rowid IN ('.implode(',', array_map('intval', $validatedJournalIds)).')');
			}
			$this->q('UPDATE '.$this->table('ob_batch').' SET status=2,fk_user_post='.(int) $user->id.',date_posted='.$this->str($this->db->idate(dol_now())).' WHERE entity='.$this->entity.' AND rowid='.(int) $id.' AND status=1');
			return array('invoices' => $summary['ar_count'] + $summary['ap_count'], 'stock' => $summary['stock_count'], 'journals' => count($validatedJournalIds));
		});
	}

	private function finalizeStock($batch, $user, &$journalIds)
	{
		$rows = $this->stockLines($batch->rowid);
		if (!$rows) return;
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/perpetualinventory/class/perpetualinventory.class.php';
		$pi = new PerpetualInventory($this->db, $this->entity);
		$date = $this->dateTimestamp($batch->cutover_date);
		$config = $pi->config(true);
		$running = (int) $config->enabled !== 0 || (bool) $this->one('SELECT rowid FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' LIMIT 1');
		foreach ($rows as $line) {
			$native = $this->one('SELECT reel FROM '.$this->table('product_stock').' WHERE fk_product='.(int) $line->fk_product.' AND fk_entrepot='.(int) $line->fk_warehouse.' FOR UPDATE');
			$nativeQty = $native ? (float) $native->reel : 0.0;
			$addNative = $running || abs($nativeQty) <= 0.00000001;
			$entryId = $pi->backfillOpening((int) $line->fk_product, (int) $line->fk_warehouse, (float) $line->qty, (float) $line->unit_cost, $date, 'openingbalance:'.$batch->rowid.':'.$line->rowid, $batch->ref.' — Saldo awal', $batch->clearing_account, $user, $addNative);
			$this->link($batch->rowid, 'stock', $line->rowid, 'pi_entry', $entryId);
			$entry = $this->one('SELECT fk_movement FROM '.$this->table('pi_entry').' WHERE entity='.$this->entity.' AND rowid='.(int) $entryId);
			if ($entry && $entry->fk_movement) $this->link($batch->rowid, 'stock', $line->rowid, 'stock_movement', $entry->fk_movement);
			$amount = (float) price2num((float) $line->qty * (float) $line->unit_cost, 'MT');
			$ids = $this->postPiece($batch, 'openingbalance_stock', $batch->ref, $batch->rowid, $line->rowid, $batch->cutover_date, null, $batch->ref.' — Persediaan '.$line->product_ref.'/'.$line->warehouse_ref, array(array($batch->stock_account, $amount, 0), array($batch->clearing_account, 0, $amount)), $user);
			$journalIds = array_merge($journalIds, $ids);
			foreach ($ids as $journalId) $this->link($batch->rowid, 'stock', $line->rowid, 'bookkeeping', $journalId);
		}
		$pi->recalculateValuation($user, false);
	}

	/** Re-run valuation for a posted stock-opening batch without duplicating it. */
	public function recalculateStockValuation($id, $user)
	{
		if (!$user->admin || !$user->hasRight('openingbalance', 'post')) {
			throw new DomainException('Tidak memiliki izin hitung ulang valuasi stok.');
		}
		return $this->transaction(function () use ($id, $user) {
			$batch = $this->batch($id, true);
			if ((int) $batch->status !== self::STATUS_POSTED) {
				throw new DomainException('Hitung ulang hanya tersedia setelah batch diposting.');
			}
			if (!$this->one('SELECT rowid FROM '.$this->table('ob_posting_link').' WHERE entity='.$this->entity.' AND fk_batch='.(int) $id." AND object_type='pi_entry' LIMIT 1")) {
				throw new DomainException('Batch ini tidak memiliki saldo awal persediaan.');
			}
			require_once DOL_DOCUMENT_ROOT.'/custom/perpetualinventory/class/perpetualinventory.class.php';
			$pi = new PerpetualInventory($this->db, $this->entity);
			return $pi->recalculateValuation($user, false);
		});
	}

	private function finalizePartyLine($batch, $line, $kind, $user, &$journalIds)
	{
		global $conf;
		$isAr = $kind === 'ar';
		$party = $this->partyAccounting($line->fk_soc, $kind);
		$marker = $this->invoiceMarker($batch->rowid, $kind, $line->rowid);
		$forcedRef = 'OB-'.$batch->rowid.'-'.strtoupper($kind).'-'.$line->rowid;
		$invoiceDate = $this->dateTimestamp($line->invoice_date);
		$dueDate = $this->dateTimestamp($line->due_date);
		$description = 'Saldo awal '.($isAr ? 'piutang' : 'hutang').' '.$line->source_ref.' per '.$batch->cutover_date;
		$clearing = $this->account($batch->clearing_account);

		if ($isAr) {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$invoice = new Facture($this->db);
			$invoice->socid = (int) $line->fk_soc;
			$invoice->date = $invoiceDate;
			$invoice->type = Facture::TYPE_STANDARD;
			$invoice->ref_client = $line->source_ref;
			$invoice->ref_ext = $marker;
			$invoice->module_source = 'openingbalance';
			$invoice->note_private = '['.$batch->ref.'] Opening balance. Original '.price($line->original_amount).', paid before cutover '.price($line->prior_paid).'. '.$line->note;
			$invoice->multicurrency_code = $conf->currency;
			$invoice->multicurrency_tx = 1;
			$invoiceId = $invoice->create($user, 1, $dueDate);
			if ($invoiceId <= 0) throw new DomainException('Gagal membuat opening customer invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			$lineId = $invoice->addline($description, (float) $line->outstanding, 1, 0, 0, 0, 0, 0, '', '', (int) $clearing->rowid, 0, 0, 'HT', 0, 1);
			if ($lineId <= 0) throw new DomainException('Gagal membuat baris opening customer invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			if ($invoice->validate($user, $forcedRef, 0, 1) <= 0) throw new DomainException('Gagal memvalidasi opening customer invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			$control = $batch->receivable_account;
			$journalLines = array(array($control, (float) $line->outstanding, 0, true), array($batch->clearing_account, 0, (float) $line->outstanding, false));
			$docType = 'customer_invoice';
		} else {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
			$invoice = new FactureFournisseur($this->db);
			$invoice->socid = (int) $line->fk_soc;
			$invoice->date = $invoiceDate;
			$invoice->date_echeance = $dueDate;
			$invoice->type = FactureFournisseur::TYPE_STANDARD;
			$invoice->ref_supplier = $line->source_ref;
			$invoice->ref_ext = $marker;
			$invoice->label = $description;
			$invoice->note_private = '['.$batch->ref.'] Opening balance. Original '.price($line->original_amount).', paid before cutover '.price($line->prior_paid).'. '.$line->note;
			$invoice->multicurrency_code = $conf->currency;
			$invoice->multicurrency_tx = 1;
			$invoiceId = $invoice->create($user);
			if ($invoiceId <= 0) throw new DomainException('Gagal membuat opening supplier invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			$lineId = $invoice->addline($description, (float) $line->outstanding, 0, 0, 0, 1, 0, 0, 0, 0, (int) $clearing->rowid, 0, 'HT', 1);
			if ($lineId <= 0) throw new DomainException('Gagal membuat baris opening supplier invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			if ($invoice->validate($user, $forcedRef, 0, 1) <= 0) throw new DomainException('Gagal memvalidasi opening supplier invoice '.$line->source_ref.': '.$invoice->error.' '.implode('; ', $invoice->errors));
			$control = $batch->payable_account;
			$journalLines = array(array($batch->clearing_account, (float) $line->outstanding, 0, false), array($control, 0, (float) $line->outstanding, true));
			$docType = 'supplier_invoice';
		}

		$this->q('UPDATE '.$this->table('ob_party_line').' SET created_object_id='.(int) $invoiceId.' WHERE entity='.$this->entity.' AND rowid='.(int) $line->rowid.' AND fk_batch='.(int) $batch->rowid);
		$this->link($batch->rowid, $kind, $line->rowid, $docType, $invoiceId);
		$ids = $this->postPiece($batch, $docType, $forcedRef, $invoiceId, 0, $batch->cutover_date, $line->due_date, $batch->ref.' — '.($isAr ? 'Piutang ' : 'Hutang ').$line->source_ref, $journalLines, $user, $party);
		$journalIds = array_merge($journalIds, $ids);
		foreach ($ids as $journalId) $this->link($batch->rowid, $kind, $line->rowid, 'bookkeeping', $journalId);
	}

	private function postPiece($batch, $docType, $docRef, $fkDoc, $fkDocdet, $date, $dueDate, $label, $lines, $user, $party = null)
	{
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
		$ids = array();
		foreach ($lines as $line) {
			$account = $this->account($line[0]);
			$book = new BookKeeping($this->db);
			$book->doc_date = $this->dateTimestamp($date);
			$book->date_lim_reglement = $dueDate ? $this->dateTimestamp($dueDate) : null;
			$book->doc_type = $docType;
			$book->doc_ref = $docRef;
			$book->fk_doc = (int) $fkDoc;
			$book->fk_docdet = (int) $fkDocdet;
			$book->numero_compte = $account->account_number;
			$book->label_compte = $account->label;
			$book->label_operation = $label;
			$book->debit = (float) $line[1];
			$book->credit = (float) $line[2];
			$book->montant = max($book->debit, $book->credit);
			$book->sens = $book->debit > 0 ? 'D' : 'C';
			$book->code_journal = $batch->journal_code;
			$book->journal_label = 'Opening Balance';
			$book->fk_user_author = (int) $user->id;
			$book->entity = $this->entity;
			if (!empty($line[3]) && $party) {
				$book->thirdparty_code = $party->party_code;
				$book->subledger_account = $party->subledger_account;
				$book->subledger_label = $party->party_name;
			}
			// The batch already enforces its own invariants; suppress unrelated business triggers for these system journals.
			if ($book->create($user, 1) < 0) throw new DomainException('Gagal membuat jurnal '.$docRef.': '.$book->error.' '.implode('; ', $book->errors));
			$ids[] = (int) $book->id;
		}
		return $ids;
	}

	private function partyAccounting($partyId, $kind)
	{
		$party = $this->one('SELECT rowid,nom,code_client,code_fournisseur,code_compta,code_compta_fournisseur,accountancy_code_customer_general,accountancy_code_supplier_general FROM '.$this->table('societe').' WHERE entity='.$this->entity.' AND rowid='.(int) $partyId);
		if (!$party) return null;
		if ($kind === 'ar') {
			$party->general_account = trim((string) $party->accountancy_code_customer_general);
			if ($party->general_account === '' || $party->general_account === '-1') $party->general_account = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER');
			$party->party_code = trim((string) $party->code_client);
			$party->subledger_account = trim((string) $party->code_compta) ?: $party->general_account;
		} else {
			$party->general_account = trim((string) $party->accountancy_code_supplier_general);
			if ($party->general_account === '' || $party->general_account === '-1') $party->general_account = getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER');
			$party->party_code = trim((string) $party->code_fournisseur);
			$party->subledger_account = trim((string) $party->code_compta_fournisseur) ?: $party->general_account;
		}
		$party->party_name = $party->nom;
		return $party;
	}

	public function account($number)
	{
		$account = $this->one('SELECT rowid,account_number,label FROM '.$this->table('accounting_account').' WHERE entity='.$this->entity.' AND active=1 AND account_number='.$this->str($number).' AND fk_pcg_version IN (SELECT s.pcg_version FROM '.$this->table('accounting_system').' s WHERE s.rowid='.(int) getDolGlobalInt('CHARTOFACCOUNTS').')');
		if (!$account) throw new DomainException('Akun aktif tidak ditemukan: '.$number.'.');
		return $account;
	}

	private function link($batchId, $lineType, $lineId, $objectType, $objectId)
	{
		$this->q('INSERT INTO '.$this->table('ob_posting_link').' (entity,fk_batch,line_type,fk_line,object_type,object_id,datec) VALUES ('.$this->entity.','.(int) $batchId.','.$this->str($lineType).','.(int) $lineId.','.$this->str($objectType).','.(int) $objectId.','.$this->str($this->db->idate(dol_now())).')');
	}

	private function invoiceMarker($batchId, $kind, $lineId)
	{
		return 'OPENINGBALANCE:'.(int) $batchId.':'.$kind.':'.(int) $lineId;
	}

	private function dateTimestamp($date)
	{
		$parts = explode('-', substr((string) $date, 0, 10));
		$timestamp = dol_mktime(12, 0, 0, (int) $parts[1], (int) $parts[2], (int) $parts[0]);
		// A cutover dated today must not look like a future movement before noon.
		return min($timestamp, dol_now());
	}

	private function assertDraft($batch)
	{
		if ((int) $batch->status !== self::STATUS_DRAFT) {
			throw new DomainException('Batch tidak dapat diedit karena bukan Draft.');
		}
	}

	private function partyKind($kind)
	{
		if (!in_array($kind, array('ar', 'ap'), true)) {
			throw new DomainException('Jenis saldo pihak tidak valid.');
		}
	}

	private function validDate($date, $label)
	{
		$value = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
		if (!$value || $value->format('Y-m-d') !== $date) {
			throw new DomainException($label.' tidak valid.');
		}
	}

	private function number($value, $label)
	{
		if ($value === '' || !is_numeric($value) || !is_finite((float) $value)) {
			throw new DomainException($label.' harus berupa angka dengan titik sebagai desimal.');
		}
		return (float) $value;
	}

	private function accountExists($account)
	{
		try {
			return (bool) $this->account($account);
		} catch (DomainException $e) {
			return false;
		}
	}
}
