<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/asset/modules_asset.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_asset_namingseries extends ModeleNumRefAsset
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='asset';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($object){return $this->nsNext($this->nsDocumentType,$object);}
}
