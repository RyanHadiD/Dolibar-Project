<?php

require_once dirname(__DIR__, 3).'/main.inc.php';
require_once __DIR__.'/../class/printformat.class.php';

if (!isModEnabled('printformat') || !empty($user->socid) || !$user->hasRight('printformat', 'read')) {
	accessforbidden();
}

$langs->load('printformat@printformat');
$printFormatRepository = new PrintFormat($db, $conf->entity);

function pfEscape($value)
{
	return dol_escape_htmltag((string) $value);
}

function pfUrl($path)
{
	return dol_buildpath('/printformat/'.$path, 1);
}

function pfHeader($title, $withCodeEditor = false)
{
	$moreJs = array();
	if ($withCodeEditor) {
		$moreJs = array(
			'/includes/ace/src/ace.js',
			'/includes/ace/src/mode-twig.js',
			'/includes/ace/src/mode-css.js',
			'/includes/ace/src/theme-chrome.js',
			'/includes/ace/src/ext-beautify.js',
		);
	}
	llxHeader('', $title, '', '', 0, 0, $moreJs, array('/printformat/css/printformat.css?v=0.4.0'));
	print load_fiche_titre($title, '', 'file-pdf');
}

function pfEnd()
{
	global $db;
	llxFooter();
	$db->close();
}

function pfPost()
{
	global $user;
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !GETPOST('token', 'alpha') || !hash_equals((string) ($_SESSION['token'] ?? ''), GETPOST('token', 'alpha'))) {
		accessforbidden('Permintaan atau token tidak valid.');
	}
	if (!$user->hasRight('printformat', 'write')) {
		accessforbidden();
	}
}

function pfPostValue($name, $default = '')
{
	if (!isset($_POST[$name]) || !is_scalar($_POST[$name])) {
		return $default;
	}
	return (string) $_POST[$name];
}

function pfFields($doctype = '')
{
	$groups = array(
		'Document' => array('doc.ref', 'doc.label', 'doc.description | nl2br', 'doc.customer_ref', 'doc.date | date', 'doc.due_date | date', 'doc.delivery_date | date', 'doc.date_start | date', 'doc.date_end | date', 'doc.status', 'doc.type', 'doc.currency', 'doc.note_public | nl2br', 'doc.note_private | nl2br', 'doc.payment_terms', 'doc.payment_method', 'doc.warehouse', 'doc.warehouse_ref', 'doc.author', 'doc.validator', 'doc.approver', 'doc.extrafields.field_name'),
		'Related party' => array('party.name', 'party.address | nl2br', 'party.zip', 'party.town', 'party.country', 'party.email', 'party.phone', 'party.vat_number', 'party.customer_code', 'party.supplier_code', 'party.extrafields.field_name'),
		'Company' => array('company.name', 'company.logo', 'company.address | nl2br', 'company.zip', 'company.town', 'company.country', 'company.email', 'company.phone', 'company.vat_number'),
		'Totals' => array('totals.excl_tax | money', 'totals.tax | money', 'totals.incl_tax | money', 'totals.payments | money', 'totals.credit_notes | money', 'totals.deposits | money', 'totals.paid | money', 'totals.balance | money'),
		'Line (inside loop)' => array('line.ref', 'line.sku', 'line.label', 'line.product_ref', 'line.product_label', 'line.description | nl2br', 'line.note | nl2br', 'line.qty | number', 'line.qty_ordered | number', 'line.qty_shipped | number', 'line.unit', 'line.unit_code', 'line.unit_label', 'line.unit_short_label', 'line.unit_price | money', 'line.discount_percent | number', 'line.vat_rate | number', 'line.total_excl_tax | money', 'line.total_tax | money', 'line.total_incl_tax | money', 'line.date_start | date', 'line.date_end | date', 'line.extrafields.field_name', 'loop.index'),
	);

	if ($doctype === '') return $groups;
	try {
		global $db, $conf;
		$definition = PrintFormatDoctypeRegistry::get($doctype);
		require_once DOL_DOCUMENT_ROOT.$definition['class_file'];
		$className = $definition['class_name'];
		$sample = new $className($db);
		$documentFields = array();
		foreach ((array) ($sample->fields ?? array()) as $field => $metadata) {
			if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $field)) $documentFields[] = 'doc.'.$field;
		}

		$table = $db->prefix().$definition['table'];
		$columns = array();
		$result = $db->query('SHOW COLUMNS FROM '.$table);
		while ($result && ($column = $db->fetch_object($result))) $columns[$column->Field] = true;
		$sql = 'SELECT rowid FROM '.$table.(isset($columns['entity']) ? ' WHERE entity='.(int) $conf->entity : '').' ORDER BY rowid DESC LIMIT 1';
		$result = $db->query($sql);
		$row = $result ? $db->fetch_object($result) : null;
		$lineFields = array();
		if ($row && $sample->fetch((int) $row->rowid) > 0) {
			if (empty($sample->lines)) {
				if (method_exists($sample, 'fetch_lines')) $sample->fetch_lines();
				elseif (method_exists($sample, 'getLinesArray')) $sample->getLinesArray();
			}
			foreach ((array) ($sample->array_options ?? array()) as $field => $value) {
				$documentFields[] = 'doc.extrafields.'.preg_replace('/^options_/', '', $field);
			}
			$firstLine = !empty($sample->lines) ? reset($sample->lines) : null;
			if (is_object($firstLine)) {
				$fieldNames = array_keys((array) ($firstLine->fields ?? array()));
				foreach (get_object_vars($firstLine) as $field => $value) {
					if (is_scalar($value) || $value === null) $fieldNames[] = $field;
				}
				$fieldNames = array_unique($fieldNames);
				foreach ($fieldNames as $field) {
					if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $field)) $lineFields[] = 'line.'.$field;
				}
				foreach ((array) ($firstLine->array_options ?? array()) as $field => $value) {
					$lineFields[] = 'line.extrafields.'.preg_replace('/^options_/', '', $field);
				}
			}
		}
		$groups['Document fields: '.$definition['label']] = array_values(array_unique($documentFields));
		if ($lineFields) $groups['Line fields: '.$definition['label']] = array_values(array_unique($lineFields));
	} catch (Throwable $e) {
		// Keep the stable common field list if discovery is unavailable.
	}
	return $groups;
}

