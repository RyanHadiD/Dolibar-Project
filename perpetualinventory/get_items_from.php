<?php

/** ERP-style source document picker for Shipments and Receptions. */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('orders', 'sendings', 'receptions', 'companies', 'stocks', 'productbatch', 'perpetualinventory@perpetualinventory'));

$target = GETPOST('target', 'alpha');
if (!in_array($target, array('shipment', 'reception'), true)) {
	$target = 'shipment';
}
$isShipment = $target === 'shipment';
$destinationId = GETPOSTINT('destination_id');
$orderId = GETPOSTINT('order_id');
$action = GETPOST('action', 'aZ09');
$permissionModule = $isShipment ? 'expedition' : 'reception';
if (!$user->admin && !$user->hasRight($permissionModule, 'creer')) {
	accessforbidden();
}
if (!isModEnabled('perpetualinventory') || $user->socid) {
	accessforbidden();
}

$search = trim(GETPOST('search', 'restricthtml'));
$page = max(0, GETPOSTINT('page'));
$limit = max(10, min(100, GETPOSTINT('limit') ?: 25));
$offset = $page * $limit;
$entitySql = getEntity($isShipment ? 'commande' : 'supplier_order');
$movementEntitySql = getEntity($isShipment ? 'expedition' : 'reception');
$stockServices = getDolGlobalString('STOCK_SUPPORTS_SERVICES') ? true : false;
$destination = null;

if ($destinationId > 0) {
	if ($isShipment) {
		setEventMessages($langs->trans('PINotAvailableForExistingShipment'), null, 'warnings');
		$destinationId = 0;
	} else {
		$destination = new Reception($db);
		if ($destination->fetch($destinationId) <= 0 || (int) $destination->entity !== (int) $conf->entity) {
			accessforbidden();
		}
		if ((int) $destination->status !== Reception::STATUS_DRAFT) {
			setEventMessages($langs->trans('PIImportDraftOnly'), null, 'errors');
			$destinationId = 0;
			$destination = null;
		}
	}
}

$getReceptionLines = function ($purchaseOrderId) use ($db, $movementEntitySql, $stockServices) {
	$lineCondition = $stockServices ? '1=1' : 'd.product_type = 0';
	$sql = 'SELECT d.rowid, d.fk_commande, d.fk_product, d.fk_unit, d.description, d.qty AS ordered_qty,';
	$sql .= ' d.subprice, d.remise_percent, d.product_type, p.ref AS product_ref, p.label AS product_label,';
	$sql .= ' p.tobatch, p.fk_default_warehouse, COALESCE(done.processed_qty, 0) AS processed_qty,';
	$sql .= ' CASE WHEN ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) > 0 THEN ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) ELSE 0 END AS outstanding_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet d';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = d.fk_product';
	$sql .= ' LEFT JOIN (SELECT rd.fk_elementdet, SUM(rd.qty) AS processed_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'receptiondet_batch rd';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'reception r ON r.rowid = rd.fk_reception';
	$sql .= " WHERE r.entity IN (".$movementEntitySql.") AND r.fk_statut >= 0 AND rd.element_type IN ('supplier_order', 'order_supplier')";
	$sql .= ' GROUP BY rd.fk_elementdet) done ON done.fk_elementdet = d.rowid';
	$sql .= ' WHERE d.fk_commande = '.((int) $purchaseOrderId).' AND '.$lineCondition;
	$sql .= ' AND ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) > 0';
	$sql .= ' ORDER BY d.rang, d.rowid';
	$lines = array();
	$resql = $db->query($sql);
	while ($resql && ($line = $db->fetch_object($resql))) {
		$lines[(int) $line->rowid] = $line;
	}
	return $lines;
};

