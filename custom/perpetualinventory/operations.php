<?php
require_once __DIR__.'/lib/common.php';
if (!$user->hasRight('perpetualinventory', 'write')) {
	accessforbidden();
}

list($products, $warehouses) = piMasters();
$postedRows = array();
$kind = GETPOST('kind', 'alpha');
$note = GETPOST('note', 'alphanohtml');
$requestKey = GETPOST('request_key', 'alphanohtml') ?: bin2hex(random_bytes(16));

function piOperationNumber($value, $label, $required = true)
{
	$value = trim((string) $value);
	if ($value === '' && !$required) {
		return null;
	}
	if ($value === '' || !is_numeric($value) || !is_finite((float) $value)) {
		throw new DomainException('Isi '.$label.' menggunakan angka dengan titik untuk desimal.');
	}
	return (float) $value;
}

function piOperationSelect($name, array $options, $value, $class = '')
{
	print '<select class="flat '.$class.'" name="'.$name.'[]"><option value="">— Pilih —</option>';
	foreach ($options as $id => $label) {
		print '<option value="'.piEscape($id).'"'.((string) $id === (string) $value ? ' selected' : '').'>'.piEscape($label).'</option>';
	}
	print '</select>';
}

function piOperationRow(array $row, array $products, array $warehouses)
{
	print '<tr class="pi-entry-row">';
	print '<td class="pi-field-product">';
	piOperationSelect('item_product', $products, $row['product'], 'pi-product-search');
	print '</td><td class="pi-field-warehouse">';
	piOperationSelect('item_warehouse', $warehouses, $row['warehouse']);
	print '</td><td class="pi-field-target">';
	piOperationSelect('item_target', $warehouses, $row['target']);
	print '</td><td><input class="flat pi-entry-number" name="item_qty[]" type="number" step="0.00000001" value="'.piEscape($row['qty']).'"></td>';
	print '<td class="pi-field-cost"><input class="flat pi-entry-number" name="item_cost[]" type="number" min="0" step="0.00000001" value="'.piEscape($row['cost']).'"></td>';
	print '<td class="pi-field-source"><input class="flat pi-entry-source" name="item_source[]" type="number" min="1" step="1" value="'.piEscape($row['source']).'"></td>';
	print '<td><button type="button" class="button pi-remove-row" title="Hapus baris">×</button></td></tr>';
}

