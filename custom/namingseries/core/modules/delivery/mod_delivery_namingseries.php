<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/delivery/modules_delivery.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_delivery_namingseries extends ModeleNumRefDeliveryOrder
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='delivery';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($objsoc,$object){return $this->nsNext($this->nsDocumentType,$object,$objsoc);}
	public function getNumRef($objsoc,$objforref){return $this->getNextValue($objsoc,$objforref);}
}
