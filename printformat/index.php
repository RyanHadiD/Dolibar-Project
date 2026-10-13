<?php

require_once __DIR__.'/lib/common.php';

$action = GETPOST('action', 'aZ09');
if ($action === 'delete') {
	pfPost();
	try {
		$printFormatRepository->delete(GETPOSTINT('id'));
		setEventMessages('Print format dihapus.', null, 'mesgs');
	} catch (Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
	header('Location: '.pfUrl('index.php'));
	exit;
}

pfHeader('Print Format');
print '<div class="pf-help">Buat layout dokumen native Dolibarr menggunakan HTML, CSS, variable, loop, dan kondisi. Setiap doctype memiliki template aktif dan default masing-masing.</div>';
if ($user->hasRight('printformat', 'write')) {
	print '<p><a class="butAction" href="'.pfEscape(pfUrl('card.php')).'">Buat Print Format</a></p>';
}
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>Nama</th><th>Dokumen</th><th>Halaman</th><th>Status</th><th>Default</th><th>Diperbarui</th><th></th></tr>';
$doctypeLabels = PrintFormatDoctypeRegistry::labels();
foreach ($printFormatRepository->all('') as $format) {
	$pageLabel = $format->page_format === 'CUSTOM' ? 'Custom '.((float) $format->custom_width).' × '.((float) $format->custom_height).' mm' : $format->page_format;
	print '<tr class="oddeven"><td><a href="'.pfEscape(pfUrl('card.php?id='.(int) $format->rowid)).'"><b>'.pfEscape($format->name).'</b></a></td><td>'.pfEscape($doctypeLabels[$format->doctype] ?? $format->doctype).'</td><td>'.pfEscape($pageLabel.' / '.$format->orientation).'</td><td>'.($format->active ? '<span class="badge badge-status4">Aktif</span>' : '<span class="badge badge-status0">Nonaktif</span>').'</td><td>'.($format->is_default ? 'Ya' : '').'</td><td>'.pfEscape($format->tms).'</td><td class="right">';
	if ($user->hasRight('printformat', 'write')) {
		print '<form method="post" class="inline-block" onsubmit="return confirm(\'Hapus print format ini?\')"><input type="hidden" name="token" value="'.pfEscape(newToken()).'"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.(int) $format->rowid.'"><button class="button button-delete smallpaddingimp" type="submit">Hapus</button></form>';
	}
	print '</td></tr>';
}
print '</table></div>';
pfEnd();
