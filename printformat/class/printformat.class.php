<?php

/**
 * Native Dolibarr object types supported by the shared print engine.
 *
 * Reports, labels and accounting exports are intentionally excluded: they are
 * report generators rather than documents attached to a CommonObject record.
 */
class PrintFormatDoctypeRegistry
{
	public static function all()
	{
		return array(
			'invoice' => self::item('Customer Invoice', 'invoice', array('facture'), 'facture', 'core/modules/facture/doc/', '/compta/facture/class/facture.class.php', 'Facture', 'facture'),
			'supplier_invoice' => self::item('Supplier Invoice', 'invoice_supplier', array('facture_fournisseur', 'supplier_invoice'), 'invoice_supplier', 'core/modules/supplier_invoice/doc/', '/fourn/class/fournisseur.facture.class.php', 'FactureFournisseur', 'facture_fourn'),
			'proposal' => self::item('Customer Proposal', 'propal', array('propal'), 'propal', 'core/modules/propale/doc/', '/comm/propal/class/propal.class.php', 'Propal', 'propal'),
			'supplier_proposal' => self::item('Supplier Proposal', 'supplier_proposal', array('supplier_proposal'), 'supplier_proposal', 'core/modules/supplier_proposal/doc/', '/supplier_proposal/class/supplier_proposal.class.php', 'SupplierProposal', 'supplier_proposal'),
			'order' => self::item('Customer Order', 'order', array('commande'), 'commande', 'core/modules/commande/doc/', '/commande/class/commande.class.php', 'Commande', 'commande'),
			'supplier_order' => self::item('Supplier Order', 'order_supplier', array('commande_fournisseur', 'supplier_order'), 'order_supplier', 'core/modules/supplier_order/doc/', '/fourn/class/fournisseur.commande.class.php', 'CommandeFournisseur', 'commande_fournisseur'),
			'shipment' => self::item('Shipment', 'shipping', array('expedition'), 'shipping', 'core/modules/expedition/doc/', '/expedition/class/expedition.class.php', 'Expedition', 'expedition'),
			'reception' => self::item('Reception', 'reception', array('reception'), 'reception', 'core/modules/reception/doc/', '/reception/class/reception.class.php', 'Reception', 'reception'),
			'delivery' => self::item('Delivery Note', 'delivery', array('delivery'), 'delivery', 'core/modules/delivery/doc/', '/delivery/class/delivery.class.php', 'Delivery', 'delivery'),
			'contract' => self::item('Contract', 'contract', array('contract'), 'contrat', 'core/modules/contract/doc/', '/contrat/class/contrat.class.php', 'Contrat', 'contrat'),
			'intervention' => self::item('Intervention', 'ficheinter', array('ficheinter'), 'fichinter', 'core/modules/fichinter/doc/', '/fichinter/class/fichinter.class.php', 'Fichinter', 'fichinter'),
			'expense_report' => self::item('Expense Report', 'expensereport', array('expensereport'), 'expensereport', 'core/modules/expensereport/doc/', '/expensereport/class/expensereport.class.php', 'ExpenseReport', 'expensereport'),
			'project' => self::item('Project', 'project', array('project'), 'project', 'core/modules/project/doc/', '/projet/class/project.class.php', 'Project', 'projet'),
			'project_task' => self::item('Project Task', 'project_task', array('project_task'), 'project_task', 'core/modules/project/task/doc/', '/projet/class/task.class.php', 'Task', 'projet_task'),
			'product' => self::item('Product or Service', 'product', array('product'), 'product', 'core/modules/product/doc/', '/product/class/product.class.php', 'Product', 'product'),
			'product_batch' => self::item('Product Batch', 'product_batch', array('product_batch'), 'productlot', 'core/modules/product_batch/doc/', '/product/stock/class/productlot.class.php', 'Productlot', 'product_lot'),
			'warehouse' => self::item('Warehouse', 'stock', array('stock'), 'stock', 'core/modules/stock/doc/', '/product/stock/class/entrepot.class.php', 'Entrepot', 'entrepot'),
			'stock_movement' => self::item('Stock Movement', 'movement', array('movement'), 'stockmouvement', 'core/modules/stock/doc/', '/product/stock/class/mouvementstock.class.php', 'MouvementStock', 'stock_mouvement'),
			'stock_transfer' => self::item('Stock Transfer', 'stocktransfer', array('stocktransfer'), 'stocktransfer', 'core/modules/stocktransfer/doc/', '/product/stock/stocktransfer/class/stocktransfer.class.php', 'StockTransfer', 'stocktransfer_stocktransfer'),
			'manufacturing_order' => self::item('Manufacturing Order', 'mrp', array('mrp-mo', 'mrp'), 'mo', 'core/modules/mrp/doc/', '/mrp/class/mo.class.php', 'Mo', 'mrp_mo'),
			'bill_of_materials' => self::item('Bill of Materials', 'bom', array('bom'), 'bom', 'core/modules/bom/doc/', '/bom/class/bom.class.php', 'BOM', 'bom_bom'),
			'ticket' => self::item('Ticket', 'ticket', array('ticket'), 'ticket', 'core/modules/ticket/doc/', '/ticket/class/ticket.class.php', 'Ticket', 'ticket'),
			'asset' => self::item('Asset', 'asset', array('asset'), 'asset', 'core/modules/asset/doc/', '/asset/class/asset.class.php', 'Asset', 'asset'),
			'thirdparty' => self::item('Third Party', 'company', array('company'), 'societe', 'core/modules/societe/doc/', '/societe/class/societe.class.php', 'Societe', 'societe'),
			'member' => self::item('Member', 'member', array('member'), 'member', 'core/modules/member/doc/', '/adherents/class/adherent.class.php', 'Adherent', 'adherent'),
			'agenda_event' => self::item('Agenda Event', 'action', array('agenda', 'actions'), 'action', 'core/modules/action/doc/', '/comm/action/class/actioncomm.class.php', 'ActionComm', 'actioncomm'),
			'supplier_payment' => self::item('Supplier Payment', 'supplier_payment', array('supplier_payment'), 'payment_supplier', 'core/modules/supplier_payment/doc/', '/fourn/class/paiementfourn.class.php', 'PaiementFourn', 'paiementfourn'),
			'evaluation' => self::item('HR Evaluation', 'evaluation', array('hrm'), 'evaluation', 'core/modules/hrm/doc/', '/hrm/class/evaluation.class.php', 'Evaluation', 'hrm_evaluation'),
			'user' => self::item('User', 'user', array('user'), 'user', 'core/modules/user/doc/', '/user/class/user.class.php', 'User', 'user'),
			'user_group' => self::item('User Group', 'group', array('usergroup'), 'usergroup', 'core/modules/usergroup/doc/', '/user/class/usergroup.class.php', 'UserGroup', 'usergroup'),
			'donation' => self::item('Donation', 'donation', array('donation'), 'don', 'core/modules/dons/', '/don/class/don.class.php', 'Don', 'don'),
			'workstation' => self::item('Workstation', 'workstation', array('workstation'), 'workstation', 'core/modules/workstation/doc/', '/workstation/class/workstation.class.php', 'Workstation', 'workstation_workstation'),
			'recruitment_job_position' => self::item('Recruitment Job Position', 'recruitmentjobposition', array('recruitment-recruitmentjobposition'), 'recruitmentjobposition', 'core/modules/recruitment/doc/', '/recruitment/class/recruitmentjobposition.class.php', 'RecruitmentJobPosition', 'recruitment_recruitmentjobposition'),
		);
	}

