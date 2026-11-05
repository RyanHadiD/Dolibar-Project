<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/product_batch/modules_product_batch.class.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_sn_namingseries extends ModeleNumRefBatch
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='product_serial';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($objsoc,$object){return $this->nsNext($this->nsDocumentType,$object,$objsoc);}
}
