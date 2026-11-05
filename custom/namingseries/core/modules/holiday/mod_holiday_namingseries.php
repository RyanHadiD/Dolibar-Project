<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/holiday/modules_holiday.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_holiday_namingseries extends ModelNumRefHolidays
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='leave_request';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($objsoc,$holiday){return $this->nsNext($this->nsDocumentType,$holiday,$objsoc);}
}
