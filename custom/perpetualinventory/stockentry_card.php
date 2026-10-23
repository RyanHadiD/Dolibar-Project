<?php
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/pistockentry.class.php';

$service = new PiStockEntry($pi);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$purposes = PiStockEntry::purposes();
list($products, $warehouses) = piMasters();
$accounts = $service->accounts();

function piStockEntryPostedNumber($value, $label, $required = true)
{
	$value = trim((string) $value);
	if ($value === '' && !$required) return null;
	if ($value === '' || !is_numeric($value) || !is_finite((float) $value)) {
		throw new DomainException('Isi '.$label.' dengan angka yang valid.');
	}
	return (float) $value;
}

function piStockEntrySelect($name, array $options, $value, $class = '')
{
	print '<select class="flat '.$class.'" name="'.$name.'[]"><option value="">— Pilih —</option>';
	foreach ($options as $key => $label) {
		print '<option value="'.piEscape($key).'"'.((string) $key === (string) $value ? ' selected' : '').'>'.piEscape($label).'</option>';
	}
	print '</select>';
}

function piStockEntryEditRow(array $line, array $products, array $warehouses, array $accounts)
{
	print '<tr class="pi-entry-row"><td class="pi-entry-seq"></td><td class="pi-field-product">';
	piStockEntrySelect('item_product', $products, $line['product'], 'pi-product-search');
	print '</td><td class="pi-field-warehouse">';
	piStockEntrySelect('item_warehouse', $warehouses, $line['warehouse']);
	print '</td><td class="pi-field-target">';
	piStockEntrySelect('item_target', $warehouses, $line['target']);
	print '</td><td><input class="flat pi-entry-number" name="item_qty[]" type="number" step="0.00000001" value="'.piEscape($line['qty']).'"></td>';
	print '<td class="pi-field-cost"><input class="flat pi-entry-number" name="item_cost[]" type="number" min="0" step="0.00000001" value="'.piEscape($line['cost']).'"></td><td class="pi-field-account">';
	piStockEntrySelect('item_account', $accounts, $line['account']);
	print '</td><td class="pi-field-source"><input class="flat pi-entry-source" name="item_source[]" type="number" min="1" step="1" value="'.piEscape($line['source']).'"></td>';
	print '<td><input class="flat" name="item_note[]" maxlength="255" value="'.piEscape($line['line_note']).'" placeholder="Catatan item"></td>';
	print '<td><button type="button" class="button pi-remove-row" title="Hapus item">×</button></td></tr>';
}

function piStockEntryPostedRows()
{
	$columns = array();
	foreach (array('product', 'warehouse', 'target', 'qty', 'cost', 'account', 'source', 'note') as $field) {
		$key = 'item_'.$field;
		$columns[$field] = isset($_POST[$key]) && is_array($_POST[$key]) ? array_values($_POST[$key]) : array();
	}
	$count = count($columns['qty']);
	if ($count < 1 || $count > 100) throw new DomainException('Stock Entry harus memiliki 1 sampai 100 item.');
	$rows = array();
	for ($i = 0; $i < $count; $i++) {
		$costRaw = trim((string) ($columns['cost'][$i] ?? ''));
		$rows[] = array(
			'product' => (int) ($columns['product'][$i] ?? 0),
			'warehouse' => (int) ($columns['warehouse'][$i] ?? 0),
			'target' => (int) ($columns['target'][$i] ?? 0),
			'qty' => piStockEntryPostedNumber($columns['qty'][$i] ?? '', 'Qty baris '.($i + 1)),
			'cost' => $costRaw === '' ? null : piStockEntryPostedNumber($costRaw, 'Basic Rate baris '.($i + 1)),
			'account' => trim((string) ($columns['account'][$i] ?? '')),
			'source' => (int) ($columns['source'][$i] ?? 0),
			'note' => trim((string) ($columns['note'][$i] ?? '')),
		);
	}
	return $rows;
}