// Import into an already-open draft Reception.  This is the ERP-style
// "Get Items" behavior: it enriches the current draft instead of silently
// starting another Reception.
if (!$isShipment && $action === 'import_reception' && $destinationId > 0 && $destination) {
	$purchaseOrder = new CommandeFournisseur($db);
	if ($purchaseOrder->fetch($orderId) <= 0 || (int) $purchaseOrder->entity !== (int) $conf->entity || !in_array((int) $purchaseOrder->status, array(3, 4, 5), true)) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	} elseif ((int) $purchaseOrder->socid !== (int) $destination->socid) {
		setEventMessages($langs->trans('PISupplierMustMatch'), null, 'errors');
	} else {
		$availableLines = $getReceptionLines($orderId);
		$selected = array();
		foreach ($_POST as $key => $value) {
			if (preg_match('/^fk_commandefourndet_0_([0-9]+)$/', $key, $matches)) {
				$suffix = '0_'.$matches[1];
				$lineId = GETPOSTINT($key);
				$qty = price2num(GETPOST('qty_'.$suffix), 'MS');
				if ($qty > 0) {
					$selected[] = array(
						'line_id' => $lineId,
						'qty' => (float) $qty,
						'warehouse_id' => GETPOSTINT('entrepot_'.$suffix),
						'batch' => GETPOST('lot_number_'.$suffix, 'restricthtml'),
						'sellby' => dol_mktime(12, 0, 0, GETPOSTINT('dlc_'.$suffix.'month'), GETPOSTINT('dlc_'.$suffix.'day'), GETPOSTINT('dlc_'.$suffix.'year')),
						'eatby' => dol_mktime(12, 0, 0, GETPOSTINT('dluo_'.$suffix.'month'), GETPOSTINT('dluo_'.$suffix.'day'), GETPOSTINT('dluo_'.$suffix.'year')),
					);
				}
			}
		}
		if (!$selected) {
			setEventMessages($langs->trans('PISelectAtLeastOneItem'), null, 'errors');
		} else {
			$db->begin();
			// Re-read availability inside the transaction so a stale picker page
			// cannot over-receive after another Reception was created.
			$availableLines = $getReceptionLines($orderId);
			$error = 0;
			$preparedIndexes = array();
			foreach ($selected as $selection) {
				$lineId = $selection['line_id'];
				if (empty($availableLines[$lineId]) || $selection['qty'] > (float) $availableLines[$lineId]->outstanding_qty + 0.000001) {
					setEventMessages($langs->trans('PIQtyExceedsOutstanding'), null, 'errors');
					$error++;
					break;
				}
				if (!empty($availableLines[$lineId]->fk_product)) {
					$warehouseSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'entrepot WHERE rowid = '.((int) $selection['warehouse_id']);
					$warehouseSql .= ' AND entity IN ('.getEntity('stock').') AND statut = 1';
					$warehouseResult = $db->query($warehouseSql);
					if (!$warehouseResult || !$db->num_rows($warehouseResult)) {
						setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('Warehouse')), null, 'errors');
						$error++;
						break;
					}
				}
				$sourceLine = $availableLines[$lineId];
				$costPrice = price2num((float) $sourceLine->subprice * (100 - (float) $sourceLine->remise_percent) / 100, 'MU');
				$index = $destination->addline($selection['warehouse_id'], $lineId, $selection['qty'], array(), '', $selection['eatby'], $selection['sellby'], $selection['batch'], $costPrice);
				if ($index < 0) {
					setEventMessages($destination->error, $destination->errors, 'errors');
					$error++;
					break;
				}
				$preparedIndexes[] = $index;
			}
			if (!$error) {
				foreach ($preparedIndexes as $index) {
					if ($destination->lines[$index]->create($user) > 0) {
						continue;
					}
					setEventMessages($destination->lines[$index]->error, $destination->lines[$index]->errors, 'errors');
					$error++;
					break;
				}
			}
			if (!$error) {
				$linkExistsSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'element_element';
				$linkExistsSql .= " WHERE sourcetype = 'order_supplier' AND fk_source = ".((int) $orderId);
				$linkExistsSql .= " AND targettype = 'reception' AND fk_target = ".((int) $destinationId);
				$linkExists = $db->query($linkExistsSql);
				if (!$linkExists || !$db->num_rows($linkExists)) {
					if ($destination->add_object_linked('order_supplier', $orderId, $user) <= 0) {
						setEventMessages($destination->error, $destination->errors, 'errors');
						$error++;
					}
				}
			}
			if (!$error) {
				$db->commit();
				setEventMessages($langs->trans('PIItemsAddedToDraft'), null, 'mesgs');
				header('Location: '.DOL_URL_ROOT.'/reception/card.php?id='.(int) $destinationId);
				exit;
			}
			$db->rollback();
		}
	}
}

