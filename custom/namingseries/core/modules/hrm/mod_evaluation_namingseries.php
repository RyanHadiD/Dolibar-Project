<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/hrm/modules_evaluation.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_evaluation_namingseries extends ModeleNumRefEvaluation
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='hr_evaluation';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($object){return $this->nsNext($this->nsDocumentType,$object);}
}
