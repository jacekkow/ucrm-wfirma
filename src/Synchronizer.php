<?php

namespace SIPL\UCRM\wFirma;

abstract class Synchronizer {
	protected $wfirma;
	protected $helper;

	function __construct(\Webit\WFirmaSDK\Entity\ModuleApiFactory $wFirmaApi, UcrmHelper $ucrmHelper) {
		$this->wfirma = $wFirmaApi;
		$this->helper = $ucrmHelper;
	}

	public function synchronize(int $entityId) {
		return FALSE;
	}

	public function delete(array $entity) {
		return FALSE;
	}
}