	private static function item($label, $modelType, $moduleparts, $element, $modelpath, $classFile, $className, $table)
	{
		return array(
			'label' => $label,
			'model_type' => $modelType,
			'moduleparts' => $moduleparts,
			'element' => $element,
			'modelpath' => $modelpath,
			'class_file' => $classFile,
			'class_name' => $className,
			'table' => $table,
		);
	}

	public static function get($doctype)
	{
		$all = self::all();
		if (empty($all[$doctype])) {
			throw new DomainException('DocType native tidak didukung: '.$doctype);
		}
		return $all[$doctype];
	}

	public static function resolve($object, $modelpath = '', $modulepart = '')
	{
		if (is_object($object) && !empty($object->element)) {
			foreach (self::all() as $key => $definition) {
				if ($definition['element'] === $object->element) {
					return $key;
				}
			}
		}
		foreach (self::all() as $key => $definition) {
			if ($modelpath && $definition['modelpath'] === $modelpath) {
				return $key;
			}
			if ($modulepart && in_array($modulepart, $definition['moduleparts'], true)) {
				return $key;
			}
		}
		return '';
	}

	public static function labels()
	{
		$result = array();
		foreach (self::all() as $key => $definition) {
			$result[$key] = $definition['label'];
		}
		return $result;
	}
}

/** Repository and validation for HTML/CSS print formats. */
class PrintFormat
{
	public $db;
	public $entity;

	public function __construct($db, $entity)
	{
		$this->db = $db;
		$this->entity = (int) $entity;
	}

	public function table()
	{
		return $this->db->prefix().'printformat';
	}

	private function str($value)
	{
		return "'".$this->db->escape((string) $value)."'";
	}

