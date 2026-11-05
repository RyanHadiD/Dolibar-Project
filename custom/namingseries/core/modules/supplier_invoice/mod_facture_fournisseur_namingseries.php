<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/supplier_invoice/modules_facturefournisseur.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_facture_fournisseur_namingseries extends ModeleNumRefSuppliersInvoices
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='supplier_invoice';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$object,$mode='next'){ return $this->nsNext($this->nsDocumentType,$object,$objsoc,$mode); }
	public function getNumRef($objsoc,$objforref,$mode='next'){ return $this->getNextValue($objsoc,$objforref,$mode); }
}
