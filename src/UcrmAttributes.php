<?php

namespace SIPL\UCRM\wFirma;

class UcrmAttributes {
	protected $attributesFile;
	protected $api;

	protected static $definition = [
		'wFirma Customer ID' => [
			'code' => 'client',
			'type' => 'client',
		],
		'wFirma Invoice ID' => [
			'code' => 'invoice',
			'type' => 'invoice',
		],
		'wFirma Payment ID' => [
			'code' => 'payment',
			'type' => 'payment',
		],
	];
	protected $ids = [];

	function __construct(UcrmHelper $ucrmHelper) {
		$this->attributesFile = $ucrmHelper->getRootDirectory() . '/data/attributes.json';
		$this->api = $ucrmHelper->getApi();

		if (!is_file($this->attributesFile)) {
			$this->updateMapping();
			$this->saveMapping();
		} else {
			$this->ids = json_decode(file_get_contents($this->attributesFile), TRUE);
		}
	}

	function getIdForCode($attributeCode) {
		if (!isset($this->ids[$attributeCode])) {
			throw new \RuntimeException('Attribute for ' . $attributeCode . ' does not exist');
		}
		return $this->ids[$attributeCode];
	}

	function updateMapping() {
		$attributes = $this->api->get('/custom-attributes');
		foreach ($attributes as $attribute) {
			$data = self::$definition[$attribute['name']] ?? NULL;
			if ($data !== NULL) {
				$this->ids[$data['code']] = $attribute['id'];
			}
		}

		$changed = FALSE;
		foreach (self::$definition as $name => $data) {
			if (isset($this->ids[$data['code']])) {
				continue;
			}

			$result = $this->api->post(
				'/custom-attributes',
				[
					'name' => $name,
					'clientZoneVisible' => FALSE,
					'attributeType' => $data['type'],
				]
			);
			$this->ids[$data['code']] = $result['id'];
			$changed = TRUE;
		}

		return $changed;
	}

	function saveMapping() {
		if (!is_dir(dirname($this->attributesFile))) {
			mkdir(dirname($this->attributesFile));
		}

		file_put_contents($this->attributesFile, json_encode($this->ids));
	}
}
