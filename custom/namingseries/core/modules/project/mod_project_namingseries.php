<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_project_namingseries extends ModeleNumRefProjects
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='project';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$project){ return $this->nsNext($this->nsDocumentType,$project,$objsoc); }
}
