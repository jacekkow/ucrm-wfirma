<?php

namespace SIPL\UCRM\wFirma;

class wFirmaApiFactory {
	protected UcrmHelper $ucrmHelper;

	function __construct(UcrmHelper $ucrmHelper) {
		$this->ucrmHelper = $ucrmHelper;
	}

	function create(): \Webit\WFirmaSDK\Entity\ModuleApiFactory {
		$config = $this->ucrmHelper->getConfig();
		$wFirmaAuth = new \Webit\WFirmaSDK\Auth\ApiKeysAuth($config['wfirma_access_key'], $config['wfirma_secret_key'], $config['wfirma_app_key']);

		$wFirmaEntityApiFactory = new \Webit\WFirmaSDK\Entity\EntityApiFactory();
		$wFirmaEntityApi = $wFirmaEntityApiFactory->create($wFirmaAuth);

		return new \Webit\WFirmaSDK\Entity\ModuleApiFactory($wFirmaEntityApi);
	}
}
