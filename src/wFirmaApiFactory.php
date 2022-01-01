<?php

namespace SIPL\UCRM\wFirma;

class wFirmaApiFactory {
	protected $ucrmHelper;

	function __construct(UcrmHelper $ucrmHelper) {
		$this->ucrmHelper = $ucrmHelper;
	}

	function create(): \Webit\WFirmaSDK\Entity\ModuleApiFactory {
		$config = $this->ucrmHelper->getConfig();
		$wFirmaAuth = new \Webit\WFirmaSDK\Auth\BasicAuth($config['wfirma_username'], $config['wfirma_password']);

		$wFirmaEntityApiFactory = new \Webit\WFirmaSDK\Entity\EntityApiFactory();
		$wFirmaEntityApi = $wFirmaEntityApiFactory->create($wFirmaAuth);

		return new \Webit\WFirmaSDK\Entity\ModuleApiFactory($wFirmaEntityApi);
	}
}
