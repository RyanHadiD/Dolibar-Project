<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/accountancy/modules_accountancy.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_bookkeeping_namingseries extends ModeleNumRefBookkeeping
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='bookkeeping';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue(BookKeeping $object){return $this->nsNext($this->nsDocumentType,$object);}
}