if ($isShipment) {
	$title = $langs->trans('PIGetItemsFromSalesOrder');
	$orderLabel = $langs->trans('CustomerOrder');
	$partyLabel = $langs->trans('Customer');
	$dateLabel = $langs->trans('OrderDate');
	$processedLabel = $langs->trans('QtyShipped');
	$backUrl = DOL_URL_ROOT.'/expedition/list.php?leftmenu=sendings';
	$lineCondition = $stockServices ? '1=1' : 'd.product_type = 0';
	$sql = 'SELECT o.rowid, o.ref, o.ref_client AS thirdparty_ref, o.date_commande AS order_date,';
	$sql .= ' s.nom AS thirdparty_name, COUNT(DISTINCT d.rowid) AS line_count,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' THEN d.qty ELSE 0 END) AS ordered_qty,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' THEN COALESCE(done.processed_qty, 0) ELSE 0 END) AS processed_qty,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' AND ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) > 0 THEN ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) ELSE 0 END) AS outstanding_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'commande o';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = o.fk_soc';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'commandedet d ON d.fk_commande = o.rowid';
	$sql .= ' LEFT JOIN (SELECT ed.fk_elementdet, SUM(ed.qty) AS processed_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'expeditiondet ed';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'expedition e ON e.rowid = ed.fk_expedition';
	$sql .= " WHERE e.entity IN (".$movementEntitySql.") AND e.fk_statut >= 0 AND ed.element_type = 'commande'";
	$sql .= ' GROUP BY ed.fk_elementdet) done ON done.fk_elementdet = d.rowid';
	$sql .= ' WHERE o.entity IN ('.$entitySql.') AND o.fk_statut IN (1, 2)';
} else {
	$title = $langs->trans('PIGetItemsFromPurchaseOrder');
	$orderLabel = $langs->trans('SupplierOrder');
	$partyLabel = $langs->trans('Supplier');
	$dateLabel = $langs->trans('OrderDate');
	$processedLabel = $langs->trans('QtyReceived');
	$backUrl = $destinationId > 0 ? DOL_URL_ROOT.'/reception/card.php?id='.(int) $destinationId : DOL_URL_ROOT.'/reception/list.php?leftmenu=receptions';
	$lineCondition = $stockServices ? '1=1' : 'd.product_type = 0';
	$sql = 'SELECT o.rowid, o.ref, o.ref_supplier AS thirdparty_ref, o.date_commande AS order_date,';
	$sql .= ' s.nom AS thirdparty_name, COUNT(DISTINCT d.rowid) AS line_count,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' THEN d.qty ELSE 0 END) AS ordered_qty,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' THEN COALESCE(done.processed_qty, 0) ELSE 0 END) AS processed_qty,';
	$sql .= ' SUM(CASE WHEN '.$lineCondition.' AND ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) > 0 THEN ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) ELSE 0 END) AS outstanding_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'commande_fournisseur o';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = o.fk_soc';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'commande_fournisseurdet d ON d.fk_commande = o.rowid';
	$sql .= ' LEFT JOIN (SELECT rd.fk_elementdet, SUM(rd.qty) AS processed_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'receptiondet_batch rd';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'reception r ON r.rowid = rd.fk_reception';
	$sql .= " WHERE r.entity IN (".$movementEntitySql.") AND r.fk_statut >= 0 AND rd.element_type IN ('supplier_order', 'order_supplier')";
	$sql .= ' GROUP BY rd.fk_elementdet) done ON done.fk_elementdet = d.rowid';
	$sql .= ' WHERE o.entity IN ('.$entitySql.') AND o.fk_statut IN (3, 4, 5)';
}

if ($destination) {
	$sql .= ' AND o.fk_soc = '.((int) $destination->socid);
}

if ($search !== '') {
	$escapedSearch = $db->escape($search);
	$sql .= " AND (o.ref LIKE '%".$escapedSearch."%' OR s.nom LIKE '%".$escapedSearch."%' OR ";
	$sql .= ($isShipment ? 'o.ref_client' : 'o.ref_supplier')." LIKE '%".$escapedSearch."%')";
}
$sql .= ' GROUP BY o.rowid, o.ref, '.($isShipment ? 'o.ref_client' : 'o.ref_supplier').', o.date_commande, s.nom';
$sql .= ' HAVING SUM(CASE WHEN '.$lineCondition.' AND ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) > 0 THEN ROUND(d.qty - COALESCE(done.processed_qty, 0), 5) ELSE 0 END) > 0';
$sql .= ' ORDER BY o.date_commande DESC, o.rowid DESC';
$sql .= $db->plimit($limit + 1, $offset);

$rows = array();
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}
while ($resql && ($row = $db->fetch_object($resql))) {
	$rows[] = $row;
}
$hasNext = count($rows) > $limit;
if ($hasNext) {
	array_pop($rows);
}

llxHeader('', $title);

