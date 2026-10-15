<?php
require_once __DIR__.'/../lib/common.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

if (!$user->admin) accessforbidden();

$rightId = 529443;
$right = $cl->one('SELECT id FROM '.$cl->table('rights_def').' WHERE entity='.$cl->entity.' AND module='.$cl->str('creditlimit').' AND perms='.$cl->str('approve').' LIMIT 1');
if ($right) $rightId = (int) $right->id;

$users = $cl->rows('SELECT rowid,login,firstname,lastname,email,admin,statut FROM '.$cl->table('user').' WHERE entity IN (0,'.$cl->entity.') AND (fk_soc IS NULL OR fk_soc=0) ORDER BY statut DESC,admin DESC,lastname,firstname,login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	clPost('');
	$selected = GETPOST('bypass_users', 'array:int');
	if (!is_array($selected)) $selected = array();
	$selected = array_map('intval', $selected);
	$errors = array();
	foreach ($users as $row) {
		if ((int) $row->admin || !(int) $row->statut) continue;
		$hasDirect = $cl->one('SELECT fk_user FROM '.$cl->table('user_rights').' WHERE entity='.$cl->entity.' AND fk_user='.(int) $row->rowid.' AND fk_id='.$rightId.' LIMIT 1') !== null;
		$shouldHave = in_array((int) $row->rowid, $selected, true);
		if ($hasDirect === $shouldHave) continue;
		$editUser = new User($db);
		if ($editUser->fetch((int) $row->rowid) <= 0) { $errors[] = 'User ID '.(int) $row->rowid.' tidak dapat dibaca.'; continue; }
		$result = $shouldHave ? $editUser->addrights($rightId, '', '', $conf->entity) : $editUser->delrights($rightId, '', '', $conf->entity);
		if ($result < 0) $errors[] = $editUser->error ?: 'Gagal memperbarui hak '.$row->login.'.';
	}
	if ($errors) setEventMessages('Sebagian pengaturan user bypass gagal disimpan.', $errors, 'errors');
	else setEventMessages('Daftar user yang boleh memberi bypass berhasil disimpan.', null, 'mesgs');
	header('Location: '.clUrl('admin/bypass_users.php')); exit;
}

$direct = array();
foreach ($cl->rows('SELECT fk_user FROM '.$cl->table('user_rights').' WHERE entity='.$cl->entity.' AND fk_id='.$rightId) as $row) $direct[(int) $row->fk_user] = true;
$group = array();
$sql = 'SELECT DISTINCT gu.fk_user FROM '.$cl->table('usergroup_user').' gu JOIN '.$cl->table('usergroup_rights').' gr ON gr.fk_usergroup=gu.fk_usergroup AND gr.entity='.$cl->entity.' WHERE gr.fk_id='.$rightId.' AND gu.entity IN (0,'.$cl->entity.')';
foreach ($cl->rows($sql) as $row) $group[(int) $row->fk_user] = true;

clHeader('Pengaturan User Bypass');
print '<div class="cl-help"><b>Cara kerja:</b> user yang dipilih dapat membuat approval override pada Sales Order, Shipment, atau Customer Invoice yang diblokir. Approval wajib memakai alasan, berlaku sementara, hanya untuk satu dokumen dan nominal yang sama, lalu otomatis habis setelah dipakai.</div>';
print '<div class="cl-help">Admin/superadmin selalu memiliki hak bypass. Hak dari grup dikelola pada menu <b>Users &amp; Groups &gt; Groups &gt; Permissions</b>; halaman ini mengatur hak langsung per user.</div>';
print '<form method="post">'; clToken('save_bypass_users');
print '<div class="cl-scroll"><table class="noborder centpercent cl-users"><tr class="liste_titre"><th>User</th><th>Login</th><th>Status</th><th class="center">Boleh Approve Bypass</th><th>Sumber Hak</th></tr>';
foreach ($users as $row) {
	$id = (int) $row->rowid;
	$name = trim($row->firstname.' '.$row->lastname);
	$isAdmin = (int) $row->admin === 1;
	$isActive = (int) $row->statut === 1;
	$source = array();
	if ($isAdmin) $source[] = 'Admin';
	if (isset($direct[$id])) $source[] = 'Langsung';
	if (isset($group[$id])) $source[] = 'Grup';
	print '<tr class="oddeven"><td>'.clEscape($name ?: $row->login).($row->email ? '<br><span class="opacitymedium">'.clEscape($row->email).'</span>' : '').'</td><td>'.clEscape($row->login).'</td><td><span class="cl-badge '.($isActive ? 'cl-badge-ok' : '').'">'.($isActive ? 'Aktif' : 'Nonaktif').'</span></td><td class="center">';
	if ($isAdmin) print '<input type="checkbox" checked disabled title="Admin selalu boleh approve bypass">';
	else print '<input type="checkbox" name="bypass_users[]" value="'.$id.'"'.(isset($direct[$id]) ? ' checked' : '').($isActive ? '' : ' disabled').'>';
	print '</td><td>'.($source ? clEscape(implode(' + ', $source)) : '<span class="opacitymedium">Tidak ada</span>');
	if (!$isAdmin && isset($group[$id]) && !isset($direct[$id])) print '<br><span class="opacitymedium">Tetap aktif melalui grup</span>';
	print '</td></tr>';
}
print '</table></div><div class="cl-actions"><button class="button button-save" type="submit">Simpan User Bypass</button></div></form>';
clEnd();
