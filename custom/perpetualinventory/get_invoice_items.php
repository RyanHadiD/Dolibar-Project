<?php

/** Pull outstanding Shipment/Reception lines into an existing draft invoice. */

define('CSRFCHECK_WITH_TOKEN', 1);
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

$langs->loadLangs(array('bills', 'sendings', 'receptions', 'companies', 'products', 'perpetualinventory@perpetualinventory'));

$target = GETPOST('target', 'alpha');
if (!in_array($target, array('customer_invoice', 'supplier_invoice'), true)) {
	accessforbidden();
}
$isSupplier = $target === 'supplier_invoice';
$destinationId = GETPOSTINT('destination_id');
$sourceId = GETPOSTINT('source_id');
$action = GETPOST('action', 'aZ09');
$permission = $isSupplier
	? ($user->admin || $user->hasRight('fournisseur', 'facture', 'creer') || $user->hasRight('supplier_invoice', 'creer'))
	: ($user->admin || $user->hasRight('facture', 'creer'));
if (!isModEnabled('perpetualinventory') || !$permission || $user->socid || $destinationId <= 0) {
	accessforbidden();
}

$destination = $isSupplier ? new FactureFournisseur($db) : new Facture($db);
if ($destination->fetch($destinationId) <= 0 || (int) $destination->entity !== (int) $conf->entity) {
	accessforbidden();
}
if ((int) $destination->status !== 0 || (int) $destination->type !== 0) {
	setEventMessages($langs->trans('PIInvoiceImportDraftOnly'), null, 'errors');
	$invoiceUrl = $isSupplier ? DOL_URL_ROOT.'/fourn/facture/card.php?id='.(int) $destinationId : DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $destinationId;
	header('Location: '.$invoiceUrl);
	exit;
}

$destination->fetch_thirdparty();
$search = trim(GETPOST('search', 'restricthtml'));
$page = max(0, GETPOSTINT('page'));
$limit = max(10, min(100, GETPOSTINT('limit') ?: 25));
$offset = $page * $limit;
$invoiceUrl = $isSupplier ? DOL_URL_ROOT.'/fourn/facture/card.php?id='.(int) $destinationId : DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $destinationId;
$sourceTable = $isSupplier ? 'reception' : 'expedition';
$sourceLineTable = $isSupplier ? 'receptiondet_batch' : 'expeditiondet';
$sourceForeignKey = $isSupplier ? 'fk_reception' : 'fk_expedition';
$orderLineTable = $isSupplier ? 'commande_fournisseurdet' : 'commandedet';
$sourceElement = $isSupplier ? 'reception' : 'shipping';
$sourceLineType = $isSupplier ? 'reception_line' : 'shipping_line';
$targetLineType = $isSupplier ? 'invoice_supplier_line' : 'invoice_line';
$invoiceTable = $isSupplier ? 'facture_fourn' : 'facture';
$invoiceLineTable = $isSupplier ? 'facture_fourn_det' : 'facturedet';
$invoiceLineForeignKey = $isSupplier ? 'fk_facture_fourn' : 'fk_facture';
$invoiceTargetType = $isSupplier ? 'invoice_supplier' : 'facture';
$entitySql = getEntity($sourceTable);
$baseCurrency = strtoupper((string) $conf->currency);
$destinationCurrency = strtoupper((string) ($destination->multicurrency_code ?: $baseCurrency));
$currencyCondition = "COALESCE(NULLIF(UPPER(d.multicurrency_code),''), '".$db->escape($baseCurrency)."')='".$db->escape($destinationCurrency)."'";
$customBilledSql = '(SELECT ee.fk_source,SUM(il.qty) qty FROM '.MAIN_DB_PREFIX.'element_element ee';
$customBilledSql .= ' JOIN '.MAIN_DB_PREFIX.$invoiceLineTable.' il ON il.rowid=ee.fk_target';
$customBilledSql .= ' JOIN '.MAIN_DB_PREFIX.$invoiceTable.' inv ON inv.rowid=il.'.$invoiceLineForeignKey;
$customBilledSql .= " WHERE ee.sourcetype='".$db->escape($sourceLineType)."' AND ee.targettype='".$db->escape($targetLineType)."' AND inv.fk_statut IN (0,1,2)";
$customBilledSql .= ' GROUP BY ee.fk_source) billed';
// Older invoices created through Dolibarr's native "create from" flow only
// retain a document-level link. Treat those source documents as fully billed;
// new imports have line links and can therefore expose a precise remainder.
$legacyBilledSql = '(SELECT DISTINCT doclink.fk_source FROM '.MAIN_DB_PREFIX.'element_element doclink';
$legacyBilledSql .= ' JOIN '.MAIN_DB_PREFIX.$invoiceTable.' legacyinv ON legacyinv.rowid=doclink.fk_target';
$legacyBilledSql .= " WHERE doclink.sourcetype='".$db->escape($sourceElement)."' AND doclink.targettype='".$db->escape($invoiceTargetType)."' AND legacyinv.fk_statut IN (0,1,2)";
$legacyBilledSql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.$invoiceLineTable.' legacyexistingline WHERE legacyexistingline.'.$invoiceLineForeignKey.'=legacyinv.rowid)';
$legacyBilledSql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'element_element linelink';
$legacyBilledSql .= ' JOIN '.MAIN_DB_PREFIX.$sourceLineTable.' legacysl ON legacysl.rowid=linelink.fk_source AND legacysl.'.$sourceForeignKey.'=doclink.fk_source';
$legacyBilledSql .= ' JOIN '.MAIN_DB_PREFIX.$invoiceLineTable.' legacyil ON legacyil.rowid=linelink.fk_target AND legacyil.'.$invoiceLineForeignKey.'=legacyinv.rowid';
$legacyBilledSql .= " WHERE linelink.sourcetype='".$db->escape($sourceLineType)."' AND linelink.targettype='".$db->escape($targetLineType)."')) legacy";
$billedQtySql = 'CASE WHEN legacy.fk_source IS NOT NULL THEN sl.qty ELSE COALESCE(billed.qty,0) END';

