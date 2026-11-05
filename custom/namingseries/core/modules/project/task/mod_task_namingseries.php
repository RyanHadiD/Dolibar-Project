<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/project/task/modules_task.php';
require_once dol_buildpath('/namingseries/core/modules/namingseries_numref_trait.php', 0);
class mod_task_namingseries extends ModeleNumRefTask
{
	use NamingSeriesNumRefTrait;
	public $version='dolibarr'; public $error=''; public $position=5; public $name='NamingSeries'; protected $nsDocumentType='project_task';
	public function getExample(){ return $this->nsExample($this->nsDocumentType); }
	public function getNextValue($objsoc,$object){ return $this->nsNext($this->nsDocumentType,$object,$objsoc); }
}
