<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_fichinter_namingseries extends ModeleNumRefFicheinter
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='intervention';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc='',$object=''){ return $this->nsNext($this->nsDocumentType,$object,is_object($objsoc)?$objsoc:null); }
}
