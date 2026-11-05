<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_facture_namingseries extends ModeleNumRefFactures
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='customer_invoice';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$invoice,$mode='next'){ return $this->nsNext($this->nsDocumentType,$invoice,$objsoc,$mode); }
	public function getNumRef($objsoc,$objforref,$mode='next'){ return $this->getNextValue($objsoc,$objforref,$mode); }
}
