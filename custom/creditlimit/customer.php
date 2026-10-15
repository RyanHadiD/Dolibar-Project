<?php
require_once __DIR__.'/lib/common.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$id = GETPOSTINT('id'); $soc = new Societe($db);
if ($id <= 0 || $soc->fetch($id) <= 0 || (int) $soc->entity !== (int) $conf->entity) accessforbidden('Customer tidak ditemukan.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	clPost('manage');
	try {
		$cl->saveCustomerPolicy($id, array('credit_limit' => GETPOST('credit_limit', 'alphanohtml'), 'control_mode' => GETPOST('control_mode', 'alpha'), 'max_overdue_days' => GETPOST('max_overdue_days', 'alphanohtml'), 'overdue_amount_limit' => GETPOST('overdue_amount_limit', 'alphanohtml'), 'grace_days' => GETPOST('grace_days', 'alphanohtml'), 'credit_hold' => GETPOST('credit_hold', 'alpha'), 'note' => GETPOST('note', 'restricthtml')), $user);
		setEventMessages('Kebijakan kredit customer berhasil disimpan.', null, 'mesgs'); header('Location: '.clUrl('customer.php').'?id='.$id); exit;
	} catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
$policyRow = $cl->one('SELECT * FROM '.$cl->table('cl_customer_policy').' WHERE entity='.$cl->entity.' AND fk_soc='.$id);
$dummy = (object) array('element' => 'shipping', 'id' => 0, 'socid' => $id, 'total_ttc' => 0);
$check = $cl->evaluate($dummy, false); $p = $check['policy'];
clHeader('Credit Control — '.$soc->name);
print '<div class="cl-cards"><div><span>Credit Limit</span><strong>'.clMoney($p->limit).'</strong></div><div><span>Invoice Outstanding</span><strong>'.clMoney($check['invoice_outstanding']).'</strong></div><div><span>Order Reserved</span><strong>'.clMoney($check['order_exposure']).'</strong></div><div><span>Available Credit</span><strong>'.($check['available'] === null ? 'Unlimited' : clMoney($check['available'])).'</strong></div><div><span>Total Overdue</span><strong>'.clMoney($check['overdue']).'</strong></div><div><span>Oldest Overdue</span><strong>'.(int) $check['oldest_days'].' hari</strong></div></div>';
if ($p->hold) print '<div class="cl-error">Customer sedang Credit Hold.</div>';
if ($user->hasRight('creditlimit', 'manage')) {
	print '<form method="post" class="cl-form">'; clToken('save');
	print '<label>Credit limit customer</label><input type="number" min="0" step="0.01" name="credit_limit" value="'.clEscape($soc->outstanding_limit).'">';
	print '<label>Mode kontrol</label><select name="control_mode">'; foreach (array('inherit'=>'Ikuti default','block'=>'Block','warn'=>'Warning','off'=>'Off') as $v=>$l) print '<option value="'.$v.'"'.(($policyRow->control_mode ?? 'inherit') === $v ? ' selected' : '').'>'.$l.'</option>'; print '</select>';
	foreach (array('max_overdue_days'=>'Maksimum hari overdue (kosong = default)','overdue_amount_limit'=>'Batas nominal overdue (kosong = default)','grace_days'=>'Grace period hari (kosong = default)') as $f=>$l) print '<label>'.$l.'</label><input type="number" min="0" step="'.($f === 'overdue_amount_limit' ? '0.01' : '1').'" name="'.$f.'" value="'.clEscape($policyRow->$f ?? '').'">';
	print '<label><input type="checkbox" name="credit_hold" value="1"'.(!empty($policyRow->credit_hold) ? ' checked' : '').'> Credit Hold</label><label>Catatan kebijakan</label><textarea name="note" rows="4" maxlength="1000">'.clEscape($policyRow->note ?? '').'</textarea><button class="button button-save" type="submit">Simpan Kebijakan</button></form>';
}
print '<h3>Invoice Outstanding</h3><div class="cl-scroll"><table class="noborder centpercent"><tr class="liste_titre"><th>Invoice</th><th>Due Date</th><th class="right">Outstanding</th><th class="right">Overdue</th></tr>';
foreach ($check['invoices'] as $inv) print '<tr class="oddeven"><td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$inv->id.'">'.clEscape($inv->ref).'</a></td><td>'.clEscape($inv->due_date).'</td><td class="right">'.clMoney($inv->remain).'</td><td class="right">'.($inv->overdue_days ? $inv->overdue_days.' hari' : 'Current').'</td></tr>';
if (!$check['invoices']) print '<tr><td colspan="4" class="center">Tidak ada invoice outstanding.</td></tr>'; print '</table></div>';
clEnd();
