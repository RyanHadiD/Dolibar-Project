<?php

require_once __DIR__.'/lib/common.php';

if (GETPOST('action', 'alpha') === 'create') {
	obPost('write');
	try {
		$id = $ob->createBatch(GETPOST('cutover_date', 'alphanohtml'), GETPOST('label', 'alphanohtml'), $user);
		setEventMessages('Batch Draft berhasil dibuat.', null, 'mesgs');
		header('Location: '.obUrl('card.php?id='.$id));
		exit;
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
}

obHeader('Saldo Awal & Migrasi');
print '<div class="ob-help"><b>Versi 0.2:</b> siapkan dan validasi batch terlebih dahulu, lalu finalisasi secara eksplisit. Finalisasi membuat invoice piutang/hutang native yang dapat dilunasi serta jurnal saldo awal yang tervalidasi.</div>';

if ($user->hasRight('openingbalance', 'write')) {
	print '<form method="post" class="ob-form ob-create">';
	obToken('create');
	print '<label>Nama batch<input name="label" maxlength="255" required placeholder="Migrasi saldo awal 2026"></label>';
	print '<label>Tanggal cutover<input type="date" name="cutover_date" max="'.date('Y-m-d', dol_now()).'" required></label>';
	print '<button class="button button-save" type="submit">Buat Batch Draft</button></form>';
}

$batches = $ob->listBatches();
print '<div class="div-table-responsive"><table class="noborder centpercent"><thead><tr class="liste_titre"><th>Referensi</th><th>Nama</th><th>Cutover</th><th>Status</th><th>Pembuat</th><th>Diperbarui</th></tr></thead><tbody>';
foreach ($batches as $batch) {
	print '<tr class="oddeven"><td><a href="'.obEscape(obUrl('card.php?id='.(int) $batch->rowid)).'"><b>'.obEscape($batch->ref).'</b></a></td><td>'.obEscape($batch->label).'</td><td>'.obEscape($batch->cutover_date).'</td><td><span class="ob-status ob-status-'.(int) $batch->status.'">'.obEscape(obStatus($batch->status)).'</span></td><td>'.obEscape($batch->author_login).'</td><td>'.obEscape($batch->tms).'</td></tr>';
}
if (!$batches) {
	print '<tr><td colspan="6" class="center opacitymedium">Belum ada batch saldo awal.</td></tr>';
}
print '</tbody></table></div>';
obEnd();