/** Return source lines with their latest invoice availability. */
$getSourceLines = function ($documentId) use ($db, $isSupplier, $sourceTable, $sourceLineTable, $sourceForeignKey, $orderLineTable, $customBilledSql, $legacyBilledSql, $billedQtySql, $currencyCondition, $entitySql) {
	$priceColumn = $isSupplier ? 'd.subprice' : 'd.subprice';
	$description = 'COALESCE(NULLIF(d.description, \'\'), NULLIF(sl.description, \'\'), p.label, p.ref)';
	$sql = 'SELECT sl.rowid, sl.'.$sourceForeignKey.' AS source_id, sl.qty AS source_qty, COALESCE(sl.fk_product,d.fk_product) AS fk_product, COALESCE(sl.fk_unit,d.fk_unit) AS fk_unit,';
	$sql .= ' '.$description.' AS description, p.ref AS product_ref, p.label AS product_label,';
	$sql .= ' '.$priceColumn.' AS subprice, d.multicurrency_subprice, d.remise_percent, d.tva_tx, d.vat_src_code, d.localtax1_tx, d.localtax2_tx,';
	$sql .= ' d.product_type, d.date_start, d.date_end, d.info_bits, d.special_code, d.rang,';
	$sql .= $isSupplier ? " d.ref AS ref_supplier," : ' d.fk_product_fournisseur_price AS fk_fournprice, d.buy_price_ht AS pa_ht,';
	$sql .= ' '.$billedQtySql.' AS billed_qty,';
	$sql .= ' CASE WHEN ROUND(sl.qty - '.$billedQtySql.', 5) > 0 THEN ROUND(sl.qty - '.$billedQtySql.', 5) ELSE 0 END AS outstanding_qty';
	$sql .= ' FROM '.MAIN_DB_PREFIX.$sourceLineTable.' sl';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.$sourceTable.' src ON src.rowid=sl.'.$sourceForeignKey;
	$sql .= ' JOIN '.MAIN_DB_PREFIX.$orderLineTable.' d ON d.rowid=sl.fk_elementdet';
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=sl.fk_product';
	$sql .= ' LEFT JOIN '.$customBilledSql.' ON billed.fk_source=sl.rowid';
	$sql .= ' LEFT JOIN '.$legacyBilledSql.' ON legacy.fk_source=src.rowid';
	$sql .= ' WHERE src.entity IN ('.$entitySql.') AND src.fk_statut IN (1,2,3) AND '.$currencyCondition.' AND sl.'.$sourceForeignKey.'='.(int) $documentId;
	$sql .= ' ORDER BY sl.rang, sl.rowid';
	$lines = array();
	$resql = $db->query($sql);
	while ($resql && ($line = $db->fetch_object($resql))) {
		$lines[(int) $line->rowid] = $line;
	}
	return $lines;
};

