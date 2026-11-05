<?php

require_once __DIR__.'/lib/common.php';

$action = GETPOST('action', 'alpha');
$filterType = GETPOST('document_type', 'alpha');
$filterStatus = GETPOST('status', 'alpha');

if ($action === 'updatecounter') {
	nsPost('counter');
	try {
		$seriesId = GETPOSTINT('series_id');
		$scope = GETPOST('scope_key', 'alphanohtml');
		$value = GETPOSTINT('current_value');
		$series = $ns->getSeries($seriesId);
		if (!$series) throw new Exception('Naming Series tidak ditemukan.');
		$old = $ns->currentValue($seriesId, $scope);
		$db->begin();
		$ns->setCounter($seriesId, $scope, $value, $user->id);
		$db->commit();
		setEventMessages('Counter '.$series->code.' untuk scope '.$scope.' diperbarui dari '.$old.' menjadi '.$value.'. Nomor berikutnya akan dihitung dari nilai tersebut.', null, 'mesgs');
		header('Location: '.nsUrl('counters.php?document_type='.urlencode($filterType).'&status='.urlencode($filterStatus))); exit;
	} catch (Throwable $e) {
		$db->rollback();
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

$catalog = NamingSeries::documentTypes();
$seriesList = $ns->listSeries($filterType);
nsHeader('Current Counter');

print '<div class="ns-panel"><div class="ns-counter-head"><div><h2>Current Counter</h2><p>Current value adalah nomor terakhir yang telah dialokasikan. Contoh: current <strong>2</strong> menghasilkan nomor berikutnya <strong>0003</strong>.</p></div>';
print '<form method="get" class="ns-filter"><select name="document_type"><option value="">Semua document type</option>';
foreach ($catalog as $type => $definition) print '<option value="'.nsEscape($type).'"'.($filterType === $type ? ' selected' : '').'>'.nsEscape($definition['label']).'</option>';
print '</select><select name="status"><option value="">Semua status</option><option value="active"'.($filterStatus === 'active' ? ' selected' : '').'>Aktif</option><option value="inactive"'.($filterStatus === 'inactive' ? ' selected' : '').'>Nonaktif</option></select><button class="button" type="submit">Filter</button></form></div></div>';

print '<div class="div-table-responsive"><table class="liste centpercent ns-counter-table"><tr class="liste_titre"><th>Document type / Series</th><th>Pattern</th><th>Reset</th><th>Scope</th><th class="right">Current</th><th>Next number</th><th>Koreksi counter</th></tr>';
$shown = 0;
foreach ($seriesList as $series) {
	if ($filterStatus === 'active' && !$series->active) continue;
	if ($filterStatus === 'inactive' && $series->active) continue;
	$scope = $ns->scopeKey($series, dol_now());
	$current = $ns->currentValue($series->rowid, $scope);
	try { $next = $ns->preview($series); } catch (Throwable $e) { $next = 'Error: '.$e->getMessage(); }
	$label = isset($catalog[$series->document_type]) ? $catalog[$series->document_type]['label'] : $series->document_type;
	print '<tr class="oddeven"><td><strong>'.nsEscape($label).'</strong><br><a href="'.nsUrl('index.php?id='.(int) $series->rowid).'">'.nsEscape($series->label).'</a> <code>'.nsEscape($series->code).'</code>'.(!$series->active ? ' <span class="badge badge-status4">Nonaktif</span>' : '').'</td>';
	print '<td><code>'.nsEscape($series->pattern).'</code></td><td>'.nsEscape($series->reset_period).'</td><td><code>'.nsEscape($scope).'</code></td><td class="right"><strong class="ns-current">'.(int) $current.'</strong></td><td><strong>'.nsEscape($next).'</strong></td>';
	print '<td><form method="post" class="ns-inline-counter">'; nsToken('updatecounter');
	print '<input type="hidden" name="series_id" value="'.(int) $series->rowid.'"><input type="hidden" name="document_type" value="'.nsEscape($filterType).'"><input type="hidden" name="status" value="'.nsEscape($filterStatus).'">';
	print '<input required name="scope_key" value="'.nsEscape($scope).'" title="Scope: global, YYYY, YYYY-MM, atau YYYY-MM-DD"><input required type="number" min="0" name="current_value" value="'.(int) $current.'">';
	print '<button class="button smallpaddingimp" type="submit"'.(!$user->hasRight('namingseries', 'counter') ? ' disabled' : '').'>Update</button></form></td></tr>';
	$shown++;
}
if (!$shown) print '<tr><td colspan="7" class="opacitymedium">Tidak ada series yang cocok dengan filter.</td></tr>';
print '</table></div>';

print '<div class="ns-panel ns-warning"><strong>Perhatian:</strong> menaikkan counter akan melewati nomor. Menurunkan counter tidak membuat nomor duplikat karena engine akan melewati referensi yang sudah digunakan, tetapi urutan audit tetap mencatat perubahan manual.</div>';
nsEnd();
