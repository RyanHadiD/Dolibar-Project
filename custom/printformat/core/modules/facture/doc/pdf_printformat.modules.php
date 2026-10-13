<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/custom/printformat/class/printformatgenerator.class.php';

class pdf_printformat extends ModelePDFFactures
{
	public $db;
	public $name;
	public $description;
	public $type;
	public $version = '0.1.0';
	public $update_main_doc_field = 1;

	public function __construct($db)
	{
		global $langs;
		$this->db = $db;
		$this->name = 'printformat';
		$this->description = 'HTML/CSS Print Format';
		$this->type = 'pdf';
		$this->option_logo = 1;
		$this->option_tva = 1;
		$this->option_multilang = 1;
		$this->option_draft_watermark = 0;
		$langs->load('printformat@printformat');
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		$generator = new PrintFormatDocumentGenerator($this->db);
		$result = $generator->write($object, $outputlangs, 'invoice', GETPOSTINT('printformat_id'));
		$this->error = $generator->error;
		$this->errors = $generator->errors;
		$this->warnings = $generator->warnings;
		$this->result = $generator->result;
		return $result;
	}
}