	private function query($sql)
	{
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException($this->db->lasterror());
		}
		return $result;
	}

	public function all($doctype = 'invoice', $activeOnly = false)
	{
		$sql = 'SELECT * FROM '.$this->table().' WHERE entity='.$this->entity;
		if ($doctype !== '') {
			$sql .= ' AND doctype='.$this->str($doctype);
		}
		if ($activeOnly) {
			$sql .= ' AND active=1';
		}
		$sql .= ' ORDER BY doctype ASC, is_default DESC, active DESC, name ASC';
		$result = $this->query($sql);
		$rows = array();
		while ($row = $this->db->fetch_object($result)) {
			$rows[] = $this->normalizeFormatRow($row);
		}
		return $rows;
	}

	public function get($id, $activeOnly = false)
	{
		$sql = 'SELECT * FROM '.$this->table().' WHERE entity='.$this->entity.' AND rowid='.(int) $id;
		if ($activeOnly) {
			$sql .= ' AND active=1';
		}
		$result = $this->query($sql);
		$row = $this->db->fetch_object($result);
		if (!$row) {
			throw new DomainException('Print format tidak ditemukan.');
		}
		return $this->normalizeFormatRow($row);
	}

	public function getDefault($doctype = 'invoice')
	{
		$result = $this->query('SELECT * FROM '.$this->table().' WHERE entity='.$this->entity.' AND doctype='.$this->str($doctype).' AND active=1 ORDER BY is_default DESC,name ASC LIMIT 1');
		$row = $this->db->fetch_object($result);
		if (!$row) {
			throw new DomainException('Belum ada print format aktif untuk '.$doctype.'.');
		}
		return $this->normalizeFormatRow($row);
	}

	public function save($id, $data, $userId)
	{
		$name = trim((string) ($data['name'] ?? ''));
		if ($name === '') {
			throw new DomainException('Nama format wajib diisi.');
		}
		if (strlen($name) > 128) {
			throw new DomainException('Nama format maksimal 128 karakter.');
		}
		$doctype = (string) ($data['doctype'] ?? 'invoice');
		$definition = PrintFormatDoctypeRegistry::get($doctype);
		$html = $this->sanitizeHtml($this->normalizeNewlines((string) ($data['template_html'] ?? '')));
		$css = $this->sanitizeCss($this->normalizeNewlines((string) ($data['template_css'] ?? '')));
		if (trim($html) === '') {
			throw new DomainException('Template HTML wajib diisi.');
		}
		if (strlen($html) > 1000000 || strlen($css) > 250000) {
			throw new DomainException('Ukuran template terlalu besar.');
		}
		PrintFormatRenderer::validateSyntax($html);

		$pageFormat = strtoupper((string) ($data['page_format'] ?? 'A4'));
		if (!in_array($pageFormat, array('A4', 'LETTER', 'CUSTOM'), true)) {
			$pageFormat = 'A4';
		}
		$customWidth = null;
		$customHeight = null;
		if ($pageFormat === 'CUSTOM') {
			$customWidth = $this->validateCustomDimension($data['custom_width'] ?? '', 'Lebar');
			$customHeight = $this->validateCustomDimension($data['custom_height'] ?? '', 'Tinggi');
		}
		$orientation = strtoupper((string) ($data['orientation'] ?? 'P'));
		if (!in_array($orientation, array('P', 'L'), true)) {
			$orientation = 'P';
		}
		$margins = array();
		foreach (array('margin_top', 'margin_right', 'margin_bottom', 'margin_left') as $field) {
			$value = (int) ($data[$field] ?? 12);
			if ($value < 0 || $value > 50) {
				throw new DomainException('Margin harus berada di antara 0 dan 50 mm.');
			}
			$margins[$field] = $value;
		}
		if ($pageFormat === 'CUSTOM') {
			$pageWidth = $orientation === 'L' ? max($customWidth, $customHeight) : min($customWidth, $customHeight);
			$pageHeight = $orientation === 'L' ? min($customWidth, $customHeight) : max($customWidth, $customHeight);
			if ($margins['margin_left'] + $margins['margin_right'] >= $pageWidth || $margins['margin_top'] + $margins['margin_bottom'] >= $pageHeight) {
				throw new DomainException('Total margin harus lebih kecil daripada ukuran halaman custom.');
			}
		}
		$active = empty($data['active']) ? 0 : 1;
		$isDefault = $active && !empty($data['is_default']) ? 1 : 0;
		$this->ensureCustomPageColumns();

		$this->db->begin();
		try {
			if ($isDefault) {
				$this->query('UPDATE '.$this->table().' SET is_default=0 WHERE entity='.$this->entity.' AND doctype='.$this->str($doctype));
			}
			$values = array(
				'name='.$this->str($name),
				'doctype='.$this->str($doctype),
				'template_html='.$this->str($html),
				'template_css='.$this->str($css),
				'page_format='.$this->str($pageFormat),
				'custom_width='.($customWidth === null ? 'NULL' : $this->decimal($customWidth)),
				'custom_height='.($customHeight === null ? 'NULL' : $this->decimal($customHeight)),
				'orientation='.$this->str($orientation),
				'margin_top='.$margins['margin_top'],
				'margin_right='.$margins['margin_right'],
				'margin_bottom='.$margins['margin_bottom'],
				'margin_left='.$margins['margin_left'],
				'active='.$active,
				'is_default='.$isDefault,
				'fk_user_modif='.(int) $userId,
			);
			if ($id) {
				$this->get($id);
				$this->query('UPDATE '.$this->table().' SET '.implode(',', $values).' WHERE entity='.$this->entity.' AND rowid='.(int) $id);
			} else {
				$values[] = 'entity='.$this->entity;
				$values[] = 'fk_user_author='.(int) $userId;
				$values[] = 'datec='.$this->str($this->db->idate(dol_now()));
				$columns = array();
				$insertValues = array();
				foreach ($values as $assignment) {
					list($column, $value) = explode('=', $assignment, 2);
					$columns[] = $column;
					$insertValues[] = $value;
				}
				$this->query('INSERT INTO '.$this->table().' ('.implode(',', $columns).') VALUES ('.implode(',', $insertValues).')');
				$id = (int) $this->db->last_insert_id($this->table());
			}
			if ($this->db->commit() < 0) {
				throw new RuntimeException($this->db->lasterror());
			}
		} catch (Throwable $e) {
			$this->db->rollback();
			if (stripos($e->getMessage(), 'Duplicate') !== false) {
				throw new DomainException('Nama print format tersebut sudah digunakan.');
			}
			throw $e;
		}
		return (int) $id;
	}

	private function validateCustomDimension($value, $label)
	{
		$value = str_replace(',', '.', trim((string) $value));
		if ($value === '' || !is_numeric($value)) {
			throw new DomainException($label.' kertas custom wajib berupa angka dalam milimeter.');
		}
		$value = (float) $value;
		if ($value < 20 || $value > 2000) {
			throw new DomainException($label.' kertas custom harus antara 20 dan 2000 mm.');
		}
		return round($value, 2);
	}

	private function decimal($value)
	{
		return number_format((float) $value, 2, '.', '');
	}

	/** Add custom-size fields to databases created by an older module version. */
	public function ensureCustomPageColumns()
	{
		$columns = array();
		$result = $this->query('SHOW COLUMNS FROM '.$this->table());
		while ($column = $this->db->fetch_object($result)) {
			$columns[$column->Field] = true;
		}
		if (empty($columns['custom_width'])) {
			$this->query('ALTER TABLE '.$this->table().' ADD COLUMN custom_width decimal(8,2) NULL AFTER page_format');
		}
		if (empty($columns['custom_height'])) {
			$this->query('ALTER TABLE '.$this->table().' ADD COLUMN custom_height decimal(8,2) NULL AFTER custom_width');
		}
	}

	/**
	 * Convert real and legacy escaped line endings to a single LF character.
	 * Some older templates stored the text "\\r\\n" instead of a line break.
	 */
	private function normalizeNewlines($value)
	{
		return str_replace(
			array("\r\n", "\r", '\\r\\n', '\\r'."\n", '\\n', '\\r'),
			array("\n", "\n", "\n", "\n", "\n", "\n"),
			(string) $value
		);
	}

	private function normalizeFormatRow($row)
	{
		if ($row) {
			$row->template_html = $this->normalizeNewlines((string) $row->template_html);
			$row->template_css = $this->normalizeNewlines((string) $row->template_css);
			if (!property_exists($row, 'custom_width')) $row->custom_width = null;
			if (!property_exists($row, 'custom_height')) $row->custom_height = null;
		}
		return $row;
	}

	public function delete($id)
	{
		$format = $this->get($id);
		$this->query('DELETE FROM '.$this->table().' WHERE entity='.$this->entity.' AND rowid='.(int) $id);
		if (!empty($format->is_default)) {
			$this->query('UPDATE '.$this->table().' SET is_default=1 WHERE entity='.$this->entity.' AND doctype='.$this->str($format->doctype).' AND active=1 ORDER BY name ASC LIMIT 1');
		}
	}

	public function ensureDefaultFormat()
	{
		$result = $this->query('SELECT rowid FROM '.$this->table()." WHERE entity=".$this->entity." AND doctype='invoice' LIMIT 1");
		if ($this->db->fetch_object($result)) {
			return;
		}
		$default = self::defaultData();
		$default['active'] = 1;
		$default['is_default'] = 1;
		$this->save(0, $default, 0);
	}

	public function ensureDefaultFormats()
	{
		foreach (PrintFormatDoctypeRegistry::all() as $doctype => $definition) {
			$result = $this->query('SELECT rowid FROM '.$this->table().' WHERE entity='.$this->entity.' AND doctype='.$this->str($doctype).' LIMIT 1');
			if ($this->db->fetch_object($result)) {
				continue;
			}
			$default = self::defaultData($doctype);
			$default['active'] = 1;
			$default['is_default'] = 1;
			$this->save(0, $default, 0);
		}
	}

	/**
	 * Add the unit next to quantities in existing product-related formats.
	 * This is intentionally idempotent and leaves formats that already place a
	 * unit themselves untouched.
	 */
	public function ensureUnitsInProductDocumentFormats()
	{
		$doctypes = array(
			'invoice', 'supplier_invoice', 'proposal', 'supplier_proposal',
			'order', 'supplier_order', 'shipment', 'reception', 'delivery',
			'contract', 'intervention', 'stock_movement', 'stock_transfer',
			'manufacturing_order', 'bill_of_materials',
		);
		$quotedDoctypes = array_map(array($this, 'str'), $doctypes);
		$result = $this->query('SELECT rowid,template_html FROM '.$this->table().' WHERE entity='.$this->entity.' AND doctype IN ('.implode(',', $quotedDoctypes).')');
		while ($format = $this->db->fetch_object($result)) {
			$html = (string) $format->template_html;
			if (preg_match('/line\.(?:unit|unit_code|unit_label|unit_short_label)(?![a-zA-Z0-9_])/', $html)) {
				continue;
			}
			$updated = preg_replace(
				'/({{\s*line\.(?:qty|quantity|qty_ordered|qty_shipped)(?:\s*\|\s*number)?\s*}})/',
				'$1 {{ line.unit_short_label }}',
				$html
			);
			if (is_string($updated) && $updated !== $html) {
				$this->query('UPDATE '.$this->table().' SET template_html='.$this->str($updated).' WHERE entity='.$this->entity.' AND rowid='.(int) $format->rowid);
			}
		}
	}

	public static function defaultData($doctype = 'invoice')
	{
		$definition = PrintFormatDoctypeRegistry::get($doctype);
		if ($doctype !== 'invoice') {
			return self::genericDefaultData($doctype, $definition['label']);
		}
		return array(
			'name' => 'Invoice Standard',
			'doctype' => 'invoice',
			'page_format' => 'A4',
			'custom_width' => null,
			'custom_height' => null,
			'orientation' => 'P',
			'margin_top' => 12,
			'margin_right' => 12,
			'margin_bottom' => 12,
			'margin_left' => 12,
			'active' => 1,
			'is_default' => 0,
			'template_css' => "body { font-family: dejavusans; font-size: 10pt; color: #1f2937; }\n.header-table { width: 100%; }\n.title { font-size: 24pt; font-weight: bold; color: #1d4ed8; }\n.muted { color: #6b7280; }\n.address { line-height: 1.45; }\ntable.items { width: 100%; border-collapse: collapse; margin-top: 18px; }\ntable.items th { background-color: #1d4ed8; color: white; font-weight: bold; border: 1px solid #1d4ed8; padding: 7px; }\ntable.items td { border-bottom: 1px solid #d1d5db; padding: 7px; }\n.right { text-align: right; }\n.totals { width: 42%; margin-left: 58%; margin-top: 16px; }\n.totals td { padding: 5px; }\n.grand-total { background-color: #eff6ff; font-size: 12pt; font-weight: bold; }\n.note { margin-top: 20px; padding: 10px; background-color: #f9fafb; }",
			'template_html' => '<table class="header-table"><tr><td width="55%">{% if company.logo %}<img src="{{ company.logo }}" height="48">{% endif %}<br><b>{{ company.name }}</b><br><span class="muted">{{ company.address | nl2br }}<br>{{ company.zip }} {{ company.town }}</span></td><td width="45%" class="right"><span class="title">INVOICE</span><br><b>{{ doc.ref }}</b><br><span class="muted">Tanggal: {{ doc.date | date }}<br>Jatuh tempo: {{ doc.due_date | date }}</span></td></tr></table><hr><table width="100%"><tr><td width="55%" class="address"><span class="muted">DITAGIHKAN KEPADA</span><br><b>{{ customer.name }}</b><br>{{ customer.address | nl2br }}<br>{{ customer.zip }} {{ customer.town }}<br>{{ customer.country }}</td><td width="45%"><span class="muted">Referensi pelanggan</span><br>{{ doc.customer_ref }}<br><br><span class="muted">Mata uang</span><br>{{ doc.currency }}</td></tr></table><table class="items"><thead><tr><th width="8%">No.</th><th width="42%">Deskripsi</th><th width="12%" class="right">Qty / Satuan</th><th width="18%" class="right">Harga</th><th width="20%" class="right">Jumlah</th></tr></thead><tbody>{% for line in lines %}<tr><td width="8%">{{ loop.index }}</td><td width="42%"><b>{{ line.product_ref }}</b>{% if line.product_ref %}<br>{% endif %}{{ line.description | nl2br }}</td><td width="12%" class="right">{{ line.qty | number }} {{ line.unit_short_label }}</td><td width="18%" class="right">{{ line.unit_price | money }}</td><td width="20%" class="right">{{ line.total_excl_tax | money }}</td></tr>{% endfor %}</tbody></table><table class="totals"><tr><td>Subtotal</td><td class="right">{{ totals.excl_tax | money }}</td></tr><tr><td>Pajak</td><td class="right">{{ totals.tax | money }}</td></tr><tr class="grand-total"><td>Total</td><td class="right">{{ totals.incl_tax | money }}</td></tr><tr><td>Sudah dibayar</td><td class="right">{{ totals.paid | money }}</td></tr><tr><td><b>Sisa</b></td><td class="right"><b>{{ totals.balance | money }}</b></td></tr></table>{% if doc.note_public %}<div class="note"><b>Catatan</b><br>{{ doc.note_public | nl2br }}</div>{% endif %}',
		);
	}

	private static function genericDefaultData($doctype, $label)
	{
		return array(
			'name' => $label.' Standard',
			'doctype' => $doctype,
			'page_format' => 'A4',
			'custom_width' => null,
			'custom_height' => null,
			'orientation' => 'P',
			'margin_top' => 12,
			'margin_right' => 12,
			'margin_bottom' => 12,
			'margin_left' => 12,
			'active' => 1,
			'is_default' => 0,
			'template_css' => "body { font-family: dejavusans; font-size: 10pt; color: #1f2937; }\n.header { width: 100%; border-bottom: 2px solid #1d4ed8; margin-bottom: 16px; }\n.title { color: #1d4ed8; font-size: 21pt; font-weight: bold; }\n.muted { color: #6b7280; }\n.meta { width: 100%; margin: 12px 0; }\ntable.items { width: 100%; border-collapse: collapse; margin-top: 16px; }\ntable.items th { background-color: #1d4ed8; color: white; border: 1px solid #1d4ed8; padding: 6px; }\ntable.items td { border-bottom: 1px solid #d1d5db; padding: 6px; }\n.right { text-align: right; }\n.note { background-color: #f9fafb; margin-top: 18px; padding: 10px; }\n",
			'template_html' => '<table class="header"><tr><td width="55%">{% if company.logo %}<img src="{{ company.logo }}" height="42">{% endif %}<br><b>{{ company.name }}</b><br><span class="muted">{{ company.address | nl2br }}</span></td><td width="45%" class="right"><span class="title">'.$label.'</span><br><b>{{ doc.ref }}</b><br>{{ doc.date | date }}</td></tr></table>{% if party.name %}<table class="meta"><tr><td><span class="muted">PIHAK TERKAIT</span><br><b>{{ party.name }}</b><br>{{ party.address | nl2br }}<br>{{ party.zip }} {{ party.town }}</td><td class="right"><span class="muted">STATUS</span><br>{{ doc.status }}</td></tr></table>{% endif %}<h3>{{ doc.label }}</h3>{% if doc.description %}<p>{{ doc.description | nl2br }}</p>{% endif %}{% if lines %}<table class="items"><thead><tr><th width="8%">No.</th><th width="47%">Deskripsi</th><th width="15%" class="right">Qty / Satuan</th><th width="15%" class="right">Harga</th><th width="15%" class="right">Jumlah</th></tr></thead><tbody>{% for line in lines %}<tr><td width="8%">{{ loop.index }}</td><td width="47%"><b>{{ line.ref }}</b>{% if line.ref %}<br>{% endif %}{{ line.description | nl2br }}</td><td width="15%" class="right">{{ line.qty | number }} {{ line.unit_short_label }}</td><td width="15%" class="right">{{ line.unit_price | money }}</td><td width="15%" class="right">{{ line.total_excl_tax | money }}</td></tr>{% endfor %}</tbody></table>{% endif %}{% if totals.has_amount %}<table width="45%" align="right"><tr><td>Subtotal</td><td class="right">{{ totals.excl_tax | money }}</td></tr><tr><td>Pajak</td><td class="right">{{ totals.tax | money }}</td></tr><tr><td><b>Total</b></td><td class="right"><b>{{ totals.incl_tax | money }}</b></td></tr></table>{% endif %}{% if doc.note_public %}<div class="note">{{ doc.note_public | nl2br }}</div>{% endif %}',
		);
	}

	private function sanitizeHtml($html)
	{
		if (strpos($html, '<?') !== false) {
			throw new DomainException('Kode PHP tidak diperbolehkan di template.');
		}
		$html = preg_replace('#<(script|iframe|object|embed|link|meta|base)[^>]*>.*?</\1\s*>#is', '', $html);
		$html = preg_replace('#<(script|iframe|object|embed|link|meta|base)\b[^>]*?/?>#is', '', $html);
		$html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
		$html = preg_replace_callback('/\s+src\s*=\s*(["\'])(.*?)\1/is', function ($match) {
			$source = trim($match[2]);
			if ($source === '{{ company.logo }}' || preg_match('#^data:image/(?:png|jpeg|gif);base64,#i', $source)) {
				return ' src='.$match[1].$source.$match[1];
			}
			return '';
		}, $html);
		$html = preg_replace_callback('/url\s*\(\s*(["\']?)(.*?)\1\s*\)/is', function ($match) {
			return preg_match('#^data:image/(?:png|jpeg|gif);base64,#i', trim($match[2])) ? $match[0] : 'none';
		}, $html);
		$html = preg_replace('/javascript\s*:/i', '', $html);
		return $html;
	}

	private function sanitizeCss($css)
	{
		if (strpos($css, '<?') !== false) {
			throw new DomainException('Kode PHP tidak diperbolehkan di CSS.');
		}
		if (strpos($css, '<') !== false || strpos($css, '>') !== false) {
			throw new DomainException('Tag HTML tidak diperbolehkan di CSS.');
		}
		$css = preg_replace('/@import[^;]+;/i', '', $css);
		$css = preg_replace_callback('/url\s*\(\s*(["\']?)(.*?)\1\s*\)/is', function ($match) {
			return preg_match('#^data:image/(?:png|jpeg|gif);base64,#i', trim($match[2])) ? $match[0] : 'none';
		}, $css);
		$css = preg_replace('/(?:expression|javascript)\s*[:(]/i', '', $css);
		return $css;
	}
}

