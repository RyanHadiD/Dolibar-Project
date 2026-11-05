<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/contract/modules_contract.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_contract_namingseries extends ModelNumRefContracts
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='contract';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$contract){ return $this->nsNext($this->nsDocumentType,$contract,$objsoc); }
}
