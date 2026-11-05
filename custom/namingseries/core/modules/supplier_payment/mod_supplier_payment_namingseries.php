<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/supplier_payment/modules_supplier_payment.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_supplier_payment_namingseries extends ModeleNumRefSupplierPayments
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='supplier_payment';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$object){ return $this->nsNext($this->nsDocumentType,$object,$objsoc); }
}
