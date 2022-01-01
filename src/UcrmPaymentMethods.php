<?php

namespace SIPL\UCRM\wFirma;

class UcrmPaymentMethods {
	protected $api;
	protected static $definition = [
		'Credit card' => '',
		'Compensation' => '',
	];

	function __construct(UcrmHelper $ucrmHelper) {
		$this->api = $ucrmHelper->getApi();
		$this->update();
	}

	function get(string $method): string {
		if (!isset(self::$definition[$method])) {
			throw new \RuntimeException('Unsupported payment method: ' . $method);
		}
		return self::$definition[$method];
	}

	function update() {
		$methods = $this->api->get('/payment-methods');
		foreach ($methods as $method) {
			if (isset(self::$definition[$method['name']])) {
				self::$definition[$method['name']] = $method['id'];
			}
		}
		foreach (self::$definition as $name => $id) {
			if (empty($id)) {
				$result = $this->api->post(
					'/payment-methods',
					[
						'name' => $name,
					]
				);
				self::$definition[$name] = $result['id'];
			}
		}
	}
}
