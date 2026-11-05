<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_propale_namingseries extends ModeleNumRefPropales
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='sales_proposal';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$propal){ return $this->nsNext($this->nsDocumentType,$propal,$objsoc); }
}