/** Restricted, non-eval template renderer. */
class PrintFormatRenderer
{
	private $unitCache = array();
	private $warehouseCache = array();
	private $userCache = array();

	public static function validateSyntax($template)
	{
		$forOpen = preg_match_all('/{%\s*for\s+[a-zA-Z_][a-zA-Z0-9_]*\s+in\s+[a-zA-Z0-9_.]+\s*%}/', $template);
		$forClose = preg_match_all('/{%\s*endfor\s*%}/', $template);
		$ifOpen = preg_match_all('/{%\s*if\s+(?:not\s+)?[a-zA-Z0-9_.]+\s*%}/', $template);
		$ifClose = preg_match_all('/{%\s*endif\s*%}/', $template);
		if ($forOpen !== $forClose || $ifOpen !== $ifClose) {
			throw new DomainException('Tag for/endfor atau if/endif pada template tidak seimbang.');
		}
		preg_match_all('/{%\s*(.*?)\s*%}/s', $template, $tags);
		foreach ($tags[1] as $tag) {
			$tag = trim($tag);
			if (!preg_match('/^(?:for\s+[a-zA-Z_][a-zA-Z0-9_]*\s+in\s+[a-zA-Z0-9_.]+|endfor|if\s+(?:not\s+)?[a-zA-Z0-9_.]+|endif)$/', $tag)) {
				throw new DomainException('Template berisi control tag yang tidak didukung: '.$tag);
			}
		}
	}

