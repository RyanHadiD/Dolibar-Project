<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_expedition_namingseries extends ModelNumRefExpedition
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='shipment';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$shipment){ return $this->nsNext($this->nsDocumentType,$shipment,$objsoc); }
}
