<?php

require_once dol_buildpath('/namingseries/class/namingseries.class.php', 0);

trait NamingSeriesNumRefTrait
{
	protected function nsNext($documentType, $object = null, $thirdparty = null, $mode = 'next')
	{
		global $db, $conf;
		try {
			$engine = new NamingSeries($db, $conf->entity);
			if ($mode === 'last') return $engine->last($documentType, $object, $thirdparty);
			if (empty($db->transaction_opened)) {
				$series = $engine->resolveSeries($documentType, $object);
				return $series ? $engine->preview($series, $object, $thirdparty) : '';
			}
			return $engine->next($documentType, $object, $thirdparty);
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			dol_syslog(get_class($this).' '.$this->error, LOG_ERR);
			return '';
		}
	}

	protected function nsExample($documentType)
	{
		global $db, $conf;
		try {
			$engine = new NamingSeries($db, $conf->entity);
			$series = $engine->resolveSeries($documentType);
			return $series ? $engine->preview($series) : 'NotConfigured';
		} catch (Throwable $e) {
			$this->error = $e->getMessage();
			return 'Error';
		}
	}

	public function info($langs)
	{
		return $langs->trans('NSGenericNumberingDescription');
	}

	public function canBeActivated($object)
	{
		global $db, $conf;
		$engine = new NamingSeries($db, $conf->entity);
		$series = $engine->resolveSeries($this->nsDocumentType);
		if (!$series) { $this->error = 'NoActiveNamingSeries'; return false; }
		try { $engine->validatePattern($series->pattern); return true; }
		catch (Throwable $e) { $this->error = $e->getMessage(); return false; }
	}
}
