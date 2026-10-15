<?php
require_once __DIR__.'/lib/common.php';
if (!$user->hasRight('creditlimit', 'approve')) accessforbidden();
$type = GETPOST('type','alpha'); $id = GETPOSTINT('id');
try { $object = clLoadDocument($type,$id); } catch (Throwable $e) { accessforbidden($e->getMessage()); }
$status = (int) ($object->status ?? $object->statut ?? 0);
if ($status !== 0) accessforbidden('Override hanya dapat dibuat untuk dokumen Draft.');
if ($_SERVER['REQUEST_METHOD']==='POST') {
	clPost('approve');
	try { $cl->approveOverride($object, GETPOST('reason','restricthtml'), $user); setEventMessages('Credit override berhasil disetujui. Validasi dokumen sebelum masa approval berakhir.',null,'mesgs'); header('Location: '.clUrl('override.php').'?type='.urlencode($type).'&id='.$id); exit; } catch(Throwable $e){ setEventMessages($e->getMessage(),null,'errors'); }
}
$check=$cl->evaluate($object,true);
clHeader('Approve Credit Override');
print '<div class="cl-help">Dokumen <b>'.clEscape($object->ref).'</b>. Approval hanya berlaku untuk nominal dokumen saat ini dan akan dipakai sekali ketika validasi berhasil.</div>';
print '<div class="cl-cards"><div><span>Current Exposure</span><strong>'.clMoney($check['exposure']).'</strong></div><div><span>Document Impact</span><strong>'.clMoney($check['delta']).'</strong></div><div><span>Projected Exposure</span><strong>'.clMoney($check['projected']).'</strong></div><div><span>Credit Limit</span><strong>'.clMoney($check['policy']->limit).'</strong></div><div><span>Overdue</span><strong>'.clMoney($check['overdue']).'</strong></div><div><span>Oldest</span><strong>'.$check['oldest_days'].' hari</strong></div></div>';
if ($check['violations']) print '<div class="cl-error"><b>Alasan blokir:</b> '.clEscape(implode('; ',$check['violations'])).'</div>';
$approved=$cl->one('SELECT o.*,u.login,u.firstname,u.lastname FROM '.$cl->table('cl_override').' o LEFT JOIN '.$cl->table('user').' u ON u.rowid=o.fk_user_approve WHERE o.entity='.$cl->entity.' AND o.element_type='.$cl->str($type).' AND o.fk_element='.$id." AND o.status='approved' AND o.date_expiry>=".$cl->str($db->idate(dol_now())).' ORDER BY o.rowid DESC LIMIT 1');
if ($approved) print '<div class="cl-ok">Approval aktif sampai '.clEscape(dol_print_date($db->jdate($approved->date_expiry),'dayhour')).' oleh '.clEscape(trim($approved->firstname.' '.$approved->lastname) ?: $approved->login).'.</div>';
if ($check['blocked']) { print '<form method="post" class="cl-form">'; clToken('approve'); print '<input type="hidden" name="type" value="'.clEscape($type).'"><input type="hidden" name="id" value="'.$id.'"><label>Alasan approval override</label><textarea name="reason" rows="4" minlength="5" maxlength="1000" required></textarea><button class="button button-save" type="submit">Approve Sekali</button></form>'; }
else print '<div class="cl-ok">Dokumen saat ini tidak diblokir oleh kebijakan kredit.</div>';
clEnd();