function pfFetchObject($doctype, $id)
{
	global $db, $conf;
	$definition = PrintFormatDoctypeRegistry::get($doctype);
	require_once DOL_DOCUMENT_ROOT.$definition['class_file'];
	$className = $definition['class_name'];
	$object = new $className($db);
	if ($object->fetch((int) $id) <= 0) {
		throw new DomainException($definition['label'].' tidak ditemukan.');
	}
	if (isset($object->entity) && (int) $object->entity !== (int) $conf->entity) {
		throw new DomainException('Dokumen berasal dari entity lain.');
	}
	return $object;
}

function pfObjectChoices($doctype)
{
	global $db, $conf;
	$definition = PrintFormatDoctypeRegistry::get($doctype);
	$table = MAIN_DB_PREFIX.$definition['table'];
	$columns = array();
	$result = $db->query('SHOW COLUMNS FROM '.$table);
	if (!$result) {
		return array();
	}
	while ($column = $db->fetch_object($result)) {
		$columns[$column->Field] = true;
	}
	$labelColumn = '';
	foreach (array('ref', 'code', 'label', 'name', 'nom', 'login', 'title', 'subject') as $candidate) {
		if (isset($columns[$candidate])) {
			$labelColumn = $candidate;
			break;
		}
	}
	if (!$labelColumn) {
		$labelColumn = 'rowid';
	}
	$sql = 'SELECT rowid,'.$labelColumn.' AS display_label FROM '.$table;
	if (isset($columns['entity'])) {
		$sql .= ' WHERE entity='.(int) $conf->entity;
	}
	$sql .= ' ORDER BY rowid DESC LIMIT 50';
	$result = $db->query($sql);
	if (!$result) {
		return array();
	}
	$rows = array();
	while ($row = $db->fetch_object($result)) {
		$rows[(int) $row->rowid] = (string) $row->display_label;
	}
	return $rows;
}
