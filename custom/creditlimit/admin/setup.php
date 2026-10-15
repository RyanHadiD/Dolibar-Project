<?php
require_once __DIR__.'/../lib/common.php';
if (!$user->admin) accessforbidden();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	clPost();
	try {
		$cl->saveConfig(array(
			'enabled' => GETPOST('enabled', 'alpha'), 'default_limit' => GETPOST('default_limit', 'alphanohtml'), 'default_mode' => GETPOST('default_mode', 'alpha'),
			'max_overdue_days' => GETPOSTINT('max_overdue_days'), 'overdue_amount_limit' => GETPOST('overdue_amount_limit', 'alphanohtml'), 'grace_days' => GETPOSTINT('grace_days'),
			'check_order' => GETPOST('check_order', 'alpha'), 'check_shipment' => GETPOST('check_shipment', 'alpha'), 'check_invoice' => GETPOST('check_invoice', 'alpha'),
			'include_orders' => GETPOST('include_orders', 'alpha'), 'override_valid_days' => GETPOSTINT('override_valid_days'),
		), $user);
		setEventMessages('Pengaturan Credit Control berhasil disimpan.', null, 'mesgs');
		header('Location: '.clUrl('admin/setup.php')); exit;
	} catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
$c = $cl->config();
clHeader('Pengaturan Credit Control');
print '<div class="cl-help">Limit khusus menggunakan field <b>Outstanding limit</b> native pada Customer. Default entitas hanya dipakai bila customer tidak memiliki limit positif.</div>';
print '<form method="post" class="cl-form">'; clToken('save');
print '<label><input type="checkbox" name="enabled" value="1"'.($c->enabled ? ' checked' : '').'> Aktifkan kontrol kredit</label>';
print '<label>Mode default</label><select name="default_mode"><option value="block"'.($c->default_mode === 'block' ? ' selected' : '').'>Block</option><option value="warn"'.($c->default_mode === 'warn' ? ' selected' : '').'>Warning</option><option value="off"'.($c->default_mode === 'off' ? ' selected' : '').'>Off</option></select>';
foreach (array('default_limit' => 'Default credit limit entitas', 'max_overdue_days' => 'Maksimum hari overdue', 'overdue_amount_limit' => 'Batas nominal overdue', 'grace_days' => 'Grace period (hari)', 'override_valid_days' => 'Masa berlaku approval override (hari)') as $field => $label) print '<label>'.$label.'</label><input type="number" min="0" step="'.(strpos($field, 'limit') !== false ? '0.01' : '1').'" name="'.$field.'" value="'.clEscape($c->$field).'">';
foreach (array('include_orders' => 'Sales Order mencadangkan credit', 'check_order' => 'Periksa saat validasi Sales Order', 'check_shipment' => 'Periksa saat validasi Shipment', 'check_invoice' => 'Periksa saat validasi Customer Invoice') as $field => $label) print '<label><input type="checkbox" name="'.$field.'" value="1"'.($c->$field ? ' checked' : '').'> '.$label.'</label>';
print '<button class="button button-save" type="submit">Simpan</button></form>';
clEnd();