	public function contextForInvoice($object, $outputlangs)
	{
		return $this->contextForObject($object, $outputlangs, 'invoice');
	}

	public function contextForObject($object, $outputlangs, $doctype = '')
	{
		global $conf, $mysoc;

		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}
		if (empty($object->lines)) {
			if (method_exists($object, 'fetch_lines')) {
				$object->fetch_lines();
			} elseif (method_exists($object, 'getLinesArray')) {
				$object->getLinesArray();
			}
		}
		$useMultiCurrency = isModEnabled('multicurrency') && !empty($object->multicurrency_code) && (float) ($object->multicurrency_tx ?? 1) !== 1.0;
		$currency = $useMultiCurrency ? $object->multicurrency_code : $conf->currency;
		$payments = method_exists($object, 'getSommePaiement') ? (float) $object->getSommePaiement($useMultiCurrency ? 1 : 0) : 0.0;
		$creditNotes = method_exists($object, 'getSumCreditNotesUsed') ? (float) $object->getSumCreditNotesUsed($useMultiCurrency ? 1 : 0) : 0.0;
		$deposits = method_exists($object, 'getSumDepositsUsed') ? (float) $object->getSumDepositsUsed($useMultiCurrency ? 1 : 0) : 0.0;
		$paid = $payments + $creditNotes + $deposits;
		$status = (string) ($object->status ?? $object->statut ?? '');
		if (method_exists($object, 'getLibStatut')) {
			try {
				$status = trim(strip_tags($object->getLibStatut(0)));
			} catch (Throwable $e) {
				// Some native objects use a different status method signature.
			}
		}
		$partyObject = !empty($object->thirdparty) && is_object($object->thirdparty) ? $object->thirdparty : (!empty($object->societe) && is_object($object->societe) ? $object->societe : null);
		$party = $this->partyContext($partyObject);
		$lines = array();
		foreach ((array) ($object->lines ?? array()) as $line) {
			$lineRef = (string) ($line->product_ref ?? $line->ref ?? '');
			$lineLabel = (string) ($line->product_label ?? $line->label ?? '');
			$description = (string) ($line->desc ?? $line->description ?? $lineLabel);
			$unit = $this->unitContext($line, $outputlangs);
			$lineContext = $this->scalarObjectFields($line);
			$lines[] = array_merge($lineContext, array(
				'id' => (int) ($line->id ?? $line->rowid ?? 0),
				'product_id' => (int) ($line->fk_product ?? 0),
				'ref' => $lineRef,
				'product_ref' => $lineRef,
				'label' => $lineLabel,
				'product_label' => $lineLabel,
				'description' => $description,
				'sku' => (string) ($line->sku ?? $line->product_ref ?? $line->ref ?? ''),
				'note' => (string) ($line->note ?? $line->note_public ?? ''),
				'qty' => (float) ($line->qty ?? $line->quantity ?? 0),
				'quantity' => (float) ($line->qty ?? $line->quantity ?? 0),
				'qty_ordered' => (float) ($line->qty_asked ?? $line->qty_ordered ?? $line->qty ?? 0),
				'qty_shipped' => (float) ($line->qty_shipped ?? $line->qty ?? 0),
				'unit' => $unit['label'],
				'unit_id' => $unit['id'],
				'unit_code' => $unit['code'],
				'unit_label' => $unit['label'],
				'unit_short_label' => $unit['short_label'],
				'unit_price' => (float) ($useMultiCurrency ? ($line->multicurrency_subprice ?? 0) : ($line->subprice ?? $line->pu_ht ?? $line->unit_price ?? 0)),
				'discount_percent' => (float) ($line->remise_percent ?? 0),
				'vat_rate' => (float) ($line->tva_tx ?? 0),
				'total_excl_tax' => (float) ($useMultiCurrency ? ($line->multicurrency_total_ht ?? 0) : ($line->total_ht ?? $line->total ?? 0)),
				'total_tax' => (float) ($useMultiCurrency ? ($line->multicurrency_total_tva ?? 0) : ($line->total_tva ?? 0)),
				'total_incl_tax' => (float) ($useMultiCurrency ? ($line->multicurrency_total_ttc ?? 0) : ($line->total_ttc ?? $line->total ?? 0)),
				'date_start' => $line->date_start ?? $line->date_debut ?? null,
				'date_end' => $line->date_end ?? $line->date_fin ?? null,
				'extrafields' => $this->normalizeExtraFields($line->array_options ?? array()),
			));
		}
		$hasAmount = isset($object->total_ht) || isset($object->total_ttc) || isset($object->amount);
		$totalExcl = (float) ($useMultiCurrency ? ($object->multicurrency_total_ht ?? 0) : ($object->total_ht ?? $object->amount ?? 0));
		$totalTax = (float) ($useMultiCurrency ? ($object->multicurrency_total_tva ?? 0) : ($object->total_tva ?? 0));
		$totalIncl = (float) ($useMultiCurrency ? ($object->multicurrency_total_ttc ?? 0) : ($object->total_ttc ?? $object->amount ?? 0));