if ($action === 'save') {
	piPost('write');
	try {
		$id = $service->saveDraft($id, GETPOST('purpose', 'alpha'), GETPOST('note', 'alphanohtml'), piStockEntryPostedRows(), $user, GETPOST('posting_date', 'alphanohtml'), GETPOST('posting_time', 'alphanohtml'));
		setEventMessages('Stock Entry Draft berhasil disimpan.', null, 'mesgs');
		header('Location: '.piUrl('stockentry_card.php?id='.$id));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
		$action = $id ? 'edit' : 'create';
	}
} elseif ($action === 'validate') {
	piPost('write');
	try {
		$pi->postStockEntry($id, $user);
		setEventMessages('Stock Entry berhasil divalidasi; stok dan jurnal telah diposting.', null, 'mesgs');
		header('Location: '.piUrl('stockentry_card.php?id='.$id));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
} elseif ($action === 'cancel') {
	piPost('write');
	try {
		$reason = trim(GETPOST('reason', 'restricthtml'));
		$pi->cancelStockEntry($id, $reason, $user);
		setEventMessages('Stock Entry berhasil dibatalkan; stok dan jurnal telah dibalik.', null, 'mesgs');
		header('Location: '.piUrl('stockentry_card.php?id='.$id));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
} elseif ($action === 'delete') {
	piPost('write');
	try {
		$deleted = $service->fetch($id);
		$service->deleteDraft($id, $user);
		setEventMessages('Stock Entry '.((int) $deleted->status === 2 ? 'Cancelled' : 'Draft').' berhasil dihapus.', null, 'mesgs');
		header('Location: '.piUrl('stockentry_list.php'));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

$document = $id ? $service->fetch($id) : null;
$editing = !$document || ($document->status == 0 && $action === 'edit');
$emptyLine = array('product' => '', 'warehouse' => '', 'target' => '', 'qty' => '', 'cost' => '', 'account' => '', 'source' => '', 'line_note' => '');

piHeader($document ? $document->ref : 'New Stock Entry');
print '<div class="pi-document-title"><div><a href="'.piEscape(piUrl('stockentry_list.php')).'">Stock Entry</a> / <strong>'.piEscape($document ? $document->ref : 'New').'</strong></div>';
if ($document) {
	$statusLabels = array(0 => 'Draft', 1 => 'Posted', 2 => 'Cancelled');
	print '<span class="pi-status pi-status-'.$document->status.'">'.piEscape($statusLabels[(int) $document->status] ?? 'Unknown').'</span>';
}
print '</div>';

if ($editing) {
	$purpose = GETPOST('purpose', 'alpha') ?: ($document ? $document->purpose : 'material_issue');
	$note = GETPOST('note', 'alphanohtml') ?: ($document ? $document->note : '');
	$postingDate = GETPOST('posting_date', 'alphanohtml') ?: ($document ? substr($document->date_post, 0, 10) : dol_print_date(dol_now(), '%Y-%m-%d'));
	$postingTime = GETPOST('posting_time', 'alphanohtml') ?: ($document ? substr($document->date_post, 11, 8) : dol_print_date(dol_now(), '%H:%M:%S'));
	$lines = array();
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['item_qty'])) {
		$count = count($_POST['item_qty']);
		for ($i = 0; $i < $count; $i++) {
			$lines[] = array('product' => $_POST['item_product'][$i] ?? '', 'warehouse' => $_POST['item_warehouse'][$i] ?? '', 'target' => $_POST['item_target'][$i] ?? '', 'qty' => $_POST['item_qty'][$i] ?? '', 'cost' => $_POST['item_cost'][$i] ?? '', 'account' => $_POST['item_account'][$i] ?? '', 'source' => $_POST['item_source'][$i] ?? '', 'line_note' => $_POST['item_note'][$i] ?? '');
		}
	} elseif ($document) {
		foreach ($document->lines as $line) $lines[] = array('product' => $line->fk_product, 'warehouse' => $line->fk_warehouse, 'target' => $line->fk_target_warehouse, 'qty' => $line->qty, 'cost' => $line->unit_cost, 'account' => $line->difference_account, 'source' => $line->fk_source_entry, 'line_note' => $line->note);
	} else $lines[] = $emptyLine;

	print '<form method="post" action="'.piEscape(piUrl('stockentry_card.php')).'" class="pi-form pi-entry-form" id="pi-stock-entry" novalidate>';
	piToken('save');
	if ($document) print '<input type="hidden" name="id" value="'.(int) $document->rowid.'">';
	print '<div class="pi-document-head"><label>Stock Entry Type';
	piSelect('purpose', $purposes, $purpose);
	print '</label><label for="posting_date">Posting Date<input class="flat" id="posting_date" name="posting_date" type="date" value="'.piEscape($postingDate).'" required></label><label for="posting_time">Posting Time<input class="flat" id="posting_time" name="posting_time" type="time" step="1" value="'.piEscape($postingTime).'" required></label></div>';
	print '<label for="note">Alasan / catatan dokumen</label><input class="minwidth500" id="note" name="note" maxlength="255" required value="'.piEscape($note).'">';
	print '<h3>Items</h3><div class="pi-scroll"><table class="noborder centpercent pi-entry-table"><thead><tr class="liste_titre"><th>No.</th><th>Item Code</th><th>Source Warehouse</th><th class="pi-field-target">Target Warehouse</th><th>Qty</th><th class="pi-field-cost">Basic Rate</th><th class="pi-field-account">Difference Account</th><th class="pi-field-source">ID Ledger Asal</th><th>Catatan</th><th></th></tr></thead><tbody id="pi-entry-items">';
	foreach ($lines as $line) piStockEntryEditRow($line, $products, $warehouses, $accounts);
	print '</tbody></table></div><button type="button" class="button" id="pi-add-row">+ Add Row</button><p class="pi-muted" id="pi-entry-hint"></p>';
	print '<div class="pi-form-feedback pi-error" id="pi-entry-error" role="alert" aria-live="assertive" hidden></div>';
	print '<div class="center"><button class="button button-save" id="pi-save-draft" type="submit">Save Draft</button> <a class="button button-cancel" href="'.piEscape($document ? piUrl('stockentry_card.php?id='.$document->rowid) : piUrl('stockentry_list.php')).'">Cancel</a><span class="pi-submit-status" id="pi-submit-status" aria-live="polite"></span></div></form>';
	print '<template id="pi-entry-template"><table><tbody>'; piStockEntryEditRow($emptyLine, $products, $warehouses, $accounts); print '</tbody></table></template>';
	print '<script nonce="'.getNonce().'">jQuery(function($){var form=$("#pi-stock-entry"),body=$("#pi-entry-items"),type=$("#purpose"),error=$("#pi-entry-error"),save=$("#pi-save-draft"),status=$("#pi-submit-status");function enhance(scope){scope.find("select.pi-product-search").each(function(){if(!$(this).hasClass("select2-hidden-accessible"))$(this).select2({width:"100%",placeholder:"Pilih item"});});}function renumber(){body.find(".pi-entry-seq").each(function(i){$(this).text(i+1);});}function fields(selector,active,required){body.find(selector).prop("disabled",!active).prop("required",!!active&&!!required);}function update(){var v=type.val(),transfer=v==="material_transfer",source=v==="return"||v==="reversal",account=["material_receipt","material_issue","count","adjustment"].indexOf(v)>=0,cost=v==="material_receipt"||v==="adjustment"||v==="count";$(".pi-field-target").toggle(transfer);$(".pi-field-source").toggle(source);$(".pi-field-product,.pi-field-warehouse").toggle(!source);$(".pi-field-account").toggle(account);$(".pi-field-cost").toggle(cost);fields("select[name=\"item_product[]\"]",!source,true);fields("select[name=\"item_warehouse[]\"]",!source,true);fields("select[name=\"item_target[]\"]",transfer,true);fields("select[name=\"item_account[]\"]",account,true);fields("input[name=\"item_source[]\"]",source,true);fields("input[name=\"item_cost[]\"]",cost,v==="material_receipt");fields("input[name=\"item_qty[]\"]",true,true);var h={material_receipt:"Barang masuk manual: Basic Rate dan Difference Account wajib.",material_issue:"Barang keluar: isi Qty positif; moving average menentukan nilai keluar.",material_transfer:"Pemindahan antargudang tidak membuat jurnal GL baru.",count:"Qty adalah jumlah fisik akhir; Difference Account menampung selisih.",adjustment:"Qty positif/negatif; nilai masuk membutuhkan Basic Rate.",return:"Isi ID Stock Ledger asal dan Qty positif.",reversal:"Isi ID Stock Ledger terakhir; seluruh transaksi asal akan dibalik."};$("#pi-entry-hint").text(h[v]||"");error.prop("hidden",true).text("");renumber();}function labelFor(field){var row=$(field).closest("tr").index()+1,name=field.name||"";if(name.indexOf("item_product")===0)return "Item Code baris "+row;if(name.indexOf("item_warehouse")===0)return "Source Warehouse baris "+row;if(name.indexOf("item_target")===0)return "Target Warehouse baris "+row;if(name.indexOf("item_qty")===0)return "Qty baris "+row;if(name.indexOf("item_cost")===0)return "Basic Rate baris "+row;if(name.indexOf("item_account")===0)return "Difference Account baris "+row;if(name.indexOf("item_source")===0)return "ID Ledger Asal baris "+row;if(field.id==="note")return "Alasan / catatan dokumen";if(field.id==="posting_date")return "Posting Date";if(field.id==="posting_time")return "Posting Time";return "kolom wajib";}function focusField(field){var el=$(field);if(el.hasClass("select2-hidden-accessible"))el.select2("open");else field.focus();}$("#pi-add-row").on("click",function(){var row=$($("#pi-entry-template").html()).find("tr");body.append(row);enhance(row);update();});body.on("click",".pi-remove-row",function(){if(body.find("tr").length>1){var row=$(this).closest("tr"),p=row.find("select.pi-product-search");if(p.hasClass("select2-hidden-accessible"))p.select2("destroy");row.remove();renumber();}});type.on("change",update);form.on("submit",function(event){update();var invalid=null;this.querySelectorAll("[required]:not([disabled])").forEach(function(field){if(!invalid&&!field.checkValidity())invalid=field;});if(invalid){event.preventDefault();error.text("Mohon lengkapi "+labelFor(invalid)+" sebelum menyimpan Draft.").prop("hidden",false);focusField(invalid);return;}save.prop("disabled",true).text("Saving...");status.text("Menyimpan draft, mohon tunggu...");});enhance(body);update();});</script>';
} else {
	$purposeLabel = $purposes[$document->purpose] ?? $document->purpose;
	print '<div class="pi-document-card"><div class="pi-document-head"><div><span>Stock Entry Type</span><strong>'.piEscape($purposeLabel).'</strong></div><div><span>Posting Date</span><strong>'.piEscape(substr($document->date_post, 0, 10)).'</strong></div><div><span>Posting Time</span><strong>'.piEscape(substr($document->date_post, 11, 8)).'</strong></div></div><div><span>Catatan</span><p>'.piEscape($document->note).'</p></div></div>';
	print '<h3>Items</h3><div class="pi-scroll"><table class="noborder centpercent pi-entry-view"><thead><tr class="liste_titre"><th>No.</th><th>Source Warehouse</th><th>Target Warehouse</th><th>Item Code</th><th class="right">Qty</th><th class="right">Valuation Rate</th><th class="right">Amount</th><th>Accounting</th></tr></thead><tbody>';
	foreach ($document->lines as $index => $line) {
		$rate = $line->posted_rate !== null ? $line->posted_rate : $line->unit_cost;
		$amount = $line->posted_amount !== null ? abs($line->posted_amount) : abs($line->qty * $line->unit_cost);
		print '<tr class="oddeven"><td>'.($index + 1).'</td><td>'.piEscape($line->source_warehouse_ref ?: '—').'</td><td>'.piEscape($line->target_warehouse_ref ?: '—').'</td><td><strong>'.piEscape($line->product_ref).'</strong><br><span class="pi-muted">'.piEscape($line->product_label).'</span></td><td class="right">'.piQty($line->qty).'</td><td class="right">'.piMoney($rate).'</td><td class="right">'.piMoney($amount).'</td><td>'.piEscape($line->difference_account ? $line->difference_account.' — '.$line->difference_account_label : 'No GL / akun transaksi asal').'</td></tr>';
		$ledgerLabel = $line->fk_pi_entry ? '<a href="'.piEscape(piUrl('ledger.php')).'">PI-'.(int) $line->fk_pi_entry.'</a>' : ((int) $document->status === 2 ? 'Dibatalkan' : 'Belum diposting');
		print '<tr class="pi-item-detail"><td></td><td colspan="7"><details><summary>Detail item & accounting</summary><div class="pi-item-detail-grid"><div><span>Item Name</span><strong>'.piEscape($line->product_label).'</strong></div><div><span>Quantity</span><strong>'.piQty($line->qty).'</strong></div><div><span>Basic Rate</span><strong>'.piMoney($rate).'</strong></div><div><span>Amount</span><strong>'.piMoney($amount).'</strong></div><div><span>Difference Account</span><strong>'.piEscape($line->difference_account ?: '—').'</strong></div><div><span>Stock Ledger</span><strong>'.$ledgerLabel.'</strong></div></div></details></td></tr>';
	}
	print '</tbody></table></div>';
	if ($user->hasRight('perpetualinventory', 'write')) {
		print '<div class="center pi-document-actions">';
		if ((int) $document->status === 0) {
			print '<a class="button" href="'.piEscape(piUrl('stockentry_card.php?id='.$document->rowid.'&action=edit')).'">Edit</a> ';
			print '<form method="post" class="inline-block">'; piToken('validate'); print '<input type="hidden" name="id" value="'.(int) $document->rowid.'"><button class="button button-save" type="submit">Validate & Post</button></form> ';
			print '<form method="post" class="inline-block" onsubmit="return confirm(\'Hapus Stock Entry Draft ini?\')">'; piToken('delete'); print '<input type="hidden" name="id" value="'.(int) $document->rowid.'"><button class="button button-delete" type="submit">Delete</button></form>';
		} elseif ((int) $document->status === 1) {
			print '<form method="post" class="pi-cancel-form" onsubmit="return confirm(\'Batalkan Stock Entry ini dan balik stok serta jurnalnya?\')">';
			piToken('cancel');
			print '<input type="hidden" name="id" value="'.(int) $document->rowid.'"><label for="cancel_reason">Alasan pembatalan</label><input class="minwidth300" id="cancel_reason" name="reason" minlength="5" maxlength="1000" required placeholder="Minimal 5 karakter"><button class="button button-delete" type="submit">Cancel Stock Entry</button></form>';
		} elseif ((int) $document->status === 2) {
			print '<p class="pi-muted">Stok dan jurnal sudah dibatalkan. Dokumen dapat dihapus; snapshot dan alasan pembatalan tetap tersimpan pada audit trail.</p>';
			print '<a class="button" href="'.piEscape(piUrl('audit.php?type=stock_entry&document_id='.$document->rowid)).'">Riwayat Cancel</a> ';
			print '<form method="post" class="inline-block" onsubmit="return confirm(\'Hapus Stock Entry Cancelled ini?\')">'; piToken('delete'); print '<input type="hidden" name="id" value="'.(int) $document->rowid.'"><button class="button button-delete" type="submit">Delete</button></form>';
		}
		print '</div>';
	}
}
piEnd();
