<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);

class mod_commande_namingseries extends ModeleNumRefCommandes
{
	use NamingSeriesNumRefTrait;
	public $version = 'dolibarr';
	public $error = '';
	public $position = 5;
	public $name = 'NamingSeries';
	protected $nsDocumentType = 'sales_order';

	public function info($langs)
	{
		return $langs->trans('NSGenericNumberingDescription');
	}

	public function getExample()
	{
		return $this->nsExample($this->nsDocumentType);
	}

	public function getNextValue($objsoc, $object)
	{
		return $this->nsNext($this->nsDocumentType, $object, $objsoc);
	}
}