		$warehouse = $this->warehouseContext($object);
		$documentContext = array_merge($this->scalarObjectFields($object), array(
			'id' => (int) ($object->id ?? $object->rowid ?? 0),
			'ref' => (string) ($object->ref ?? $object->code ?? ''),
			'label' => (string) ($object->label ?? $object->title ?? $object->subject ?? ''),
			'description' => (string) ($object->description ?? $object->desc ?? ''),
			'customer_ref' => (string) ($object->ref_client ?? $object->ref_customer ?? $object->ref_supplier ?? ''),
			'date' => $object->date ?? $object->date_creation ?? $object->datec ?? null,
			'due_date' => $object->date_lim_reglement ?? $object->date_end ?? null,
			'delivery_date' => $object->date_delivery ?? $object->date_livraison ?? $object->date_expedition ?? $object->date_shipping ?? null,
			'date_start' => $object->date_start ?? $object->date_debut ?? null,
			'date_end' => $object->date_end ?? $object->date_fin ?? null,
			'status' => $status,
			'type' => (string) ($object->type ?? ''),
			'currency' => $currency,
			'note_public' => (string) ($object->note_public ?? ''),
			'note_private' => (string) ($object->note_private ?? ''),
			'payment_terms' => (string) ($object->cond_reglement_doc ?? ''),
			'payment_method' => (string) ($object->mode_reglement_code ?? ''),
			'warehouse' => $warehouse['label'],
			'warehouse_id' => $warehouse['id'],
			'warehouse_ref' => $warehouse['ref'],
			'warehouse_label' => $warehouse['label'],
			'author' => $this->userName($object->user_author_id ?? $object->fk_user_author ?? $object->fk_user_creat ?? $object->user_creation_id ?? 0),
			'validator' => $this->userName($object->user_validation_id ?? $object->fk_user_valid ?? 0),
			'approver' => $this->userName($object->user_approval_id ?? $object->fk_user_approve ?? $object->fk_user_approved ?? 0),
			'extrafields' => $this->normalizeExtraFields($object->array_options ?? array()),
		));

		return array(
			'doctype' => $doctype,
			'doc' => $documentContext,
			'party' => $party,
			'customer' => $party,
			'company' => array(
				'name' => (string) $mysoc->name,
				'address' => (string) $mysoc->address,
				'zip' => (string) $mysoc->zip,
				'town' => (string) $mysoc->town,
				'country' => (string) ($mysoc->country ?? ''),
				'country_code' => (string) $mysoc->country_code,
				'email' => (string) $mysoc->email,
				'phone' => (string) $mysoc->phone,
				'vat_number' => (string) ($mysoc->tva_intra ?? ''),
				'logo' => $this->companyLogoDataUri($mysoc, $object->entity ?? $conf->entity),
			),
			'lines' => $lines,
			'totals' => array(
				'has_amount' => $hasAmount,
				'excl_tax' => $totalExcl,
				'tax' => $totalTax,
				'incl_tax' => $totalIncl,
				'payments' => $payments,
				'credit_notes' => $creditNotes,
				'deposits' => $deposits,
				'paid' => $paid,
				'balance' => $totalIncl - $paid,
			),
		);
	}

	private function scalarObjectFields($object)
	{
		$result = array();
		foreach (get_object_vars($object) as $key => $value) {
			if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $key) && (is_scalar($value) || $value === null)) {
				$result[$key] = $value;
			}
		}
		return $result;
	}

	private function unitContext($line, $outputlangs)
	{
		global $db;
		$id = (int) ($line->fk_unit ?? $line->unit_id ?? 0);
		$fallback = (string) ($line->unit ?? $line->unit_short_label ?? '');
		if ($id <= 0 && !empty($line->fk_product)) {
			$result = $db->query('SELECT fk_unit FROM '.$db->prefix().'product WHERE rowid='.(int) $line->fk_product.' LIMIT 1');
			$product = $result ? $db->fetch_object($result) : null;
			$id = (int) ($product->fk_unit ?? 0);
		}
		if ($id <= 0) $id = getDolGlobalInt('PRODUCT_USE_UNITS');
		if ($id <= 0) return array('id' => 0, 'code' => '', 'label' => $fallback, 'short_label' => $fallback);
		if (!isset($this->unitCache[$id])) {
			$result = $db->query('SELECT code,label,short_label FROM '.$db->prefix().'c_units WHERE rowid='.$id);
			$row = $result ? $db->fetch_object($result) : null;
			$label = (string) ($row->label ?? $fallback);
			$shortLabel = (string) ($row->short_label ?? $fallback);
			if (function_exists('measuringUnitString')) {
				$translatedLabel = measuringUnitString($id, '', null, 0, $outputlangs);
				$translatedShortLabel = measuringUnitString($id, '', null, 1, $outputlangs);
				if ($translatedLabel !== '') $label = $translatedLabel;
				if ($translatedShortLabel !== '') $shortLabel = $translatedShortLabel;
			}
			$this->unitCache[$id] = array(
				'id' => $id,
				'code' => (string) ($row->code ?? ''),
				'label' => $label,
				'short_label' => $shortLabel,
			);
		}
		return $this->unitCache[$id];
	}

	private function warehouseContext($object)
	{
		global $db;
		$id = (int) ($object->warehouse_id ?? $object->entrepot_id ?? $object->fk_entrepot ?? 0);
		if ($id <= 0) {
			foreach ((array) ($object->lines ?? array()) as $line) {
				$id = (int) ($line->warehouse_id ?? $line->entrepot_id ?? $line->fk_entrepot ?? 0);
				if ($id > 0) break;
			}
		}
		if ($id <= 0) return array('id' => 0, 'ref' => '', 'label' => '');
		if (!isset($this->warehouseCache[$id])) {
			$result = $db->query('SELECT ref,lieu FROM '.$db->prefix().'entrepot WHERE rowid='.$id);
			$row = $result ? $db->fetch_object($result) : null;
			$this->warehouseCache[$id] = array('id' => $id, 'ref' => (string) ($row->ref ?? ''), 'label' => (string) ($row->lieu ?? $row->ref ?? ''));
		}
		return $this->warehouseCache[$id];
	}

	private function userName($id)
	{
		global $db;
		$id = (int) $id;
		if ($id <= 0) return '';
		if (!isset($this->userCache[$id])) {
			$result = $db->query('SELECT firstname,lastname,login FROM '.$db->prefix().'user WHERE rowid='.$id);
			$row = $result ? $db->fetch_object($result) : null;
			$name = trim((string) ($row->firstname ?? '').' '.(string) ($row->lastname ?? ''));
			$this->userCache[$id] = $name !== '' ? $name : (string) ($row->login ?? '');
		}
		return $this->userCache[$id];
	}

	public function render($template, $context, $outputlangs)
	{
		self::validateSyntax($template);
		$template = $this->renderLoops($template, $context, $outputlangs, 0);
		$template = $this->renderConditions($template, $context, $outputlangs, 0);
		return preg_replace_callback('/{{\s*([^{}]+?)\s*}}/', function ($match) use ($context, $outputlangs) {
			return $this->renderVariable($match[1], $context, $outputlangs);
		}, $template);
	}

	private function renderLoops($template, $context, $outputlangs, $depth)
	{
		if ($depth > 10) {
			throw new DomainException('Template memiliki loop terlalu dalam.');
		}
		$pattern = '/{%\s*for\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+in\s+([a-zA-Z0-9_.]+)\s*%}(.*?){%\s*endfor\s*%}/s';
		$guard = 0;
		while (preg_match($pattern, $template) && $guard++ < 100) {
			$template = preg_replace_callback($pattern, function ($match) use ($context, $outputlangs, $depth) {
				$items = $this->resolve($context, $match[2]);
				if (!is_array($items) && !($items instanceof Traversable)) {
					return '';
				}
				if ($items instanceof Traversable) {
					$items = iterator_to_array($items);
				}
				$output = '';
				$count = count($items);
				$index = 0;
				foreach ($items as $item) {
					$child = $context;
					$child[$match[1]] = $item;
					$child['loop'] = array('index' => $index + 1, 'index0' => $index, 'first' => $index === 0, 'last' => $index === $count - 1);
					$part = $this->renderLoops($match[3], $child, $outputlangs, $depth + 1);
					$part = $this->renderConditions($part, $child, $outputlangs, $depth + 1);
					$output .= preg_replace_callback('/{{\s*([^{}]+?)\s*}}/', function ($variable) use ($child, $outputlangs) {
						return $this->renderVariable($variable[1], $child, $outputlangs);
					}, $part);
					$index++;
				}
				return $output;
			}, $template);
		}
		return $template;
	}

	private function renderConditions($template, $context, $outputlangs, $depth)
	{
		if ($depth > 10) {
			throw new DomainException('Template memiliki kondisi terlalu dalam.');
		}
		$pattern = '/{%\s*if\s+(not\s+)?([a-zA-Z0-9_.]+)\s*%}(.*?){%\s*endif\s*%}/s';
		$guard = 0;
		while (preg_match($pattern, $template) && $guard++ < 100) {
			$template = preg_replace_callback($pattern, function ($match) use ($context, $outputlangs, $depth) {
				$value = $this->resolve($context, $match[2]);
				$show = !empty($value);
				if (!empty($match[1])) {
					$show = !$show;
				}
				if (!$show) {
					return '';
				}
				$part = $this->renderConditions($match[3], $context, $outputlangs, $depth + 1);
				return $this->renderLoops($part, $context, $outputlangs, $depth + 1);
			}, $template);
		}
		return $template;
	}

	private function renderVariable($expression, $context, $outputlangs)
	{
		$parts = array_map('trim', explode('|', $expression));
		$path = array_shift($parts);
		if (!preg_match('/^[a-zA-Z0-9_.]+$/', $path)) {
			return '';
		}
		$value = $this->resolve($context, $path);
		$escape = true;
		foreach ($parts as $filter) {
			if ($filter === 'date') {
				$value = $value ? dol_print_date($value, 'day', 'auto', $outputlangs) : '';
			} elseif (preg_match('/^default\((.*?)\)$/', $filter, $match)) {
				if ($value === null || $value === '') {
					$fallback = trim($match[1]);
					if (preg_match('/^(["\'])(.*)\1$/s', $fallback, $quoted)) $value = $quoted[2];
					elseif (preg_match('/^[a-zA-Z0-9_.]+$/', $fallback)) $value = $this->resolve($context, $fallback);
				}
			} elseif ($filter === 'money') {
				$currency = $this->resolve($context, 'doc.currency');
				$value = price((float) $value, 0, $outputlangs, 1, -1, -1, $currency ?: 'auto');
			} elseif ($filter === 'number') {
				$value = price((float) $value, 0, $outputlangs, 1, -1, -1, '');
			} elseif ($filter === 'nl2br') {
				$value = nl2br(dol_escape_htmltag((string) $value));
				$escape = false;
			} elseif ($filter === 'upper') {
				$value = function_exists('mb_strtoupper') ? mb_strtoupper((string) $value, 'UTF-8') : strtoupper((string) $value);
			} elseif ($filter === 'lower') {
				$value = function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
			}
		}
		if (is_bool($value)) {
			$value = $value ? '1' : '';
		} elseif (is_array($value) || is_object($value)) {
			$value = '';
		}
		return $escape ? dol_escape_htmltag((string) $value) : (string) $value;
	}

	private function resolve($context, $path)
	{
		$value = $context;
		foreach (explode('.', $path) as $part) {
			if (is_array($value) && array_key_exists($part, $value)) {
				$value = $value[$part];
			} elseif (is_object($value) && isset($value->$part)) {
				$value = $value->$part;
			} else {
				return null;
			}
		}
		return $value;
	}

	private function normalizeExtraFields($values)
	{
		$result = array();
		foreach ((array) $values as $key => $value) {
			$result[preg_replace('/^options_/', '', (string) $key)] = $value;
		}
		return $result;
	}

	private function partyContext($party)
	{
		if (!is_object($party)) {
			return array('id' => 0, 'name' => '', 'address' => '', 'zip' => '', 'town' => '', 'state' => '', 'country' => '', 'country_code' => '', 'email' => '', 'phone' => '', 'vat_number' => '', 'customer_code' => '', 'supplier_code' => '', 'extrafields' => array());
		}
		return array(
			'id' => (int) ($party->id ?? $party->rowid ?? 0),
			'name' => (string) ($party->name ?? $party->nom ?? $party->label ?? ''),
			'name_alias' => (string) ($party->name_alias ?? ''),
			'address' => (string) ($party->address ?? ''),
			'zip' => (string) ($party->zip ?? ''),
			'town' => (string) ($party->town ?? ''),
			'state' => (string) ($party->state ?? ''),
			'country' => (string) ($party->country ?? ''),
			'country_code' => (string) ($party->country_code ?? ''),
			'email' => (string) ($party->email ?? ''),
			'phone' => (string) ($party->phone ?? ''),
			'vat_number' => (string) ($party->tva_intra ?? ''),
			'customer_code' => (string) ($party->code_client ?? ''),
			'supplier_code' => (string) ($party->code_fournisseur ?? ''),
			'extrafields' => $this->normalizeExtraFields($party->array_options ?? array()),
		);
	}

	private function companyLogoDataUri($company, $entity)
	{
		global $conf;
		if (empty($company->logo)) {
			return '';
		}
		$directory = !empty($conf->mycompany->multidir_output[$entity]) ? $conf->mycompany->multidir_output[$entity] : $conf->mycompany->dir_output;
		$file = $directory.'/logos/'.$company->logo;
		if (!is_readable($file) || filesize($file) > 2000000) {
			return '';
		}
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$mimes = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif');
		if (empty($mimes[$extension])) {
			return '';
		}
		return 'data:'.$mimes[$extension].';base64,'.base64_encode(file_get_contents($file));
	}
}

