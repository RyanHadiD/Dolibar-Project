<?php

require_once __DIR__.'/lib/common.php';

$id = GETPOSTINT('id');
try {
	$batch = $ob->batch($id);
} catch (Throwable $e) {
	accessforbidden($e->getMessage());
}
$section = GETPOST('section', 'alpha') ?: 'summary';
$allowedSections = array('summary', 'stock', 'ar', 'ap', 'gl');
if (!in_array($section, $allowedSections, true)) {
	$section = 'summary';
}

$action = GETPOST('action', 'alpha');
if ($action === 'download_template' && $section !== 'summary') {
	$headers = array(
		'stock' => array('product_ref', 'warehouse_ref', 'qty', 'unit_cost', 'note'),
		'ar' => array('party_code', 'source_ref', 'invoice_date', 'due_date', 'original_amount', 'prior_paid', 'outstanding', 'currency', 'note'),
		'ap' => array('party_code', 'source_ref', 'invoice_date', 'due_date', 'original_amount', 'prior_paid', 'outstanding', 'currency', 'note'),
		'gl' => array('account_number', 'debit', 'credit', 'note'),
	);
	$exampleDate = trim((string) $batch->cutover_date);
	$exampleCurrency = !empty($conf->currency) ? strtoupper((string) $conf->currency) : 'IDR';
	$examples = array(
		'stock' => array(
			array('PROD-001', 'GUDANG-UTAMA', '10', '25000', 'Contoh saldo awal persediaan - ganti kode produk dan gudang'),
		),
		'ar' => array(
			array('CUST-001', 'INV-CUST-001', $exampleDate, $exampleDate, '1000000', '250000', '750000', $exampleCurrency, 'Contoh piutang - ganti kode customer dan referensi'),
		),
		'ap' => array(
			array('SUP-001', 'INV-SUP-001', $exampleDate, $exampleDate, '800000', '200000', '600000', $exampleCurrency, 'Contoh hutang - ganti kode supplier dan referensi'),
		),
		'gl' => array(
			array('110100', '500000', '', 'Contoh saldo debit - ganti nomor akun'),
			array('310100', '', '500000', 'Contoh saldo kredit - ganti nomor akun'),
		),
	);
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="opening-balance-'.$section.'-template.csv"');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, $headers[$section]);
	foreach ($examples[$section] as $example) {
		fputcsv($out, $example);
	}
	fclose($out);
	exit;
}
if ($action) {
	try {
		$message = 'Perubahan batch berhasil disimpan.';
		if ($action === 'save_header') {
			obPost('write');
			$ob->saveHeader($id, array(
				'label' => GETPOST('label', 'alphanohtml'), 'cutover_date' => GETPOST('cutover_date', 'alphanohtml'),
				'clearing_account' => GETPOST('clearing_account', 'alphanohtml'), 'stock_account' => GETPOST('stock_account', 'alphanohtml'),
				'receivable_account' => GETPOST('receivable_account', 'alphanohtml'), 'payable_account' => GETPOST('payable_account', 'alphanohtml'),
				'journal_code' => GETPOST('journal_code', 'alphanohtml'), 'note' => GETPOST('note', 'restricthtml'),
			));
		} elseif ($action === 'save_stock') {
			obPost('write');
			$ob->saveStockLines($id, obPostRows(array('product' => 'product', 'warehouse' => 'warehouse', 'qty' => 'qty', 'unit_cost' => 'unit_cost', 'note' => 'line_note')));
			$section = 'stock';
		} elseif ($action === 'save_ar' || $action === 'save_ap') {
			obPost('write');
			$kind = substr($action, 5);
			$ob->savePartyLines($id, $kind, obPostRows(array('party' => 'party', 'source_ref' => 'source_ref', 'invoice_date' => 'invoice_date', 'due_date' => 'due_date', 'original_amount' => 'original_amount', 'prior_paid' => 'prior_paid', 'outstanding' => 'outstanding', 'currency' => 'currency', 'note' => 'line_note')));
			$section = $kind;
		} elseif ($action === 'save_gl') {
			obPost('write');
			$ob->saveGlLines($id, obPostRows(array('account' => 'account', 'debit' => 'debit', 'credit' => 'credit', 'note' => 'line_note')));
			$section = 'gl';
		} elseif ($action === 'import_csv') {
			obPost('write');
			$section = GETPOST('csv_section', 'alpha');
			if (!in_array($section, array('stock', 'ar', 'ap', 'gl'), true)) {
				throw new DomainException('Jenis import CSV tidak valid.');
			}
			if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file']) || (int) $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
				throw new DomainException('Pilih file CSV yang valid untuk diunggah.');
			}
			if ((int) $_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
				throw new DomainException('File CSV terlalu besar. Maksimal 2 MB.');
			}
			$content = file_get_contents($_FILES['csv_file']['tmp_name']);
			if ($content === false) {
				throw new RuntimeException('File CSV tidak dapat dibaca.');
			}
			$count = $ob->importCsvSection($id, $section, $content);
			$message = $count.' baris berhasil diimpor. Data lama pada bagian ini telah diganti.';
		} elseif ($action === 'validate') {
			obPost('validate');
			$ob->validateBatch($id, $user);
			$section = 'summary';
		} elseif ($action === 'reopen') {
			obPost('validate');
			$ob->reopen($id);
			$section = 'summary';
		} elseif ($action === 'finalize') {
			obPost('post');
			if (!GETPOSTINT('confirm_finalize')) {
				throw new DomainException('Centang konfirmasi finalisasi terlebih dahulu.');
			}
			$result = $ob->finalizeBatch($id, $user);
			setEventMessages('Finalisasi berhasil: '.$result['invoices'].' invoice, '.$result['stock'].' saldo stok, dan '.$result['journals'].' baris jurnal dibuat.', null, 'mesgs');
			$section = 'summary';
		} elseif ($action === 'recalculate_stock') {
			obPost('post');
			$result = $ob->recalculateStockValuation($id, $user);
			$message = 'Hitung ulang selesai: '.$result['entries'].' entri direplay dan '.$result['journals'].' jurnal diperbarui.';
			$section = 'summary';
		}
		if ($action !== 'finalize') setEventMessages($message, null, 'mesgs');
		header('Location: '.obUrl('card.php?id='.$id.'&section='.$section));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

$batch = $ob->batch($id);
$summary = $ob->summary($id);
$editable = (int) $batch->status === OpeningBalance::STATUS_DRAFT && $user->hasRight('openingbalance', 'write');

obHeader($batch->ref.' — '.$batch->label);
print '<div class="ob-batch-head"><div><b>Status:</b> <span class="ob-status ob-status-'.(int) $batch->status.'">'.obEscape(obStatus($batch->status)).'</span></div><div><b>Cutover:</b> '.obEscape($batch->cutover_date).'</div><div><b>Selisih:</b> <span class="'.(abs($summary['difference']) <= 0.005 ? 'ob-ok' : 'ob-error').'">'.price($summary['difference']).'</span></div></div>';
print '<nav class="ob-tabs">';
foreach (array('summary' => 'Ringkasan', 'stock' => 'Persediaan', 'ar' => 'Piutang', 'ap' => 'Hutang', 'gl' => 'Saldo Akun') as $key => $label) {
	print '<a class="'.($section === $key ? 'active' : '').'" href="'.obEscape(obUrl('card.php?id='.$id.'&section='.$key)).'">'.obEscape($label).'</a>';
}
print '</nav>';

if ($section === 'summary') {
	obRenderSummary($ob, $batch, $summary, $editable, $user);
} elseif ($section === 'stock') {
	obRenderStock($ob, $batch, $editable);
} elseif ($section === 'ar' || $section === 'ap') {
	obRenderParty($ob, $batch, $editable, $section, $conf->currency);
} else {
	obRenderGl($ob, $batch, $editable);
}

obEnd();

function obRenderSummary($ob, $batch, $summary, $editable, $user)
{
	print '<div class="ob-cards">';
	foreach (array('Persediaan' => array($summary['stock'], $summary['stock_count']), 'Piutang' => array($summary['ar'], $summary['ar_count']), 'Hutang' => array($summary['ap'], $summary['ap_count']), 'Debit' => array($summary['debit'], ''), 'Kredit' => array($summary['credit'], '')) as $label => $value) {
		print '<div><small>'.obEscape($label).'</small><b>'.price($value[0]).'</b>'.($value[1] !== '' ? '<span>'.(int) $value[1].' baris</span>' : '').'</div>';
	}
	print '</div>';
	if ($editable) {
		print '<form method="post" class="ob-form ob-config">';
		obToken('save_header');
		print '<label>Nama batch<input name="label" required maxlength="255" value="'.obEscape($batch->label).'"></label>';
		print '<label>Tanggal cutover<input type="date" name="cutover_date" required value="'.obEscape($batch->cutover_date).'" max="'.date('Y-m-d', dol_now()).'"></label>';
		foreach (array('clearing_account' => 'Opening Balance Clearing', 'stock_account' => 'Akun Persediaan', 'receivable_account' => 'Akun Piutang Usaha', 'payable_account' => 'Akun Hutang Usaha') as $field => $label) {
			print '<label>'.obEscape($label).obSearchSelect($field, 'account', $batch->$field, obLookupLabel('account', $batch->$field)).'</label>';
		}
		print '<label>Jurnal Saldo Awal'.obSearchSelect('journal_code', 'journal', $batch->journal_code, obLookupLabel('journal', $batch->journal_code)).'</label>';
		print '<label class="ob-wide">Catatan<textarea name="note" rows="3">'.obEscape($batch->note).'</textarea></label>';
		print '<div class="ob-wide"><button class="button button-save" type="submit">Simpan Konfigurasi</button></div></form>';
	} else {
		print '<table class="noborder centpercent"><tr><td>Opening Balance Clearing</td><td>'.obEscape($batch->clearing_account).'</td></tr><tr><td>Persediaan</td><td>'.obEscape($batch->stock_account).'</td></tr><tr><td>Piutang</td><td>'.obEscape($batch->receivable_account).'</td></tr><tr><td>Hutang</td><td>'.obEscape($batch->payable_account).'</td></tr><tr><td>Jurnal</td><td>'.obEscape($batch->journal_code).'</td></tr></table>';
	}
	print '<div class="ob-reconcile"><h3>Preview keseimbangan</h3><table class="noborder centpercent"><thead><tr class="liste_titre"><th>Sumber</th><th class="right">Debit</th><th class="right">Kredit</th></tr></thead><tbody>';
	print '<tr><td>Persediaan</td><td class="right">'.price($summary['stock']).'</td><td></td></tr><tr><td>Piutang</td><td class="right">'.price($summary['ar']).'</td><td></td></tr><tr><td>Hutang</td><td></td><td class="right">'.price($summary['ap']).'</td></tr><tr><td>Saldo akun lainnya</td><td class="right">'.price($summary['gl_debit']).'</td><td class="right">'.price($summary['gl_credit']).'</td></tr><tr class="liste_total"><td>Total</td><td class="right">'.price($summary['debit']).'</td><td class="right">'.price($summary['credit']).'</td></tr></tbody></table>';
	print '<p class="'.(abs($summary['difference']) <= 0.005 ? 'ob-ok' : 'ob-error').'"><b>'.(abs($summary['difference']) <= 0.005 ? 'Batch seimbang.' : 'Batch belum seimbang sebesar '.price($summary['difference']).'.').'</b></p></div>';
	if ((int) $batch->status === OpeningBalance::STATUS_DRAFT && $user->hasRight('openingbalance', 'validate')) {
		print '<form method="post">'; obToken('validate'); print '<button class="button" type="submit">Validasi Batch</button></form>';
	} elseif ((int) $batch->status === OpeningBalance::STATUS_VALIDATED && ($user->hasRight('openingbalance', 'validate') || $user->hasRight('openingbalance', 'post'))) {
		$problems = $ob->postingProblems($batch->rowid);
		if ($problems) {
			print '<div class="ob-error-box"><b>Finalisasi belum dapat dilakukan:</b><ul>';
			foreach ($problems as $problem) print '<li>'.obEscape($problem).'</li>';
			print '</ul></div>';
		} elseif ($user->hasRight('openingbalance', 'post')) {
			print '<form method="post" class="ob-finalize">'; obToken('finalize');
			print '<label><input type="checkbox" name="confirm_finalize" value="1" required> Saya memahami bahwa finalisasi akan membuat invoice, stok, dan jurnal native serta tidak dapat diedit kembali.</label>';
			print '<button class="button button-save" type="submit" onclick="return confirm(\'Finalisasi dan posting batch saldo awal sekarang?\')">Finalisasi & Posting</button></form>';
		}
		if ($user->hasRight('openingbalance', 'validate')) {
			print '<form method="post" class="ob-reopen">'; obToken('reopen'); print '<button class="button" type="submit">Kembalikan ke Draft</button></form>';
		}
	} elseif ((int) $batch->status >= OpeningBalance::STATUS_POSTED) {
		print '<div class="ob-ok-box"><b>Batch telah diposting.</b> Invoice opening dapat dilunasi melalui pembayaran native Dolibarr. Jurnal telah divalidasi dan batch tidak dapat diedit.</div>';
		$links = $ob->rows('SELECT * FROM '.$ob->table('ob_posting_link').' WHERE entity='.$ob->entity.' AND fk_batch='.(int) $batch->rowid." AND object_type IN ('customer_invoice','supplier_invoice','pi_entry') ORDER BY line_type,fk_line,rowid");
		if ($links) {
			print '<table class="noborder centpercent"><thead><tr class="liste_titre"><th>Jenis</th><th>Baris</th><th>Dokumen hasil</th></tr></thead><tbody>';
			foreach ($links as $link) {
				if ($link->object_type === 'customer_invoice') $url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $link->object_id;
				elseif ($link->object_type === 'supplier_invoice') $url = DOL_URL_ROOT.'/fourn/facture/card.php?facid='.(int) $link->object_id;
				else $url = dol_buildpath('/perpetualinventory/ledger.php?id='.(int) $link->object_id, 1);
				print '<tr><td>'.obEscape(strtoupper($link->line_type)).'</td><td>'.(int) $link->fk_line.'</td><td><a href="'.obEscape($url).'">'.obEscape($link->object_type).' #'.(int) $link->object_id.'</a></td></tr>';
			}
			print '</tbody></table>';
		}
		if ($user->admin && $user->hasRight('openingbalance', 'post') && $ob->one('SELECT rowid FROM '.$ob->table('ob_posting_link').' WHERE entity='.$ob->entity.' AND fk_batch='.(int) $batch->rowid." AND object_type='pi_entry' LIMIT 1")) {
			print '<form method="post" class="ob-finalize">'; obToken('recalculate_stock');
			print '<button class="button" type="submit" onclick="return confirm(\'Hitung ulang seluruh moving-average dan jurnal stok dari awal?\')">Hitung Ulang Valuasi Stok</button></form>';
		}
	}
}

function obRenderStock($ob, $batch, $editable)
{
	$rows = $ob->stockLines($batch->rowid);
	print '<p class="ob-help">Masukkan posisi kuantitas pada tanggal cutover dan modal per unit. Jika transaksi sudah berjalan, nilai ini menjadi saldo historis tambahan sebelum transaksi berikutnya dan valuasi akan dihitung ulang saat finalisasi. Penyimpanan di tahap ini hanya Draft.</p>';
	obRenderCsvImport($batch, 'stock', $editable, 'product_ref,warehouse_ref,qty,unit_cost,note');
	obLineFormStart('save_stock', $editable, 'ob-stock-table');
	print '<thead><tr class="liste_titre"><th>Produk</th><th>Gudang</th><th>Qty saat cutover</th><th>Modal/unit</th><th>Total</th><th>Catatan</th><th></th></tr></thead><tbody>';
	foreach (array_merge($rows, $editable && !$rows ? array((object) array()) : array()) as $row) {
		obStockRow($row);
	}
	print '</tbody>'; obLineFormEnd($editable, 'Tambah Baris Persediaan');
}

function obStockRow($row)
{
	$product = $row->fk_product ?? '';
	$warehouse = $row->fk_warehouse ?? '';
	$qty = isset($row->qty) ? (float) $row->qty : '';
	$cost = isset($row->unit_cost) ? (float) $row->unit_cost : '';
	$productLabel = $product ? ($row->product_ref.' — '.$row->product_label) : '';
	$warehouseLabel = $warehouse ? $row->warehouse_ref : '';
	print '<tr><td>'.obSearchSelect('product[]', 'product', $product, $productLabel).'</td><td>'.obSearchSelect('warehouse[]', 'warehouse', $warehouse, $warehouseLabel).'</td><td><input class="ob-num ob-qty" type="number" min="0.00000001" step="0.00000001" name="qty[]" value="'.obEscape($qty).'"></td><td><input class="ob-num ob-cost" type="number" min="0" step="0.00000001" name="unit_cost[]" value="'.obEscape($cost).'"></td><td class="right ob-row-total">'.price(($qty === '' ? 0 : $qty) * ($cost === '' ? 0 : $cost)).'</td><td><input name="line_note[]" maxlength="255" value="'.obEscape($row->note ?? '').'"></td><td><button class="button ob-remove" type="button">×</button></td></tr>';
}

function obRenderParty($ob, $batch, $editable, $kind, $currency)
{
	$rows = $ob->partyLines($batch->rowid, $kind);
	$isAr = $kind === 'ar';
	print '<p class="ob-help">Satu baris adalah satu invoice lama yang masih outstanding. Nilai awal − sudah dibayar harus sama dengan outstanding. Dokumen native belum dibuat pada tahap Draft.</p>';
	obRenderCsvImport($batch, $kind, $editable, 'party_code,source_ref,invoice_date,due_date,original_amount,prior_paid,outstanding,currency,note');
	obLineFormStart('save_'.$kind, $editable, 'ob-party-table');
	print '<thead><tr class="liste_titre"><th>'.($isAr ? 'Customer' : 'Supplier').'</th><th>Referensi lama</th><th>Tanggal</th><th>Jatuh tempo</th><th>Nilai awal</th><th>Sudah dibayar</th><th>Outstanding</th><th>Currency</th><th>Catatan</th><th></th></tr></thead><tbody>';
	foreach (array_merge($rows, $editable && !$rows ? array((object) array()) : array()) as $row) {
		$code = $isAr ? ($row->code_client ?? '') : ($row->code_fournisseur ?? '');
		$partyLabel = isset($row->party_name) ? (($code ? $code.' — ' : '').$row->party_name) : '';
		print '<tr><td>'.obSearchSelect('party[]', $isAr ? 'customer' : 'supplier', $row->fk_soc ?? '', $partyLabel).'</td><td><input name="source_ref[]" maxlength="128" value="'.obEscape($row->source_ref ?? '').'"></td><td><input type="date" name="invoice_date[]" value="'.obEscape($row->invoice_date ?? '').'"></td><td><input type="date" name="due_date[]" value="'.obEscape($row->due_date ?? '').'"></td><td><input class="ob-num ob-original" type="number" min="0.01" step="0.01" name="original_amount[]" value="'.obEscape(isset($row->original_amount) ? (float) $row->original_amount : '').'"></td><td><input class="ob-num ob-paid" type="number" min="0" step="0.01" name="prior_paid[]" value="'.obEscape(isset($row->prior_paid) ? (float) $row->prior_paid : '0').'"></td><td><input class="ob-num ob-outstanding" type="number" min="0.01" step="0.01" name="outstanding[]" value="'.obEscape(isset($row->outstanding) ? (float) $row->outstanding : '').'"></td><td><input class="ob-currency" name="currency[]" maxlength="3" value="'.obEscape($row->currency ?? $currency).'"></td><td><input name="line_note[]" maxlength="255" value="'.obEscape($row->note ?? '').'"></td><td><button class="button ob-remove" type="button">×</button></td></tr>';
	}
	print '</tbody>'; obLineFormEnd($editable, 'Tambah Baris '.($isAr ? 'Piutang' : 'Hutang'));
}

function obRenderGl($ob, $batch, $editable)
{
	$rows = $ob->glLines($batch->rowid);
	print '<p class="ob-help">Isi saldo akun selain Persediaan, Piutang, Hutang, dan Opening Balance Clearing. Setiap baris hanya boleh memiliki Debit atau Kredit.</p>';
	obRenderCsvImport($batch, 'gl', $editable, 'account_number,debit,credit,note');
	obLineFormStart('save_gl', $editable, 'ob-gl-table');
	print '<thead><tr class="liste_titre"><th>Akun</th><th>Debit</th><th>Kredit</th><th>Catatan</th><th></th></tr></thead><tbody>';
	foreach (array_merge($rows, $editable && !$rows ? array((object) array()) : array()) as $row) {
		$account = $row->account_number ?? '';
		$accountLabel = $account ? $account.' — '.($row->account_label ?? '') : '';
		print '<tr><td>'.obSearchSelect('account[]', 'account', $account, $accountLabel).'</td><td><input class="ob-num" type="number" min="0" step="0.01" name="debit[]" value="'.obEscape(isset($row->debit) && (float) $row->debit ? (float) $row->debit : '').'"></td><td><input class="ob-num" type="number" min="0" step="0.01" name="credit[]" value="'.obEscape(isset($row->credit) && (float) $row->credit ? (float) $row->credit : '').'"></td><td><input name="line_note[]" maxlength="255" value="'.obEscape($row->note ?? '').'"></td><td><button class="button ob-remove" type="button">×</button></td></tr>';
	}
	print '</tbody>'; obLineFormEnd($editable, 'Tambah Baris Saldo Akun');
}

function obRenderCsvImport($batch, $section, $editable, $format)
{
	if (!$editable) return;
	$templateUrl = obUrl('card.php?id='.(int) $batch->rowid.'&section='.$section.'&action=download_template&token='.urlencode(newToken()));
	print '<details class="ob-csv-import"><summary>Import Massal dari CSV</summary>';
	print '<p>Format: <code>'.obEscape($format).'</code>. Template sudah berisi contoh pengisian. Ganti atau hapus baris contoh, lalu gunakan kode produk, gudang, pihak, dan akun yang terdaftar di sistem. Pemisah koma, titik koma, atau tab didukung; angka desimal memakai titik.</p>';
	print '<p class="ob-csv-warning"><b>Perhatian:</b> import yang berhasil akan mengganti semua baris pada bagian ini. Jika ada satu baris tidak valid, tidak ada data yang diubah.</p>';
	print '<form method="post" enctype="multipart/form-data">'; obToken('import_csv');
	print '<input type="hidden" name="csv_section" value="'.obEscape($section).'">';
	print '<input type="hidden" name="MAX_FILE_SIZE" value="2097152">';
	print '<input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required>';
	print '<a class="button" href="'.obEscape($templateUrl).'">Unduh Template CSV</a>';
	print '<button class="button button-save" type="submit" onclick="return confirm(\'Import CSV akan mengganti semua baris pada bagian ini. Lanjutkan?\')">Import &amp; Ganti Data</button>';
	print '</form></details>';
}

function obLineFormStart($action, $editable, $class)
{
	if ($editable) {
		print '<form method="post" class="ob-lines">'; obToken($action);
	}
	print '<div class="div-table-responsive"><table class="noborder centpercent '.$class.'">';
}

function obLineFormEnd($editable, $button)
{
	print '</table></div>';
	if ($editable) {
		print '<div class="ob-line-actions"><button class="button ob-add" type="button">'.obEscape($button).'</button><button class="button button-save" type="submit">Simpan Draft</button></div></form>';
		print '<script nonce="'.getNonce().'">jQuery(function($){var form=$("form.ob-lines");form.on("click",".ob-add",function(){form.find("select.ob-search.select2-hidden-accessible").select2("destroy");var row=form.find("tbody tr:last").clone(false,false);row.find("input").val("");row.find("select.ob-search").empty().val("").removeAttr("data-select2-id aria-hidden tabindex").removeClass("select2-hidden-accessible");row.find(".ob-paid").val("0");row.find(".ob-row-total").text("0");form.find("tbody").append(row);window.obInitSearch(form);});form.on("click",".ob-remove",function(){if(form.find("tbody tr").length>1){$(this).closest("tr").remove();}else{$(this).closest("tr").find("input").val("");$(this).closest("tr").find("select.ob-search").val(null).trigger("change");}});form.on("input",".ob-qty,.ob-cost",function(){var row=$(this).closest("tr"),qty=parseFloat(row.find(".ob-qty").val())||0,cost=parseFloat(row.find(".ob-cost").val())||0;row.find(".ob-row-total").text((qty*cost).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));});form.on("input",".ob-original,.ob-paid",function(){var row=$(this).closest("tr"),original=parseFloat(row.find(".ob-original").val())||0,paid=parseFloat(row.find(".ob-paid").val())||0;row.find(".ob-outstanding").val(Math.max(0,original-paid).toFixed(2));});});</script>';
	}
}