print load_fiche_titre($title, '<a class="butAction" href="'.dol_escape_htmltag($backUrl).'">'.$langs->trans($destination ? 'Back' : 'BackToList').'</a>', $isShipment ? 'shipment' : 'dollyrevert');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('PIOutstandingOrders').'. '.$langs->trans($isShipment ? 'PIShipmentSourceHelp' : 'PIReceptionSourceHelp').'</div>';

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="target" value="'.dol_escape_htmltag($target).'">';
if ($destinationId > 0) {
	print '<input type="hidden" name="destination_id" value="'.(int) $destinationId.'">';
}
print '<div class="liste_titre liste_titre_bydiv centpercent">';
print '<div class="divsearchfield">'.$langs->trans('Search').' <input type="text" class="minwidth300" name="search" value="'.dol_escape_htmltag($search).'" placeholder="Ref / '.$partyLabel.'"></div>';
print '<div class="divsearchfield"><button class="button" type="submit">'.$langs->trans('Search').'</button></div>';
print '</div></form>';

if (!$isShipment && $orderId > 0) {
	$purchaseOrder = new CommandeFournisseur($db);
	$validOrder = $purchaseOrder->fetch($orderId) > 0
		&& (int) $purchaseOrder->entity === (int) $conf->entity
		&& in_array((int) $purchaseOrder->status, array(3, 4, 5), true);
	if ($validOrder && $destination && (int) $purchaseOrder->socid !== (int) $destination->socid) {
		$validOrder = false;
	}
	$sourceLines = $validOrder ? $getReceptionLines($orderId) : array();
	if (!$validOrder) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	} else {
		$formproduct = new FormProduct($db);
		$warehouseIds = array();
		$warehouseSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'entrepot WHERE entity IN ('.getEntity('stock').') AND statut = 1 ORDER BY rowid';
		$warehouseResult = $db->query($warehouseSql);
		while ($warehouseResult && ($warehouse = $db->fetch_object($warehouseResult))) {
			$warehouseIds[] = (int) $warehouse->rowid;
		}
		$onlyWarehouseId = count($warehouseIds) === 1 ? $warehouseIds[0] : 0;
		$formAction = $destinationId > 0
			? $_SERVER['PHP_SELF']
			: DOL_URL_ROOT.'/reception/card.php?originid='.(int) $orderId.'&origin=supplierorder';
		print '<div class="fichecenter pi-get-items-detail">';
		print load_fiche_titre($langs->trans('PIItemsFromOrder', $purchaseOrder->ref), '', 'order');
		print '<form method="POST" action="'.dol_escape_htmltag($formAction).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="'.($destinationId > 0 ? 'import_reception' : 'create').'">';
		print '<input type="hidden" name="target" value="reception">';
		print '<input type="hidden" name="order_id" value="'.(int) $orderId.'">';
		if ($destinationId > 0) {
			print '<input type="hidden" name="destination_id" value="'.(int) $destinationId.'">';
		}
		print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Product').'</th><th class="right">'.$langs->trans('PIOrderedQty').'</th>';
		print '<th class="right">'.$langs->trans('QtyReceived').'</th><th class="right">'.$langs->trans('PIOutstandingQty').'</th>';
		print '<th class="right">'.$langs->trans('QtyToReceive').'</th><th>'.$langs->trans('Warehouse').'</th>';
		print '<th>'.$langs->trans('batch_number').'</th></tr>';
		if (!$sourceLines) {
			print '<tr><td colspan="7" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
		}
		$lineIndex = 0;
		foreach ($sourceLines as $sourceLine) {
			$suffix = '0_'.$lineIndex;
			$defaultWarehouse = (int) $sourceLine->fk_default_warehouse ?: $onlyWarehouseId;
			$productField = !empty($sourceLine->tobatch) ? 'product_batch_'.$suffix : 'product_'.$suffix;
			$costPrice = price2num((float) $sourceLine->subprice * (100 - (float) $sourceLine->remise_percent) / 100, 'MU');
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($sourceLine->product_ref.' - '.$sourceLine->product_label).'</td>';
			print '<td class="right">'.price($sourceLine->ordered_qty).'</td>';
			print '<td class="right">'.price($sourceLine->processed_qty).'</td>';
			print '<td class="right"><strong>'.price($sourceLine->outstanding_qty).'</strong></td>';
			print '<td class="right"><input class="width75 right" type="number" step="any" min="0" max="'.dol_escape_htmltag($sourceLine->outstanding_qty).'" name="qty_'.$suffix.'" value="'.dol_escape_htmltag($sourceLine->outstanding_qty).'"></td>';
			print '<td>'.$formproduct->selectWarehouses($defaultWarehouse, 'entrepot_'.$suffix, '', 1, 0, (int) $sourceLine->fk_product, '', 1, 0, array(), 'minwidth200').'</td>';
			print '<td>';
			if (!empty($sourceLine->tobatch)) {
				print '<input class="minwidth150" type="text" name="lot_number_'.$suffix.'" value="" placeholder="'.$langs->trans('batch_number').'">';
				if (!getDolGlobalString('PRODUCT_DISABLE_SELLBY')) {
					print '<br>'.$langs->trans('SellByDate').' '.$form->selectDate('', 'dlc_'.$suffix, 0, 0, 1, '');
				}
				if (!getDolGlobalString('PRODUCT_DISABLE_EATBY')) {
					print '<br>'.$langs->trans('EatByDate').' '.$form->selectDate('', 'dluo_'.$suffix, 0, 0, 1, '');
				}
			} else {
				print '<span class="opacitymedium">'.$langs->trans('ProductDoesNotUseBatchSerial').'</span>';
			}
			print '<input type="hidden" name="'.$productField.'" value="'.(int) $sourceLine->fk_product.'">';
			print '<input type="hidden" name="fk_commandefourndet_'.$suffix.'" value="'.(int) $sourceLine->rowid.'">';
			print '<input type="hidden" name="pu_'.$suffix.'" value="'.dol_escape_htmltag($costPrice).'">';
			print '</td></tr>';
			$lineIndex++;
		}
		print '</table></div>';
		if ($sourceLines) {
			print '<div class="center tabsAction"><button class="butAction" type="submit">'.$langs->trans('PIGetSelectedItems').'</button> ';
			print '<a class="butActionCancel" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?target=reception'.($destinationId > 0 ? '&destination_id='.(int) $destinationId : '')).'">'.$langs->trans('Cancel').'</a></div>';
		}
		print '</form></div><br>';
		llxFooter();
		$db->close();
		exit;
	}
}