/** Make browser-oriented CSS reliable in TCPDF by copying supported selectors to inline styles. */
class PrintFormatPdfStyler
{
	public static function prepare($html, $css)
	{
		if (trim((string) $css) === '' || trim((string) $html) === '' || !class_exists('DOMDocument')) {
			return '<style>'.(string) $css.'</style>'.(string) $html;
		}
		if (!class_exists('TCPDF_STATIC')) {
			require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/include/tcpdf_static.php';
		}

		$document = new DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$loaded = $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.(string) $html.'</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded) {
			return '<style>'.(string) $css.'</style>'.(string) $html;
		}

		$xpath = new DOMXPath($document);
		$originalStyles = array();
		$counter = 0;
		foreach ($xpath->query('//*') as $node) {
			if (!($node instanceof DOMElement)) continue;
			$key = 'pf'.(++$counter);
			$node->setAttribute('data-pf-style-key', $key);
			$originalStyles[$key] = $node->getAttribute('style');
			$node->removeAttribute('style');
		}

		$rules = TCPDF_STATIC::extractCSSproperties((string) $css);
		foreach ($rules as $weightedSelector => $declarations) {
			$selector = preg_replace('/^[0-9]+\s+/', '', $weightedSelector);
			$query = self::selectorToXpath($selector);
			if ($query === '') continue;
			$declarations = trim(preg_replace('/\s*!important\b/i', '', (string) $declarations), " \t\r\n;");
			if ($declarations === '') continue;
			$nodes = @$xpath->query($query);
			if ($nodes === false) continue;
			foreach ($nodes as $node) {
				if (!($node instanceof DOMElement)) continue;
				$current = trim($node->getAttribute('style'), " \t\r\n;");
				$node->setAttribute('style', ($current !== '' ? $current.';' : '').$declarations.';');
			}
		}

