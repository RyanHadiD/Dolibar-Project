<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/supplier_proposal/modules_supplier_proposal.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_supplier_proposal_namingseries extends ModeleNumRefSupplierProposal
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='supplier_proposal';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$supplier_proposal){ return $this->nsNext($this->nsDocumentType,$supplier_proposal,$objsoc); }
}