if ($action === 'import_items' && $sourceId > 0) {
	$selected = array();
	foreach ($_POST as $key => $value) {
		if (preg_match('/^qty_([0-9]+)$/', $key, $matches)) {
			$qty = price2num(GETPOST($key), 'MS');
			if ($qty > 0) {
				$selected[(int) $matches[1]] = (float) $qty;
			}
		}
	}
	if (!$selected) {
		setEventMessages($langs->trans('PISelectAtLeastOneItem'), null, 'errors');
	} else {
		$db->begin();
		$error = 0;
		$destinationCheckSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$invoiceTable.' WHERE rowid='.(int) $destinationId.' AND entity='.(int) $conf->entity.' AND fk_soc='.(int) $destination->socid.' AND fk_statut=0 AND type=0 FOR UPDATE';
		$destinationCheck = $db->query($destinationCheckSql);
		if (!$destinationCheck || !$db->num_rows($destinationCheck)) {
			setEventMessages($langs->trans('PIInvoiceImportDraftOnly'), null, 'errors');
			$error++;
		}
		$sourceCheckSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$sourceTable.' WHERE rowid='.(int) $sourceId.' AND entity IN ('.$entitySql.') AND fk_soc='.(int) $destination->socid.' AND fk_statut IN (1,2,3) FOR UPDATE';
		$sourceCheck = $db->query($sourceCheckSql);
		if (!$sourceCheck || !$db->num_rows($sourceCheck)) {
			setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
			$error++;
		}
		$available = $error ? array() : $getSourceLines($sourceId);
		foreach ($selected as $lineId => $qty) {
			if ($error) {
				break;
			}
			if (empty($available[$lineId]) || $qty > (float) $available[$lineId]->outstanding_qty + 0.000001) {
				setEventMessages($langs->trans('PIQtyExceedsOutstanding'), null, 'errors');
				$error++;
				break;
			}
			$line = $available[$lineId];
			$tva = $line->tva_tx;
			if (!empty($line->vat_src_code) && strpos((string) $tva, '(') === false) {
				$tva .= ' ('.$line->vat_src_code.')';
			}
			if ($isSupplier) {
				$result = $destination->addline($line->description, $line->subprice, $tva, $line->localtax1_tx, $line->localtax2_tx, $qty, (int) $line->fk_product, $line->remise_percent, $line->date_start ? strtotime($line->date_start) : 0, $line->date_end ? strtotime($line->date_end) : 0, 0, $line->info_bits, 'HT', $line->product_type, -1, 0, array(), $line->fk_unit, $lineId, $destinationCurrency !== $baseCurrency ? $line->multicurrency_subprice : 0, $line->ref_supplier, $line->special_code, 0, 0, 'reception');
			} else {
				$result = $destination->addline($line->description, $line->subprice, $qty, $tva, $line->localtax1_tx, $line->localtax2_tx, (int) $line->fk_product, $line->remise_percent, $line->date_start ? strtotime($line->date_start) : 0, $line->date_end ? strtotime($line->date_end) : 0, 0, $line->info_bits, 0, 'HT', 0, $line->product_type, -1, $line->special_code, 'shipping', $lineId, 0, $line->fk_fournprice, $line->pa_ht, '', array(), 100, 0, $line->fk_unit, $destinationCurrency !== $baseCurrency ? $line->multicurrency_subprice : 0);
			}
			if ($result <= 0) {
				setEventMessages($destination->error, $destination->errors, 'errors');
				$error++;
				break;
			}
			$linkSql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element (fk_source,sourcetype,fk_target,targettype) VALUES (';
			$linkSql .= (int) $lineId.", '".$db->escape($sourceLineType)."', ".(int) $result.", '".$db->escape($targetLineType)."')";
			if (!$db->query($linkSql)) {
				setEventMessages($db->lasterror(), null, 'errors');
				$error++;
				break;
			}
		}
		if (!$error) {
			$linkExistsSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'element_element WHERE fk_source='.(int) $sourceId;
			$linkExistsSql .= " AND sourcetype='".$db->escape($sourceElement)."' AND fk_target=".(int) $destinationId." AND targettype='".$db->escape($invoiceTargetType)."'";
			$linkExists = $db->query($linkExistsSql);
			if (!$linkExists || !$db->num_rows($linkExists)) {
				if ($destination->add_object_linked($sourceElement, $sourceId, $user) <= 0) {
					setEventMessages($destination->error, $destination->errors, 'errors');
					$error++;
				}
			}
		}
		if (!$error) {
			$db->commit();
			setEventMessages($langs->trans('PIInvoiceItemsAdded'), null, 'mesgs');
			header('Location: '.$invoiceUrl);
			exit;
		}
		$db->rollback();
	}
}

$dateColumn = $isSupplier ? 'COALESCE(src.date_reception,src.date_delivery)' : 'src.date_delivery';
$referenceColumn = $isSupplier ? 'src.ref_supplier' : 'src.ref_customer';
$sql = 'SELECT src.rowid,src.ref,'.$referenceColumn.' AS thirdparty_ref,'.$dateColumn.' AS source_date,s.nom AS thirdparty_name,';
$sql .= ' COUNT(DISTINCT sl.rowid) AS line_count,SUM(sl.qty) AS source_qty,SUM('.$billedQtySql.') AS billed_qty,';
$sql .= ' SUM(CASE WHEN ROUND(sl.qty-'.$billedQtySql.',5)>0 THEN ROUND(sl.qty-'.$billedQtySql.',5) ELSE 0 END) AS outstanding_qty';
$sql .= ' FROM '.MAIN_DB_PREFIX.$sourceTable.' src JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=src.fk_soc';
$sql .= ' JOIN '.MAIN_DB_PREFIX.$sourceLineTable.' sl ON sl.'.$sourceForeignKey.'=src.rowid';
$sql .= ' JOIN '.MAIN_DB_PREFIX.$orderLineTable.' d ON d.rowid=sl.fk_elementdet';
$sql .= ' LEFT JOIN '.$customBilledSql.' ON billed.fk_source=sl.rowid';
$sql .= ' LEFT JOIN '.$legacyBilledSql.' ON legacy.fk_source=src.rowid';
$sql .= ' WHERE src.entity IN ('.$entitySql.') AND src.fk_soc='.(int) $destination->socid.' AND src.fk_statut IN (1,2,3) AND '.$currencyCondition;
if ($search !== '') {
	$escaped = $db->escape($search);
	$sql .= " AND (src.ref LIKE '%".$escaped."%' OR s.nom LIKE '%".$escaped."%' OR ".$referenceColumn." LIKE '%".$escaped."%')";
}
$sql .= ' GROUP BY src.rowid,src.ref,'.$referenceColumn.','.$dateColumn.',s.nom';
$sql .= ' HAVING SUM(CASE WHEN ROUND(sl.qty-'.$billedQtySql.',5)>0 THEN ROUND(sl.qty-'.$billedQtySql.',5) ELSE 0 END)>0';
$sql .= ' ORDER BY '.$dateColumn.' DESC,src.rowid DESC'.$db->plimit($limit + 1, $offset);
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

$title = $langs->trans($isSupplier ? 'PIGetItemsFromReception' : 'PIGetItemsFromShipment');
llxHeader('', $title);
print load_fiche_titre($title, '<a class="butAction" href="'.dol_escape_htmltag($invoiceUrl).'">'.$langs->trans('Back').'</a>', $isSupplier ? 'supplier_invoice' : 'bill');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans($isSupplier ? 'PISupplierInvoiceSourceHelp' : 'PICustomerInvoiceSourceHelp').'</div>';
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="target" value="'.dol_escape_htmltag($target).'"><input type="hidden" name="destination_id" value="'.(int) $destinationId.'">';
print '<div class="liste_titre liste_titre_bydiv centpercent"><div class="divsearchfield">'.$langs->trans('Search').' <input type="text" class="minwidth300" name="search" value="'.dol_escape_htmltag($search).'" placeholder="Ref / '.dol_escape_htmltag($destination->thirdparty->name).'"> <button class="button" type="submit">'.$langs->trans('Search').'</button></div></div></form>';

if ($sourceId > 0) {
	$sourceLines = $getSourceLines($sourceId);
	$sourceSql = 'SELECT ref,fk_soc FROM '.MAIN_DB_PREFIX.$sourceTable.' WHERE rowid='.(int) $sourceId.' AND entity IN ('.$entitySql.') AND fk_statut IN (1,2,3)';
	$sourceResult = $db->query($sourceSql);
	$source = $sourceResult ? $db->fetch_object($sourceResult) : null;
	if (!$source || (int) $source->fk_soc !== (int) $destination->socid) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	} else {
		print load_fiche_titre($langs->trans('PIItemsFromDocument', $source->ref), '', $isSupplier ? 'reception' : 'shipment');
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="import_items"><input type="hidden" name="target" value="'.dol_escape_htmltag($target).'">';
		print '<input type="hidden" name="destination_id" value="'.(int) $destinationId.'"><input type="hidden" name="source_id" value="'.(int) $sourceId.'">';
		print '<div class="div-table-responsive"><table class="tagtable liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Product').'</th><th class="right">'.$langs->trans($isSupplier ? 'QtyReceived' : 'QtyShipped').'</th><th class="right">'.$langs->trans('PIQtyAlreadyInvoiced').'</th><th class="right">'.$langs->trans('PIOutstandingQty').'</th><th class="right">'.$langs->trans('PIQtyToInvoice').'</th><th class="right">'.$langs->trans('UnitPriceHT').'</th></tr>';
		$availableCount = 0;
		foreach ($sourceLines as $line) {
			if ((float) $line->outstanding_qty <= 0) {
				continue;
			}
			$availableCount++;
			$product = trim($line->product_ref.' - '.$line->product_label, ' -');
			if ($product === '') {
				$product = $line->description;
			}
			print '<tr class="oddeven"><td>'.dol_escape_htmltag($product).'</td><td class="right">'.price($line->source_qty).'</td><td class="right">'.price($line->billed_qty).'</td><td class="right"><strong>'.price($line->outstanding_qty).'</strong></td>';
			print '<td class="right"><input class="width75 right" type="number" step="any" min="0" max="'.dol_escape_htmltag($line->outstanding_qty).'" name="qty_'.(int) $line->rowid.'" value="'.dol_escape_htmltag($line->outstanding_qty).'"></td><td class="right">'.price($line->subprice).'</td></tr>';
		}
		if (!$availableCount) {
			print '<tr><td colspan="6" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
		}
		print '</table></div>';
		if ($availableCount) {
			print '<div class="center tabsAction"><button class="butAction" type="submit">'.$langs->trans('PIGetSelectedItems').'</button> <a class="butActionCancel" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?target='.$target.'&destination_id='.(int) $destinationId).'">'.$langs->trans('Cancel').'</a></div>';
		}
		print '</form><br>';
		llxFooter();
		$db->close();
		exit;
	}
}

