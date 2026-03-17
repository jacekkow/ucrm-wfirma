<?php

namespace SIPL\UCRM\wFirma;

class InvoiceQrCodeSynchronizer {
	protected UcrmHelper $helper;

	public function __construct(UcrmHelper $helper) {
		$this->helper = $helper;
	}

	public function synchronize(string $ucrmInvoiceId, array $previousEntity = []): void {
		$ksefUrlAttribute = $this->helper->getAttributes()->getIdForCode('ksef-url');
		$qrCodeGenUrlAttribute = $this->helper->getAttributes()->getIdForCode('qr-code-gen-url');

		$invoice = $this->helper->getApi()->get('/invoices/' . $ucrmInvoiceId);
		$currentKsefUrl = '';
		$currentQrCodeGenUrl = '';
		foreach ($invoice['attributes'] ?? [] as $attribute) {
			if ($attribute['customAttributeId'] == $ksefUrlAttribute) {
				$currentKsefUrl = $attribute['value'];
			}
			if ($attribute['customAttributeId'] == $qrCodeGenUrlAttribute) {
				$currentQrCodeGenUrl = $attribute['value'];
			}
		}

		$newQrCodeGenUrl = null;
		$expectedQrCodeGenUrl = $this->helper->getSelfUrl() . '_plugins/wfirma/public.php?barcode=';
		if ($currentQrCodeGenUrl != $expectedQrCodeGenUrl) {
			$newQrCodeGenUrl = $expectedQrCodeGenUrl;
		}

		if ($newQrCodeGenUrl != null) {
			$this->helper->getApi()->patch('/invoices/' . $ucrmInvoiceId, [
				'attributes' => [
					[
						'customAttributeId' => $qrCodeGenUrlAttribute,
						'value' => $newQrCodeGenUrl,
					],
				],
			]);
			$this->helper->getApi()->patch('/invoices/' . $ucrmInvoiceId . '/regenerate-pdf', []);
		}
	}
}
