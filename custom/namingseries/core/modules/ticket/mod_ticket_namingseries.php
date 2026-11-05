<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/ticket/modules_ticket.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php',0);
class mod_ticket_namingseries extends ModeleNumRefTicket
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='ticket';
	public function getExample(){return $this->nsExample($this->nsDocumentType);}
	public function getNextValue($objsoc,$ticket){return $this->nsNext($this->nsDocumentType,$ticket,$objsoc);}
}
