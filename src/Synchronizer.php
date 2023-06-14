<?php

namespace SIPL\UCRM\wFirma;

abstract class Synchronizer {
	protected \Webit\WFirmaSDK\Entity\ModuleApiFactory $wfirma;
	protected UcrmHelper $helper;

	function __construct(\Webit\WFirmaSDK\Entity\ModuleApiFactory $wFirmaApi, UcrmHelper $ucrmHelper) {
		$this->wfirma = $wFirmaApi;
		$this->helper = $ucrmHelper;
	}

	/**
	 * @param int $entityId
	 * @return bool TRUE if anything was modified, FALSE otherwise
	 */
	public function synchronize(int $entityId): bool {
		return FALSE;
	}

	/**
	 * @param array $entity
	 * @return bool TRUE if anything was deleted, FALSE otherwise
	 */
	public function delete(array $entity): bool {
		return FALSE;
	}
}
