<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/stocktransfer/modules_stocktransfer.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_stocktransfer_namingseries extends ModeleNumRefStockTransfer
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='stock_transfer';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($object){ return $this->nsNext($this->nsDocumentType,$object); }
}
