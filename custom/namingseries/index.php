<?php

require_once __DIR__.'/lib/common.php';

$action = GETPOST('action', 'alpha');
if ($action === 'save') {
	nsPost('manage');
	try {
		$db->begin();
		$id = $ns->saveSeries(array(
			'rowid' => GETPOSTINT('rowid'), 'code' => strtoupper(GETPOST('code', 'alphanohtml')), 'label' => GETPOST('label', 'alphanohtml'),
			'document_type' => GETPOST('document_type', 'alpha'), 'pattern' => GETPOST('pattern', 'restricthtml'),
			'reset_period' => GETPOST('reset_period', 'alpha'), 'start_value' => GETPOSTINT('start_value'),
			'is_default' => GETPOST('is_default', 'alpha'), 'active' => GETPOST('active', 'alpha'),
		), $user->id);
		$db->commit();
		setEventMessages('Naming Series berhasil disimpan.', null, 'mesgs');
		header('Location: '.nsUrl('index.php?id='.$id)); exit;
	} catch (Throwable $e) { $db->rollback(); setEventMessages($e->getMessage(), null, 'errors'); }
}
if ($action === 'counter') {
	nsPost('counter');
	try {
		$db->begin();
		$ns->setCounter(GETPOSTINT('series_id'), GETPOST('scope_key', 'alphanohtml'), GETPOSTINT('current_value'), $user->id);
		$db->commit();
		setEventMessages('Current counter berhasil diperbarui.', null, 'mesgs');
		header('Location: '.nsUrl('index.php?id='.GETPOSTINT('series_id'))); exit;
	} catch (Throwable $e) { $db->rollback(); setEventMessages($e->getMessage(), null, 'errors'); }
}

$id = GETPOSTINT('id');
$edit = $id ? $ns->getSeries($id) : null;
$series = $ns->listSeries();
nsHeader('Naming Series');
print '<div class="ns-grid"><section class="ns-panel"><h2>Daftar Series</h2><table class="liste centpercent"><tr class="liste_titre"><th>Nama</th><th>Pattern</th><th>Reset</th><th>Default</th><th>Status</th><th>Preview</th></tr>';
foreach ($series as $row) {
	try { $preview = $ns->preview($row); } catch (Throwable $e) { $preview = $e->getMessage(); }
	print '<tr class="oddeven"><td><a href="'.nsUrl('index.php?id='.(int) $row->rowid).'">'.nsEscape($row->label).'</a><br><small>'.nsEscape($row->code).'</small></td><td><code>'.nsEscape($row->pattern).'</code></td><td>'.nsEscape($row->reset_period).'</td><td>'.($row->is_default ? 'Ya' : '—').'</td><td>'.($row->active ? 'Aktif' : 'Nonaktif').'</td><td><strong>'.nsEscape($preview).'</strong></td></tr>';
}
print '</table></section>';

$form = $edit ?: (object) array('rowid' => 0, 'code' => '', 'label' => '', 'document_type' => 'sales_order', 'pattern' => 'SO-{YYYY}-{MM}-{####}', 'reset_period' => 'monthly', 'start_value' => 1, 'is_default' => 1, 'active' => 1);
print '<section class="ns-panel"><h2>'.($edit ? 'Edit Series' : 'Series Baru').'</h2><form method="post" class="ns-form">'; nsToken('save');
print '<input type="hidden" name="rowid" value="'.(int) $form->rowid.'"><label>Kode</label><input required name="code" maxlength="64" value="'.nsEscape($form->code).'">';
print '<label>Nama</label><input required name="label" maxlength="128" value="'.nsEscape($form->label).'">';
print '<label>Document type</label><select name="document_type">';
foreach (NamingSeries::documentTypes() as $type => $definition) print '<option value="'.nsEscape($type).'"'.($form->document_type === $type ? ' selected' : '').'>'.nsEscape($definition['label']).'</option>';
print '</select>';
print '<label>Pattern</label><input required name="pattern" maxlength="255" value="'.nsEscape($form->pattern).'"><small>Token: {YYYY}, {YY}, {MM}, {DD}, {ENTITY}, {CUSTOMER_CODE}, {SUPPLIER_CODE}, {PRODUCT_REF}, {DOCUMENT_TYPE}, {USER}, dan counter {####}.</small>';
print '<label>Reset counter</label><select name="reset_period">';
foreach (array('daily' => 'Setiap hari', 'monthly' => 'Setiap bulan', 'yearly' => 'Setiap tahun', 'never' => 'Tidak pernah') as $key => $label) print '<option value="'.$key.'"'.($form->reset_period === $key ? ' selected' : '').'>'.$label.'</option>';
print '</select><label>Nomor awal</label><input type="number" min="1" name="start_value" value="'.(int) $form->start_value.'">';
print '<label><input type="checkbox" name="is_default" value="1"'.($form->is_default ? ' checked' : '').'> Jadikan default</label>';
print '<label><input type="checkbox" name="active" value="1"'.($form->active ? ' checked' : '').'> Aktif</label>';
print '<button class="button button-save" type="submit">Simpan</button></form>';
if ($edit) {
	print '<h3>Current Counter</h3><table class="liste centpercent"><tr class="liste_titre"><th>Scope</th><th>Current value</th></tr>';
	foreach ($ns->counters($edit->rowid) as $counter) print '<tr class="oddeven"><td>'.nsEscape($counter->scope_key).'</td><td>'.(int) $counter->current_value.'</td></tr>';
	print '</table><form method="post" class="ns-counter">'; nsToken('counter');
	print '<input type="hidden" name="series_id" value="'.(int) $edit->rowid.'"><input required name="scope_key" placeholder="2026-11"><input required type="number" min="0" name="current_value" placeholder="0"><button class="button" type="submit">Update counter</button></form>';
}
print '</section></div>';
nsEnd();
