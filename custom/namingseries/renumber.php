<?php

require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/documentrenumber.class.php';

if (!$user->hasRight('namingseries', 'counter')) accessforbidden();

$service = new NamingSeriesDocumentRenumber($db, $ns, $conf->entity);
$catalog = $service->catalog();
$type = GETPOST('document_type', 'alpha') ?: 'customer_payment';
if (!isset($catalog[$type])) $type = 'customer_payment';
$series = $ns->resolveSeries($type);
$scope = trim(GETPOST('scope_key', 'alphanohtml'));
if ($scope === '') $scope = $service->defaultScope($type);
$plan = null;
$action = GETPOST('action', 'alpha');

if ($action === 'apply') {
	nsPost('counter');
	try {
		if (GETPOSTINT('confirm') !== 1) throw new RuntimeException('Konfirmasi wajib dicentang.');
		$result = $service->apply($type, $scope, GETPOST('plan_hash', 'alphanohtml'), $user->id, GETPOSTINT('accept_inactive_model') === 1);
		setEventMessages(count($result['plan']['mappings']).' dokumen berhasil diurutkan. Backup: '.$result['backup'], null, 'mesgs');
		header('Location: '.nsUrl('renumber.php?document_type='.urlencode($type).'&scope_key='.urlencode($scope).'&done=1')); exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

if (!empty($catalog[$type]['available']) && $scope !== '') {
	try { $plan = $service->preview($type, $scope); }
	catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}

nsHeader('Urutkan Nomor Dokumen');
print '<div class="ns-panel"><h2>Urutkan Nomor Dokumen</h2>';
print '<p>Pilih jenis dokumen dan scope series. Dokumen diurutkan berdasarkan tanggal transaksi, tanggal pembuatan, lalu ID. Seluruh relasi berbasis ID tetap dipertahankan.</p>';
print '<form method="get" class="ns-filter ns-renumber-filter"><label>Jenis dokumen <select id="ns-document-type" name="document_type">';
foreach ($catalog as $key => $definition) {
	print '<option value="'.nsEscape($key).'" data-scope="'.nsEscape($service->defaultScope($key)).'"'.($type === $key ? ' selected' : '').(!empty($definition['available']) ? '' : ' disabled').'>'.nsEscape($definition['label']).(!empty($definition['available']) ? '' : ' — modul tidak tersedia').'</option>';
}
print '</select></label><label>Scope <input id="ns-scope-key" required name="scope_key" value="'.nsEscape($scope).'" placeholder="YYYY-MM"></label><button class="button" type="submit">Tampilkan preview</button></form>';
print '<script>document.getElementById("ns-document-type").addEventListener("change",function(){var o=this.options[this.selectedIndex];document.getElementById("ns-scope-key").value=o.dataset.scope||"";});</script>';
if ($series) {
	$format = array('daily' => 'YYYY-MM-DD', 'monthly' => 'YYYY-MM', 'yearly' => 'YYYY', 'never' => 'global');
	print '<p class="opacitymedium">Series aktif: <code>'.nsEscape($series->pattern).'</code> · Reset: '.nsEscape($series->reset_period).' · Format scope: <code>'.nsEscape($format[$series->reset_period] ?? 'global').'</code></p>';
	if (empty($catalog[$type]['integration_active'])) print '<div class="warning">Numbering model aktif masih <code>'.nsEscape($catalog[$type]['numbering_model'] ?: 'belum dikonfigurasi').'</code>. Renumber tetap dapat dilakukan, tetapi nomor dokumen berikutnya belum tentu melanjutkan series ini sampai model <strong>NamingSeries</strong> diaktifkan.</div>';
}
print '</div>';

if ($plan) {
	$changed = 0;
	print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th class="right">Urutan</th><th>Tanggal</th><th>Referensi sekarang</th><th>Referensi baru</th><th>Status</th></tr>';
	foreach ($plan['mappings'] as $index => $mapping) {
		$isChanged = $mapping['old'] !== $mapping['new'];
		if ($isChanged) $changed++;
		print '<tr class="oddeven"><td class="right">'.($index + 1).'</td><td>'.nsEscape($mapping['date']).'</td><td><code>'.nsEscape($mapping['old']).'</code></td><td><strong><code>'.nsEscape($mapping['new']).'</code></strong></td><td>'.($isChanged ? '<span class="badge badge-status1">Akan diubah</span>' : '<span class="opacitymedium">Sudah sesuai</span>').'</td></tr>';
	}
	if (!$plan['mappings']) print '<tr><td colspan="5" class="opacitymedium">Tidak ada dokumen pada scope ini.</td></tr>';
	print '</table></div>';
	print '<div class="ns-panel ns-warning"><strong>Preview:</strong> '.count($plan['mappings']).' dokumen ditemukan, '.$changed.' referensi akan berubah. Counter scope akan disesuaikan dengan nomor terakhir.</div>';
	if ($changed) {
		print '<form method="post" class="ns-renumber-apply">'; nsToken('apply');
		print '<input type="hidden" name="document_type" value="'.nsEscape($type).'"><input type="hidden" name="scope_key" value="'.nsEscape($scope).'"><input type="hidden" name="plan_hash" value="'.nsEscape($plan['hash']).'">';
		print '<label><input required type="checkbox" name="confirm" value="1"> Saya sudah memeriksa preview dan memahami bahwa referensi dokumen akan diubah.</label> ';
		if (empty($catalog[$type]['integration_active'])) print '<label><input required type="checkbox" name="accept_inactive_model" value="1"> Saya memahami numbering model aktif belum memakai NamingSeries.</label> ';
		print '<button class="button button-save" type="submit">Terapkan urutan</button></form>';
	}
}

print '<div class="ns-panel"><strong>Keamanan perubahan</strong><ul><li>Preview dihitung ulang saat diterapkan; data yang berubah di tengah proses otomatis membatalkan operasi.</li><li>Database dan folder dokumen terkait dibackup sebelum perubahan.</li><li>ID dokumen tidak berubah, sehingga relasi transaksi tetap tersambung.</li><li>Folder serta nama file yang mengandung referensi ikut diubah.</li><li>Setiap perubahan dicatat pada Audit Naming Series.</li></ul><p class="opacitymedium">PDF yang isi cetaknya menampilkan nomor lama perlu digenerate ulang dari kartu dokumen setelah renumber.</p></div>';
nsEnd();
