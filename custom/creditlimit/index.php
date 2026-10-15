<?php
require_once __DIR__.'/lib/common.php';

$customers = $cl->rows('SELECT s.rowid,s.nom,s.code_client,s.outstanding_limit FROM '.$cl->table('societe').' s LEFT JOIN '.$cl->table('cl_customer_policy').' p ON p.entity=s.entity AND p.fk_soc=s.rowid WHERE s.entity='.$cl->entity.' AND s.client IN (1,2,3) AND s.status=1 AND (COALESCE(s.outstanding_limit,0)>0 OR p.rowid IS NOT NULL OR '.(float) $cl->config()->default_limit.'>0) ORDER BY s.nom LIMIT 250');
$rows = array(); $totals = array('exposure'=>0,'overdue'=>0,'blocked'=>0);
foreach ($customers as $customer) {
	$dummy = (object) array('element'=>'shipping','id'=>0,'socid'=>(int)$customer->rowid,'total_ttc'=>0);
	$check = $cl->evaluate($dummy, false); $status = (!$check['policy']->enabled || $check['policy']->mode === 'off') ? 'Off' : 'Normal';
	if ($status !== 'Off' && ($check['policy']->hold || ($check['policy']->limit > 0 && $check['exposure'] > $check['policy']->limit) || ($check['oldest_days'] > $check['policy']->max_days + $check['policy']->grace_days && $check['overdue'] > $check['policy']->overdue_limit))) { $status='Blocked'; $totals['blocked']++; }
	elseif ($status !== 'Off' && ($check['overdue'] > 0 || ($check['policy']->limit > 0 && $check['exposure'] >= $check['policy']->limit * .8))) $status='Warning';
	$rows[] = array($customer,$check,$status); $totals['exposure'] += $check['exposure']; $totals['overdue'] += $check['overdue'];
}
clHeader('Credit Control Dashboard');
print '<div class="cl-cards"><div><span>Customer Dipantau</span><strong>'.count($rows).'</strong></div><div><span>Total Exposure</span><strong>'.clMoney($totals['exposure']).'</strong></div><div><span>Total Overdue</span><strong>'.clMoney($totals['overdue']).'</strong></div><div><span>Blocked</span><strong>'.$totals['blocked'].'</strong></div></div>';
print '<div class="cl-scroll"><table class="noborder centpercent cl-table"><tr class="liste_titre"><th>Customer</th><th>Status</th><th class="right">Limit</th><th class="right">Invoice</th><th class="right">Order</th><th class="right">Exposure</th><th class="right">Available</th><th class="right">Overdue</th><th class="right">Oldest</th></tr>';
foreach ($rows as $item) { list($customer,$check,$status)=$item; print '<tr class="oddeven"><td><a href="'.clEscape(clUrl('customer.php').'?id='.$customer->rowid).'">'.clEscape($customer->nom).'</a><br><span class="opacitymedium">'.clEscape($customer->code_client).'</span></td><td><span class="cl-status cl-'.strtolower($status).'">'.$status.'</span></td><td class="right">'.clMoney($check['policy']->limit).'</td><td class="right">'.clMoney($check['invoice_outstanding']).'</td><td class="right">'.clMoney($check['order_exposure']).'</td><td class="right">'.clMoney($check['exposure']).'</td><td class="right">'.($check['available']===null?'Unlimited':clMoney($check['available'])).'</td><td class="right">'.clMoney($check['overdue']).'</td><td class="right">'.$check['oldest_days'].' hari</td></tr>'; }
if (!$rows) print '<tr><td colspan="9" class="center">Belum ada customer dengan kebijakan atau credit limit.</td></tr>';
print '</table></div>'; clEnd();
