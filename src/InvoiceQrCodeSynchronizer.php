<?php

namespace SIPL\UCRM\wFirma;

class InvoiceQrCodeSynchronizer {
	protected UcrmHelper $helper;

	public function __construct(UcrmHelper $helper) {
		$this->helper = $helper;
	}

	public function synchronize(string $ucrmInvoiceId, array $previousEntity = []): void {
		$ksefUrlAttribute = $this->helper->getAttributes()->getIdForCode('ksef-url');
		$ksefQrCodeAttribute = $this->helper->getAttributes()->getIdForCode('ksef-qr-code');

		$invoice = $this->helper->getApi()->get('/invoices/' . $ucrmInvoiceId);
		$currentUrl = '';
		$currentQrCode = '';
		foreach ($invoice['attributes'] ?? [] as $attribute) {
			if ($attribute['customAttributeId'] == $ksefUrlAttribute) {
				$currentUrl = $attribute['value'];
			}
			if ($attribute['customAttributeId'] == $ksefQrCodeAttribute) {
				$currentQrCode = $attribute['value'];
			}
		}

		$newQrCode = null;
		$expectedQrCodeUrl = $this->helper->getSelfUrl() . '_plugins/wfirma/public.php?barcode=';
		if ($currentUrl == '' && $currentQrCode != '') {
			$newQrCode = '';
		}
		if ($currentUrl != '' && $currentQrCode != $expectedQrCodeUrl) {
			$newQrCode = $expectedQrCodeUrl;
		}

		if ($newQrCode != null) {
			$this->helper->getApi()->patch('/invoices/' . $ucrmInvoiceId, [
				'attributes' => [
					[
						'customAttributeId' => $ksefQrCodeAttribute,
						'value' => $newQrCode,
					],
				],
			]);
			$this->helper->getApi()->patch('/invoices/' . $ucrmInvoiceId . '/regenerate-pdf', []);
		}
	}
}
