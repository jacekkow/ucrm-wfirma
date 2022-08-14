<?php

namespace SIPL\UCRM\wFirma;

use \Webit\WFirmaSDK\Contractors as Contractors;
use \Webit\WFirmaSDK\Invoices as Invoices;
use \Webit\WFirmaSDK\Payments as Payments;

class InvoiceSynchronizer {
	protected $wfirma;
	protected $helper;

	function __construct(\Webit\WFirmaSDK\Entity\ModuleApiFactory $wFirmaApi, UcrmHelper $ucrmHelper) {
		$this->wfirma = $wFirmaApi;
		$this->helper = $ucrmHelper;
	}

	function getContractorId($clientId, $synchronize = TRUE) {
		if ($synchronize) {
			$synchronizer = new ContractorSynchronizer($this->wfirma, $this->helper);
			$synchronizer->synchronize($clientId);
		}

		$clientAttributeId = $this->helper->getAttributes()->getIdForCode('client');

		$crm = $this->helper->getApi();
		$clientData = $crm->get('/clients/' . $clientId);
		$wFirmaId = NULL;
		foreach ($clientData['attributes'] as $attribute) {
			if ($attribute['customAttributeId'] === $clientAttributeId) {
				$wFirmaId = $attribute['value'];
			}
		}
		if ($wFirmaId === NULL || $wFirmaId === '') {
			throw new \RuntimeException('Failed to get client ID for invoice');
		}
		return Contractors\ContractorId::create($wFirmaId);
	}

	function getTaxes() {
		$crm = $this->helper->getApi();
		$taxesData = $crm->get('/taxes');

		$taxes = [];
		foreach ($taxesData as $tax) {
			$taxes[$tax['id']] = $tax;
		}
		return $taxes;
	}

	function compareInvoicesContent(Invoices\InvoicesContent $c1, Invoices\InvoicesContent $c2) {
		return
			[$c1->name(), $c1->unit(), $c1->count(), $c1->price(), $c1->vat(), $c1->discount()]
			<=>
			[$c2->name(), $c2->unit(), $c2->count(), $c2->price(), $c2->vat(), $c2->discount()];
	}

	function synchronize($ucrmInvoiceId) {
		$crm = $this->helper->getApi();
		$wFirmaInvoices = $this->wfirma->invoicesApi();

		$invoiceAttributeId = $this->helper->getAttributes()->getIdForCode('invoice');

		$invoiceData = $crm->get('/invoices/' . $ucrmInvoiceId);
		if ($invoiceData['status'] == 0) {
			// Ignore drafts
			return FALSE;
		}

		$taxes = $this->getTaxes();

		$wFirmaId = '';
		foreach ($invoiceData['attributes'] as $attribute) {
			if ($attribute['customAttributeId'] === $invoiceAttributeId) {
				$wFirmaId = $attribute['value'];
			}
		}

		$wFirmaContractorId = $this->getContractorId($invoiceData['clientId']);
		$payment = Invoices\Payment::create(
			Payments\PaymentMethod::transfer(),
			new \DateTime($invoiceData['dueDate'])
		);

		if ($wFirmaId) {
			$invoice = $wFirmaInvoices->get(\Webit\WFirmaSDK\Invoices\InvoiceId::create($wFirmaId));
		} else {
			$type = \Webit\WFirmaSDK\Invoices\Type::vat();
			$series = \Webit\WFirmaSDK\Series\SeriesId::create($this->helper->getConfig()['wfirma_series_invoice'] ?? null);
			if ($invoiceData['proforma']) {
				$type = \Webit\WFirmaSDK\Invoices\Type::proformaVat();
				$series = \Webit\WFirmaSDK\Series\SeriesId::create($this->helper->getConfig()['wfirma_series_proforma'] ?? null);
			}
			$date = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $invoiceData['createdDate']);
			$invoice = \Webit\WFirmaSDK\Invoices\Invoice::forContractorOfId(
				$wFirmaContractorId,
				$payment,
				$type,
				$series,
				$date,
				\Webit\WFirmaSDK\Invoices\Disposal::withDate($date)
			);
		}

		/** @var \Webit\WFirmaSDK\Invoices\Invoice $invoice */
		$changed = FALSE;

		if ($invoice->payment()->paymentMethod() != $payment->paymentMethod()
			|| $invoice->payment()->paymentDate() != $payment->paymentDate()) {
			$invoice->changePayment($payment);
			$changed = TRUE;
		}

		if ($invoice->priceType() != Invoices\PriceType::brutto()) {
			$invoice->changePriceType(Invoices\PriceType::brutto());
			$changed = TRUE;
		}

		if ($invoice->schema() != Invoices\Schema::vatInvoiceDate()) {
			$invoice->changeSchema(Invoices\Schema::vatInvoiceDate());
			$changed = TRUE;
		}

		$currentContents = $invoice->invoiceContents();
		$expectedContents = [];
		foreach ($invoiceData['items'] as $i => $item) {
			$tax = $taxes[$item['tax1Id']];
			$content = Invoices\InvoicesContent::fromName(
				$item['label'],
				$item['unit'] ?? 'usł.',
				$item['quantity'],
				$item['price'],
				$tax['rate']
			);
			$expectedContents[] = $content;
		}

		$overrideContents = FALSE;
		if (count($currentContents) != count($expectedContents)) {
			$overrideContents = TRUE;
		} else {
			for ($i = 0; $i < count($currentContents); $i++) {
				if ($this->compareInvoicesContent($currentContents[$i], $expectedContents[$i]) != 0) {
					$overrideContents = TRUE;
					break;
				}
			}
		}

		if ($overrideContents) {
			$changed = TRUE;
			while (count($invoice->invoiceContents()) != 0) {
				$invoice->removeInvoiceContent($invoice->invoiceContents()[0]);
			}
			foreach ($expectedContents as $content) {
				$invoice->addInvoiceContent($content);
			}
		}

		if ($wFirmaId) {
			if ($changed) {
				$wFirmaInvoices->edit($invoice);
				return TRUE;
			}
			return FALSE;
		} else {
			$invoice = $wFirmaInvoices->add($invoice);
			$crm->patch(
				'/invoices/' . $ucrmInvoiceId,
				[
					'attributes' => [
						[
							'customAttributeId' => $invoiceAttributeId,
							'value' => (string)($invoice->id()->id()),
						],
					],
				]
			);
			return TRUE;
		}
	}
}
