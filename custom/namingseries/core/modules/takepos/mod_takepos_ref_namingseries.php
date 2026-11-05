<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/takepos/modules_takepos.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_takepos_ref_namingseries extends ModeleNumRefTakepos
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='takepos_invoice';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($objsoc=null,$invoice=null,$mode='next'){return $this->nsNext($this->nsDocumentType,$invoice,$objsoc,$mode);}
}