print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$orderLabel.'</th><th>'.$partyLabel.'</th><th>'.$dateLabel.'</th><th class="center">'.$langs->trans('Lines').'</th>';
print '<th class="right">'.$langs->trans('PIOrderedQty').'</th><th class="right">'.$processedLabel.'</th><th class="right">'.$langs->trans('PIOutstandingQty').'</th><th></th>';
print '</tr>';

if (!$rows) {
	print '<tr><td colspan="8" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
foreach ($rows as $row) {
	$ordered = (float) $row->ordered_qty;
	$processed = (float) $row->processed_qty;
	$outstanding = (float) $row->outstanding_qty;
	$createUrl = $isShipment
		? DOL_URL_ROOT.'/expedition/card.php?action=create&origin=commande&origin_id='.(int) $row->rowid
		: $_SERVER['PHP_SELF'].'?target=reception&order_id='.(int) $row->rowid.($destinationId > 0 ? '&destination_id='.(int) $destinationId : '');
	$orderUrl = $isShipment
		? DOL_URL_ROOT.'/commande/card.php?id='.(int) $row->rowid
		: DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int) $row->rowid;
	print '<tr class="oddeven">';
	print '<td><a href="'.dol_escape_htmltag($orderUrl).'">'.img_object('', 'order').' '.dol_escape_htmltag($row->ref).'</a>';
	if (!empty($row->thirdparty_ref)) {
		print '<br><span class="opacitymedium">'.dol_escape_htmltag($row->thirdparty_ref).'</span>';
	}
	print '</td><td>'.dol_escape_htmltag($row->thirdparty_name).'</td>';
	print '<td>'.dol_print_date($db->jdate($row->order_date), 'day').'</td>';
	print '<td class="center">'.(int) $row->line_count.'</td>';
	print '<td class="right">'.price($ordered).'</td><td class="right">'.price($processed).'</td><td class="right"><strong>'.price($outstanding).'</strong></td>';
	print '<td class="right"><a class="butAction small" href="'.dol_escape_htmltag($createUrl).'">'.$langs->trans('PISelectOrder').'</a></td>';
	print '</tr>';
}
print '</table></div>';

if ($page > 0 || $hasNext) {
	$base = $_SERVER['PHP_SELF'].'?target='.urlencode($target).'&search='.urlencode($search).'&limit='.$limit.($destinationId > 0 ? '&destination_id='.(int) $destinationId : '');
	print '<div class="pagination">';
	if ($page > 0) {
		print '<a class="button" href="'.dol_escape_htmltag($base.'&page='.($page - 1)).'">&laquo; '.$langs->trans('Previous').'</a> ';
	}
	if ($hasNext) {
		print '<a class="button" href="'.dol_escape_htmltag($base.'&page='.($page + 1)).'">'.$langs->trans('Next').' &raquo;</a>';
	}
	print '</div>';
}

llxFooter();
$db->close();

