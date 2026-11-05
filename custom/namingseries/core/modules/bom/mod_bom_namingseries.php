<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/bom/modules_bom.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_bom_namingseries extends ModeleNumRefBoms
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='bom';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objprod,$object){ if(is_object($objprod))$object->product_ref=$objprod->ref; return $this->nsNext($this->nsDocumentType,$object); }
}
