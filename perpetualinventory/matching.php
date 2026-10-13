<?php
require_once __DIR__.'/lib/common.php';
/* Actions */
$action = GETPOST('action', 'aZ09');
if ($action) {
	piPost('match');
	try {
		if ($action === 'match') {
			$pi->matchInvoice(GETPOSTINT('entry'), GETPOSTINT('line'), piNumber('qty'), $user);
		} elseif ($action === 'unmatch') {
			$pi->unmatch(GETPOSTINT('id'), $user);
		} elseif ($action === 'repair_account') {
			$pi->repairInvoiceAccount(GETPOSTINT('invoice'), $user);
		}
		setEventMessages('Pencocokan berhasil diperbarui.', null, 'mesgs');
		header('Location: '.piUrl('matching.php'));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}
/* Views */
piHeader('Penerimaan dan pencocokan tagihan supplier');
print '<div class="pi-help"><strong>Alur normal otomatis:</strong> saat Vendor Invoice divalidasi, sistem mencari Reception dari supplier, barang, harga, tanggal, dan sisa kuantitas yang sesuai. Dokumen yang terhubung diprioritaskan, lalu penerimaan tertua.<br>Gunakan formulir manual di bawah hanya untuk invoice parsial atau dokumen lama/tidak terhubung yang tidak dapat dicocokkan otomatis. Setelah validasi, transfer tagihan melalui <a href="'.piEscape(DOL_URL_ROOT.'/accountancy/journal/purchasesjournal.php').'">Jurnal Pembelian Dolibarr</a> untuk mencatat utang dan menyelesaikan akun Barang Diterima Belum Ditagih.</div>';
$receipts = $pi->rows('SELECT e.*,p.ref product_ref,r.ref receipt_ref,s.nom supplier, COALESCE((SELECT SUM(m.qty) FROM '.$pi->table('pi_match').' m WHERE m.entity=e.entity AND m.fk_entry=e.rowid),0) matched_qty, COALESCE((SELECT SUM(ABS(x.qty)) FROM '.$pi->table('pi_entry').' x WHERE x.entity=e.entity AND x.fk_source=e.rowid),0) returned_qty FROM '.$pi->table('pi_entry').' e JOIN '.$pi->table('product').' p ON p.rowid=e.fk_product JOIN '.$pi->table('reception').' r ON r.rowid=e.origin_id AND r.entity=e.entity JOIN '.$pi->table('societe').' s ON s.rowid=r.fk_soc WHERE e.entity='.$pi->entity." AND e.kind='receipt' ORDER BY e.rowid DESC");
$receiptOptions = array();
print '<div class="pi-scroll"><table class="noborder centpercent"><tr class="liste_titre"><th>Penerimaan</th><th>Supplier / barang</th><th class="right">Diterima</th><th class="right">Retur</th><th class="right">Dicocokkan</th><th class="right">Sisa</th><th class="right">Nilai sisa</th></tr>';
foreach ($receipts as $e) {
	$remaining = $e->qty - $e->matched_qty - $e->returned_qty;
	if ($remaining > 0.00000001) {
		$receiptOptions[$e->rowid] = 'PI-'.$e->rowid.' / '.$e->receipt_ref.' / '.$e->supplier.' / '.$e->product_ref.' / sisa '.(float) $remaining;
	}
	print '<tr class="oddeven"><td><a href="'.piEscape(piUrl('ledger.php?id='.$e->rowid)).'">PI-'.(int) $e->rowid.' / '.piEscape($e->receipt_ref).'</a></td><td>'.piEscape($e->supplier.' / '.$e->product_ref).'</td><td class="right">'.piMoney($e->qty).'</td><td class="right">'.piMoney($e->returned_qty).'</td><td class="right">'.piMoney($e->matched_qty).'</td><td class="right">'.piMoney($remaining).'</td><td class="right">'.piMoney($remaining * $e->unit_cost).'</td></tr>';
}
print '</table></div>';
if ($user->hasRight('perpetualinventory', 'match')) {
	$lineOptions = array();
	foreach ($pi->rows('SELECT d.rowid,d.qty,f.ref,s.nom,p.ref product_ref FROM '.$pi->table('facture_fourn_det').' d JOIN '.$pi->table('facture_fourn').' f ON f.rowid=d.fk_facture_fourn JOIN '.$pi->table('societe').' s ON s.rowid=f.fk_soc JOIN '.$pi->table('product').' p ON p.rowid=d.fk_product WHERE f.entity='.$pi->entity.' AND f.fk_statut=0 AND f.type=0 AND p.fk_product_type=0 AND p.stockable_product=1 ORDER BY f.rowid DESC,d.rang') as $l) {
		$lineOptions[$l->rowid] = $l->ref.' / '.$l->nom.' / '.$l->product_ref.' / baris #'.$l->rowid.' / qty '.(float) $l->qty;
	}
	print '<form method="post" class="pi-form">';
	piToken('match');
	print '<label for="entry">Penerimaan</label>';
	piSelect('entry', $receiptOptions);
	print '<label for="line">Baris tagihan draft</label>';
	piSelect('line', $lineOptions);
	print '<label for="qty">Jumlah yang dicocokkan</label><input id="qty" name="qty" type="number" min="0.00000001" step="0.00000001" required><br><button class="button" type="submit">Cocokkan</button></form>';
}
print '<h3>Pencocokan terakhir</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Penerimaan</th><th>Tagihan / baris</th><th class="right">Jumlah</th><th class="right">Nilai</th><th>Akun baris</th><th>Status</th><th>Aksi</th></tr>';
$grniAccount = $pi->account($pi->config()->grni_account);
foreach ($pi->rows('SELECT m.*,f.ref,f.fk_statut,d.fk_code_ventilation,a.account_number line_account,(SELECT COUNT(*) FROM '.$pi->table('accounting_bookkeeping')." j WHERE j.entity=m.entity AND j.doc_type='supplier_invoice' AND j.fk_doc=m.fk_invoice) journal_lines FROM ".$pi->table('pi_match').' m JOIN '.$pi->table('facture_fourn').' f ON f.rowid=m.fk_invoice AND f.entity=m.entity JOIN '.$pi->table('facture_fourn_det').' d ON d.rowid=m.fk_invoice_line AND d.fk_facture_fourn=m.fk_invoice LEFT JOIN '.$pi->table('accounting_account').' a ON a.rowid=d.fk_code_ventilation WHERE m.entity='.$pi->entity.' ORDER BY m.rowid DESC LIMIT 100') as $m) {
	$wrongAccount = $m->fk_code_ventilation != $grniAccount->rowid;
	print '<tr class="oddeven"><td>PI-'.(int) $m->fk_entry.'</td><td><a href="'.piEscape(DOL_URL_ROOT.'/fourn/facture/card.php?facid='.(int) $m->fk_invoice).'">'.piEscape($m->ref).'</a> / #'.(int) $m->fk_invoice_line.'</td><td class="right">'.piMoney($m->qty).'</td><td class="right">'.piMoney($m->amount).'</td><td class="'.($wrongAccount ? 'pi-error' : 'pi-ok').'">'.piEscape($m->line_account ?: 'Belum dialokasikan').'</td><td>'.($m->journal_lines ? 'Sudah dijurnal' : ($m->fk_statut ? ($wrongAccount ? 'Akun berubah — perbaiki sebelum transfer' : 'Siap ditransfer ke Jurnal Pembelian') : 'Draft')).'</td><td>';
	if (!$m->fk_statut && $user->hasRight('perpetualinventory', 'match')) {
		print '<form method="post">';
		piToken('unmatch');
		print '<input type="hidden" name="id" value="'.(int) $m->rowid.'"><button type="submit" class="button small">Lepas</button></form>';
	} elseif ($m->fk_statut && !$m->journal_lines && $wrongAccount && $user->hasRight('perpetualinventory', 'match')) {
		print '<form method="post">';
		piToken('repair_account');
		print '<input type="hidden" name="invoice" value="'.(int) $m->fk_invoice.'"><button type="submit" class="button small">Kembalikan ke GRNI</button></form>';
	}
	print '</td></tr>';
}
print '</table><p class="pi-muted">Sisa penerimaan di atas berdasarkan pencocokan dokumen. Setelah akun baris GRNI, transfer Vendor Invoice melalui Jurnal Pembelian Dolibarr. Baris Debit GRNI pada invoice menutup Kredit GRNI dari Reception; akun tetap terlihat pada masing-masing voucher, tetapi saldo kumulatifnya menjadi nol.</p>';
piEnd();