print '<div class="div-table-responsive"><table class="tagtable liste centpercent"><tr class="liste_titre"><th>'.($isSupplier ? $langs->trans('Reception') : $langs->trans('Shipment')).'</th><th>'.$langs->trans($isSupplier ? 'Supplier' : 'Customer').'</th><th>'.$langs->trans('Date').'</th><th class="center">'.$langs->trans('Lines').'</th><th class="right">'.$langs->trans($isSupplier ? 'QtyReceived' : 'QtyShipped').'</th><th class="right">'.$langs->trans('PIQtyAlreadyInvoiced').'</th><th class="right">'.$langs->trans('PIOutstandingQty').'</th><th></th></tr>';
if (!$rows) {
	print '<tr><td colspan="8" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
foreach ($rows as $row) {
	$sourceUrl = $isSupplier ? DOL_URL_ROOT.'/reception/card.php?id='.(int) $row->rowid : DOL_URL_ROOT.'/expedition/card.php?id='.(int) $row->rowid;
	$selectUrl = $_SERVER['PHP_SELF'].'?target='.$target.'&destination_id='.(int) $destinationId.'&source_id='.(int) $row->rowid;
	print '<tr class="oddeven"><td><a href="'.dol_escape_htmltag($sourceUrl).'">'.img_object('', $isSupplier ? 'reception' : 'shipment').' '.dol_escape_htmltag($row->ref).'</a>';
	if (!empty($row->thirdparty_ref)) {
		print '<br><span class="opacitymedium">'.dol_escape_htmltag($row->thirdparty_ref).'</span>';
	}
	print '</td><td>'.dol_escape_htmltag($row->thirdparty_name).'</td><td>'.dol_print_date($db->jdate($row->source_date), 'day').'</td><td class="center">'.(int) $row->line_count.'</td><td class="right">'.price($row->source_qty).'</td><td class="right">'.price($row->billed_qty).'</td><td class="right"><strong>'.price($row->outstanding_qty).'</strong></td><td class="right"><a class="butAction" href="'.dol_escape_htmltag($selectUrl).'">'.$langs->trans('PISelectSource').'</a></td></tr>';
}
print '</table></div>';
$previous = $_SERVER['PHP_SELF'].'?target='.$target.'&destination_id='.(int) $destinationId.'&search='.urlencode($search).'&page='.max(0, $page - 1);
$next = $_SERVER['PHP_SELF'].'?target='.$target.'&destination_id='.(int) $destinationId.'&search='.urlencode($search).'&page='.($page + 1);
print '<div class="center marginbottomonly margintoponly">';
if ($page > 0) {
	print '<a class="button" href="'.dol_escape_htmltag($previous).'">&laquo; '.$langs->trans('Previous').'</a> ';
}
if ($hasNext) {
	print '<a class="button" href="'.dol_escape_htmltag($next).'">'.$langs->trans('Next').' &raquo;</a>';
}
print '</div>';
llxFooter();
$db->close();
