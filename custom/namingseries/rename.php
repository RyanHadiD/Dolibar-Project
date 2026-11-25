<?php

require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/class/documentrenumber.class.php';

if (!$user->admin && !$user->hasRight('namingseries', 'counter')) accessforbidden();

$type = GETPOST('document_type', 'alpha');
$id = GETPOSTINT('id');
$returnUrl = GETPOST('return_url', 'restricthtml');
if ($returnUrl === '' || $returnUrl[0] !== '/' || strpos($returnUrl, '//') === 0 || preg_match('/[\r\n]/', $returnUrl)) $returnUrl = nsUrl('index.php');

$service = new NamingSeriesDocumentRenumber($db, $ns, $conf->entity);
try {
	$document = $service->document($type, $id);
} catch (Throwable $e) {
	if ($type === 'customer_invoice') {
		try {
			$type = 'takepos_invoice';
			$document = $service->document($type, $id);
		} catch (Throwable $fallbackError) {
			accessforbidden($e->getMessage());
		}
	} else {
		accessforbidden($e->getMessage());
	}
}

$action = GETPOST('action', 'alpha');
if ($action === 'rename') {
	nsPost('counter');
	try {
		if (GETPOSTINT('confirm') !== 1) throw new RuntimeException('Konfirmasi rename wajib dicentang.');
		$newReference = trim(dol_string_nohtmltag(GETPOST('new_ref', 'restricthtml')));
		$result = $service->renameOne($type, $id, $newReference, GETPOST('old_ref', 'restricthtml'), $user->id);
		setEventMessages('Dokumen berhasil di-rename dari '.$result['old'].' menjadi '.$result['new'].'.', null, 'mesgs');
		header('Location: '.$returnUrl);
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

$types = NamingSeries::documentTypes();
$label = !empty($types[$type]['label']) ? $types[$type]['label'] : str_replace('_', ' ', $type);
nsHeader('Rename Document');
print '<div class="ns-panel"><h2>Rename '.nsEscape($label).'</h2>';
print '<p>Rename mengubah nomor dokumen tanpa mengubah ID internal. Relasi berbasis ID tetap tersambung; referensi teks, jurnal, audit, folder, dan nama file terkait akan disesuaikan.</p>';
print '<form method="post" class="ns-form">'; nsToken('rename');
print '<input type="hidden" name="document_type" value="'.nsEscape($type).'">';
print '<input type="hidden" name="id" value="'.(int) $id.'">';
print '<input type="hidden" name="old_ref" value="'.nsEscape($document->document_ref).'">';
print '<input type="hidden" name="return_url" value="'.nsEscape($returnUrl).'">';
print '<label>Nomor sekarang</label><input readonly value="'.nsEscape($document->document_ref).'">';
print '<label for="new_ref">Nomor baru</label><input id="new_ref" required autofocus name="new_ref" maxlength="255" value="'.nsEscape(GETPOST('new_ref', 'restricthtml') ?: $document->document_ref).'">';
print '<label><input required type="checkbox" name="confirm" value="1"> Saya memahami nomor dokumen dan referensi terkait akan diubah.</label>';
print '<div><button class="button button-save" type="submit">Rename Document</button> <a class="button button-cancel" href="'.nsEscape($returnUrl).'">Batal</a></div>';
print '</form></div>';
print '<div class="ns-panel"><strong>Keamanan</strong><ul><li>Nomor tujuan harus unik pada jenis dokumen tersebut.</li><li>Perubahan database dijalankan dalam transaksi atomik.</li><li>Jika verifikasi gagal, transaksi database dan pemindahan folder dibatalkan.</li><li>Counter naming series tidak dimundurkan atau dipakai ulang.</li><li>PDF lama perlu digenerate ulang agar isi PDF menampilkan nomor baru.</li></ul></div>';
nsEnd();
