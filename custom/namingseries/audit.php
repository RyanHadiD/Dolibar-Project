<?php

require_once __DIR__.'/lib/common.php';
if (!$user->hasRight('namingseries', 'audit')) accessforbidden();

nsHeader('Audit Naming Series');
$sql = 'SELECT a.*,s.code,u.login FROM '.MAIN_DB_PREFIX.'ns_audit a LEFT JOIN '.MAIN_DB_PREFIX.'ns_series s ON s.rowid=a.fk_series LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=a.fk_user WHERE a.entity='.(int) $conf->entity.' ORDER BY a.rowid DESC LIMIT 500';
$res = $db->query($sql);
print '<table class="liste centpercent"><tr class="liste_titre"><th>Waktu</th><th>Series</th><th>Event</th><th>Scope</th><th>Perubahan</th><th>Dokumen</th><th>User</th></tr>';
if ($res) while ($row = $db->fetch_object($res)) {
	print '<tr class="oddeven"><td>'.nsEscape($row->datec).'</td><td>'.nsEscape($row->code).'</td><td>'.nsEscape($row->event_type).'</td><td>'.nsEscape($row->scope_key).'</td><td>'.nsEscape($row->old_value).' → '.nsEscape($row->new_value).'</td><td>'.nsEscape($row->document_ref).'</td><td>'.nsEscape($row->login).'</td></tr>';
}
print '</table>';
nsEnd();
