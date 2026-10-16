<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Store and locate the customer-supplied proof attached to a sales order.
 *
 * Proof files live in the native order document directory.  The prefix keeps
 * them distinguishable from generated PDFs and other order attachments.
 */
class CustomerOrderProof
{
	const FILE_PREFIX = 'CUSTOMER_PROOF-';

	/** @var DoliDB */
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the native document directory for an order.
	 *
	 * @param object $order Sales order
	 * @return string
	 */
	public function directory($order)
	{
		$root = getMultidirOutput($order);
		return rtrim((string) $root, '/').'/'.dol_sanitizeFileName((string) $order->ref);
	}

	/**
	 * Return valid proof file names for an order.
	 *
	 * @param object $order Sales order
	 * @return array<int,string>
	 */
	public function files($order)
	{
		return self::filesInDirectory($this->directory($order));
	}

	/**
	 * Locate valid proof files in a directory. Kept separate for focused tests.
	 *
	 * @param string $directory Directory to inspect
	 * @return array<int,string>
	 */
	public static function filesInDirectory($directory)
	{
		if (!is_dir($directory)) return array();

		$entries = dol_dir_list($directory, 'files', 0, '^'.preg_quote(self::FILE_PREFIX, '/'), '(\.meta|_preview.*\.png)$', 'name', SORT_ASC, 1);
		$files = array();
		foreach ($entries as $entry) {
			$path = $directory.'/'.basename($entry['name']);
			if (is_file($path) && filesize($path) > 0) $files[] = basename($entry['name']);
		}
		return $files;
	}

	/**
	 * @param object $order Sales order
	 * @return bool
	 */
	public function hasProof($order)
	{
		return count($this->files($order)) > 0;
	}

	/**
	 * Upload one proof through Dolibarr's native secured upload pipeline.
	 *
	 * @param object $order Sales order
	 * @return int Number of uploaded files, <= 0 on error
	 */
	public function upload($order)
	{
		return dol_add_file_process(
			$this->directory($order),
			0,
			1,
			'cl_customer_proof',
			self::FILE_PREFIX.'__file__',
			null,
			'',
			1,
			$order
		);
	}
}