		foreach ($xpath->query('//*[@data-pf-style-key]') as $node) {
			if (!($node instanceof DOMElement)) continue;
			$key = $node->getAttribute('data-pf-style-key');
			$inline = trim($originalStyles[$key] ?? '', " \t\r\n;");
			$current = trim($node->getAttribute('style'), " \t\r\n;");
			if ($inline !== '') {
				$current = ($current !== '' ? $current.';' : '').$inline;
			}
			if ($current !== '') $node->setAttribute('style', $current.';');
			else $node->removeAttribute('style');
			$node->removeAttribute('data-pf-style-key');
		}

		// Browsers use the widths declared on THEAD cells for the complete table.
		// TCPDF calculates TBODY independently and may enlarge columns containing
		// long text. Copy the header grid to every row so the PDF follows preview.
		self::synchronizeTableColumnWidths($xpath);

		$body = $document->getElementsByTagName('body')->item(0);
		if (!$body) {
			return '<style>'.(string) $css.'</style>'.(string) $html;
		}
		return '<style>'.(string) $css.'</style>'.$document->saveHTML($body);
	}

	private static function synchronizeTableColumnWidths($xpath)
	{
		foreach ($xpath->query('//table') as $table) {
			if (!($table instanceof DOMElement)) continue;
			$cells = $xpath->query('./thead[1]/tr[1]/*[self::th or self::td]', $table);
			if (!$cells || !$cells->length) {
				$cells = $xpath->query('./tbody[1]/tr[1]/*[self::th or self::td]', $table);
			}
			if (!$cells || !$cells->length) continue;

			// With automatic table layout a browser expands percentage columns to
			// fill the row when their total is not 100%. TCPDF leaves the remainder
			// blank, so normalize the percentages before handing it the markup.
			$percentages = array();
			$totalPercentage = 0.0;
			foreach ($cells as $cell) {
				$width = self::elementWidth($cell);
				if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)%$/', $width, $match)) {
					$percentages = array();
					break;
				}
				$percentages[] = (float) $match[1];
				$totalPercentage += (float) $match[1];
			}
			if ($percentages && $totalPercentage > 0 && abs($totalPercentage - 100.0) > 0.001) {
				foreach ($cells as $index => $cell) {
					self::setElementWidth($cell, self::formatNumber($percentages[$index] * 100 / $totalPercentage).'%');
				}
			}

			$columns = array();
			foreach ($cells as $cell) {
				$width = self::elementWidth($cell);
				$span = max(1, (int) $cell->getAttribute('colspan'));
				for ($i = 0; $i < $span; $i++) $columns[] = $span === 1 ? $width : '';
			}
			if (!$columns || !array_filter($columns, 'strlen')) continue;

			$rows = $xpath->query('./tbody/tr|./tfoot/tr', $table);
			foreach ($rows as $row) {
				$column = 0;
				foreach ($xpath->query('./*[self::th or self::td]', $row) as $cell) {
					$span = max(1, (int) $cell->getAttribute('colspan'));
					$width = self::combinedColumnWidth($columns, $column, $span);
					if ($width !== '' && self::elementWidth($cell) === '') {
						$cell->setAttribute('width', $width);
						$style = trim($cell->getAttribute('style'), " \t\r\n;");
						$cell->setAttribute('style', ($style !== '' ? $style.';' : '').'width:'.$width.';');
					}
					$column += $span;
				}
			}
		}
	}

	private static function elementWidth($element)
	{
		$width = trim((string) $element->getAttribute('width'));
		if ($width !== '') return $width;
		if (preg_match('/(?:^|;)\s*width\s*:\s*([^;]+)/i', (string) $element->getAttribute('style'), $match)) {
			return trim($match[1]);
		}
		return '';
	}

	private static function setElementWidth($element, $width)
	{
		$element->setAttribute('width', $width);
		$style = (string) $element->getAttribute('style');
		if (preg_match('/(?:^|;)\s*width\s*:/i', $style)) {
			$style = preg_replace_callback('/((?:^|;)\s*width\s*:)\s*[^;]+/i', function ($match) use ($width) {
				return $match[1].$width;
			}, $style, 1);
		} else {
			$style = trim($style, " \t\r\n;");
			$style = ($style !== '' ? $style.';' : '').'width:'.$width.';';
		}
		$element->setAttribute('style', $style);
	}

	private static function combinedColumnWidth($columns, $offset, $span)
	{
		$values = array_slice($columns, $offset, $span);
		if (count($values) !== $span || in_array('', $values, true)) return '';
		$unit = null;
		$total = 0.0;
		foreach ($values as $value) {
			if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)(%|px|pt|mm|cm|in)?$/i', trim($value), $match)) return '';
			$currentUnit = strtolower($match[2] ?? '');
			if ($unit === null) $unit = $currentUnit;
			if ($currentUnit !== $unit) return '';
			$total += (float) $match[1];
		}
		return self::formatNumber($total).$unit;
	}

	private static function formatNumber($number)
	{
		return rtrim(rtrim(number_format((float) $number, 4, '.', ''), '0'), '.');
	}

	private static function selectorToXpath($selector)
	{
		$selector = trim((string) $selector);
		if ($selector === '' || strpos($selector, ',') !== false) return '';
		$selector = preg_replace('/\s*>\s*/', ' > ', $selector);
		$tokens = preg_split('/\s+/', $selector, -1, PREG_SPLIT_NO_EMPTY);
		$query = '';
		$axis = '//';
		foreach ($tokens as $token) {
			if ($token === '>') {
				$axis = '/';
				continue;
			}
			$step = self::simpleSelectorToXpath($token);
			if ($step === '') return '';
			$query .= $axis.$step;
			$axis = '//';
		}
		return $query;
	}

	private static function simpleSelectorToXpath($selector)
	{
		$remaining = (string) $selector;
		$tag = '*';
		if (preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*|\*)/', $remaining, $match)) {
			$tag = strtolower($match[1]);
			$remaining = substr($remaining, strlen($match[0]));
		}
		$predicates = array();
		while ($remaining !== '') {
			if (preg_match('/^\.([a-zA-Z0-9_-]+)/', $remaining, $match)) {
				$predicates[] = 'contains(concat(" ",normalize-space(@class)," ")," '.$match[1].' ")';
			} elseif (preg_match('/^#([a-zA-Z0-9_-]+)/', $remaining, $match)) {
				$predicates[] = '@id="'.$match[1].'"';
			} elseif (preg_match('/^\[([a-zA-Z0-9_-]+)\]/', $remaining, $match)) {
				$predicates[] = '@'.$match[1];
			} elseif (preg_match('/^\[([a-zA-Z0-9_-]+)=["\']?([a-zA-Z0-9_.:-]+)["\']?\]/', $remaining, $match)) {
				$predicates[] = '@'.$match[1].'="'.$match[2].'"';
			} elseif (preg_match('/^:nth-child\(([1-9][0-9]*)\)/i', $remaining, $match)) {
				$predicates[] = 'count(preceding-sibling::*)='.((int) $match[1] - 1);
			} elseif (preg_match('/^:first-child/i', $remaining, $match)) {
				$predicates[] = 'not(preceding-sibling::*)';
			} elseif (preg_match('/^:last-child/i', $remaining, $match)) {
				$predicates[] = 'not(following-sibling::*)';
			} else {
				return '';
			}
			$remaining = substr($remaining, strlen($match[0]));
		}
		return $tag.($predicates ? '['.implode('][', $predicates).']' : '');
	}
}
