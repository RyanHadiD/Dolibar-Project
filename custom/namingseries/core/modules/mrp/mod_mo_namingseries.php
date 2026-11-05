<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/mrp/modules_mo.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_mo_namingseries extends ModeleNumRefMos
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='manufacturing_order';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objprod,$object){ if(is_object($objprod))$object->product_ref=$objprod->ref; return $this->nsNext($this->nsDocumentType,$object); }
}
