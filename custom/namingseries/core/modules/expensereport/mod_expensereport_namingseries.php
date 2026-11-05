<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/expensereport/modules_expensereport.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_expensereport_namingseries extends ModeleNumRefExpenseReport
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='expense_report';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($object){ return $this->nsNext($this->nsDocumentType,$object); }
}