if (GETPOST('action', 'aZ09') === 'post') {
	piPost('write');
	try {
		$columns = array();
		foreach (array('product', 'warehouse', 'target', 'qty', 'cost', 'source') as $field) {
			$key = 'item_'.$field;
			$columns[$field] = isset($_POST[$key]) && is_array($_POST[$key]) ? array_values($_POST[$key]) : array();
		}
		$count = count($columns['qty']);
		if ($count < 1 || $count > 100) {
			throw new DomainException('Stock Entry harus memiliki 1 sampai 100 baris item.');
		}
		$items = array();
		for ($i = 0; $i < $count; $i++) {
			$row = array(
				'product' => (int) ($columns['product'][$i] ?? 0),
				'warehouse' => (int) ($columns['warehouse'][$i] ?? 0),
				'target' => (int) ($columns['target'][$i] ?? 0),
				'qty' => trim((string) ($columns['qty'][$i] ?? '')),
				'cost' => trim((string) ($columns['cost'][$i] ?? '')),
				'source' => (int) ($columns['source'][$i] ?? 0),
			);
			$postedRows[] = $row;
			$items[] = array(
				'product' => $row['product'],
				'warehouse' => $row['warehouse'],
				'target' => $row['target'],
				'qty' => piOperationNumber($row['qty'], 'jumlah pada baris '.($i + 1)),
				'cost' => piOperationNumber($row['cost'], 'modal pada baris '.($i + 1), false),
				'source' => $row['source'],
			);
		}
		$ids = $pi->operateBatch($kind, $items, $note, $requestKey, $user);
		setEventMessages(count($ids).' baris Stock Entry berhasil diposting dalam satu transaksi.', null, 'mesgs');
		header('Location: '.piUrl('ledger.php'));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

$emptyRow = array('product' => '', 'warehouse' => '', 'target' => '', 'qty' => '', 'cost' => '', 'source' => '');
if (!$postedRows) {
	$postedRows[] = $emptyRow;
}
$openingAllowed = !$pi->one('SELECT rowid FROM '.$pi->table('pi_entry').' WHERE entity='.$pi->entity." AND kind<>'opening' LIMIT 1");
$operationOptions = array(
	'material_receipt' => 'Material Receipt — barang masuk manual',
	'material_issue' => 'Material Issue — barang keluar manual',
	'material_transfer' => 'Material Transfer — pindah gudang',
	'count' => 'Stock Reconciliation — isi jumlah fisik akhir',
	'adjustment' => 'Penyesuaian selisih stok (+/-)',
	'return' => 'Retur terhadap transaksi asal',
	'reversal' => 'Pembalik seluruh transaksi terakhir',
);
if ($openingAllowed) {
	$operationOptions = array('opening' => 'Opening Stock — saldo awal') + $operationOptions;
}
if (!$kind || !isset($operationOptions[$kind])) {
	$kind = 'material_transfer';
}

piHeader('Stock Entry / Operasi Stok');
print '<div class="pi-help"><b>Alur mengikuti konsep Stock Entry ERPNext:</b> satu dokumen dapat memuat banyak item, gudang dapat berbeda per baris, dan semua baris diposting atomik. Material Receipt/Issue manual memakai akun Selisih Stok. Pembelian dan penjualan normal tetap melalui Reception/Shipment agar GRNI dan HPP tercatat benar.<br><b>Saldo awal:</b> '.($openingAllowed ? 'masih terbuka' : 'sudah ditutup karena ada transaksi operasional').'. Produk lot/serial tetap diproses melalui dokumen native Dolibarr.</div>';
print '<form method="post" class="pi-form pi-entry-form" id="pi-stock-entry">';
piToken('post');
print '<input type="hidden" name="request_key" value="'.piEscape($requestKey).'">';
print '<label for="kind">Stock Entry Type / Tujuan</label>';
piSelect('kind', $operationOptions, $kind);
print '<div class="pi-entry-defaults"><label>Default gudang asal';
piOperationSelect('default_source_unused', $warehouses, '');
print '</label><button type="button" class="button" id="pi-apply-source">Terapkan ke semua baris</button><label>Default gudang tujuan';
piOperationSelect('default_target_unused', $warehouses, '');
print '</label><button type="button" class="button" id="pi-apply-target">Terapkan ke semua baris</button></div>';
print '<div class="pi-scroll"><table class="noborder centpercent pi-entry-table"><thead><tr class="liste_titre"><th>Barang</th><th>Gudang asal/aktif</th><th class="pi-field-target">Gudang tujuan</th><th>Jumlah</th><th class="pi-field-cost">Modal/unit</th><th class="pi-field-source">ID ledger asal</th><th></th></tr></thead><tbody id="pi-entry-items">';
foreach ($postedRows as $row) {
	piOperationRow($row, $products, $warehouses);
}
print '</tbody></table></div>';
print '<button type="button" class="button" id="pi-add-row">+ Tambah item</button><p class="pi-muted" id="pi-entry-hint"></p>';
print '<label for="note">Alasan / catatan dokumen</label><input class="minwidth300" id="note" name="note" maxlength="255" required value="'.piEscape($note).'">';
print '<p>Jika satu baris gagal divalidasi, seluruh Stock Entry dibatalkan. Maksimal 100 baris.</p><button class="button button-save" type="submit">Submit Stock Entry</button></form>';
print '<template id="pi-entry-template"><table><tbody>';
piOperationRow($emptyRow, $products, $warehouses);
print '</tbody></table></template>';
print '<script nonce="'.getNonce().'">jQuery(function($){var body=$("#pi-entry-items"),kind=$("#kind");function enhance(scope){scope.find("select.pi-product-search").each(function(){var field=$(this);if(!field.hasClass("select2-hidden-accessible")){field.select2({width:"100%",minimumResultsForSearch:0,placeholder:"Cari barang"});}});}function update(){var value=kind.val(),transfer=value==="material_transfer",source=value==="return"||value==="reversal",cost=value==="material_receipt"||value==="opening"||value==="adjustment";$(".pi-field-target").toggle(transfer);$(".pi-field-source").toggle(source);$(".pi-field-product,.pi-field-warehouse").toggle(!source);$(".pi-field-cost").toggle(cost);body.find("select[name=\"item_product[]\"],select[name=\"item_warehouse[]\"]").prop("required",!source);body.find("input[name=\"item_qty[]\"]").prop("required",true);body.find("select[name=\"item_target[]\"]").prop("required",transfer);body.find("input[name=\"item_source[]\"]").prop("required",source);body.find("input[name=\"item_cost[]\"]").prop("required",value==="material_receipt"||value==="opening");var hints={material_receipt:"Jumlah positif dan modal per unit wajib. Untuk pembelian normal gunakan Reception.",material_issue:"Masukkan jumlah positif; sistem membukukan sebagai stok keluar dengan moving average.",material_transfer:"Gudang asal dan tujuan harus berbeda. Nilai persediaan dibawa tanpa jurnal GL baru.",count:"Jumlah adalah saldo fisik akhir, termasuk nol. Modal dibutuhkan hanya jika hasilnya menambah stok.",adjustment:"Jumlah positif menambah stok dan membutuhkan modal; jumlah negatif mengurangi stok.",opening:"Jumlah positif dan modal per unit wajib.",return:"Isi ID ledger asal dan jumlah positif.",reversal:"Isi ID ledger asal; jumlah dapat diisi 1 karena sistem membalik seluruh transaksi."};$("#pi-entry-hint").text(hints[value]||"");}$("#pi-add-row").on("click",function(){var row=$($("#pi-entry-template").html()).find("tr");body.append(row);enhance(row);update();});body.on("click",".pi-remove-row",function(){if(body.find("tr").length>1){var row=$(this).closest("tr"),product=row.find("select.pi-product-search");if(product.hasClass("select2-hidden-accessible")){product.select2("destroy");}row.remove();}});$("#pi-apply-source").on("click",function(){body.find("select[name=\"item_warehouse[]\"]").val($("select[name=\"default_source_unused[]\"]").val()).trigger("change");});$("#pi-apply-target").on("click",function(){body.find("select[name=\"item_target[]\"]").val($("select[name=\"default_target_unused[]\"]").val()).trigger("change");});kind.on("change",update);enhance(body);update();});</script>';
piEnd();
