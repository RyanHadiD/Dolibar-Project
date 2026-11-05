<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/reception/modules_reception.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_reception_namingseries extends ModelNumRefReception
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='reception';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$reception){ return $this->nsNext($this->nsDocumentType,$reception,$objsoc); }
}
