<?php

namespace SIPL\UCRM\wFirma;

use \Webit\WFirmaSDK\Contractors as Contractors;
use \Webit\WFirmaSDK\Payments as Payments;

class ContractorSynchronizer {
	protected $wfirma;
	protected $helper;

	function __construct(\Webit\WFirmaSDK\Entity\ModuleApiFactory $wFirmaApi, UcrmHelper $ucrmHelper) {
		$this->wfirma = $wFirmaApi;
		$this->helper = $ucrmHelper;
	}

	function synchronize($ucrmClientId) {
		$crm = $this->helper->getApi();
		$wFirmaContractors = $this->wfirma->contractorsApi();

		$client = $crm->get('/clients/' . $ucrmClientId);
		$clientInvoiceCountry = [];
		if ($client['invoiceCountryId']) {
			$clientInvoiceCountry = $crm->get('/countries/' . $client['invoiceCountryId']);
		}
		$clientCountry = [];
		if ($client['countryId']) {
			$clientCountry = $crm->get('/countries/' . $client['countryId']);
		}

		$clientAttributeId = $this->helper->getAttributes()->getIdForCode('client');

		$wFirmaId = '';
		foreach ($client['attributes'] as $attribute) {
			if ($attribute['customAttributeId'] === $clientAttributeId) {
				$wFirmaId = $attribute['value'];
			}
		}

		$contractor = new Contractors\Contractor('');
		if ($wFirmaId) {
			$contractor = $wFirmaContractors->get(
				Contractors\ContractorId::create($wFirmaId)
			);
		}

		/** @var Contractors\Contractor $contractor */

		$changed = FALSE;

		$name = $client['clientType'] == 1 ? trim($client['firstName'] . ' ' . $client['lastName']) : $client['companyName'];
		if ($contractor->name() !== $name and $contractor->altName() !== $name) {
			$changed = TRUE;
			$contractor->rename($name, $contractor->altName());
		}

		if ($contractor->nip() != $client['companyTaxId']) {
			$changed = TRUE;
			$contractor->changeNip($client['companyTaxId']);
		}

		if ($client['invoiceAddressSameAsContact']) {
			$invoiceAddress = new Contractors\InvoiceAddress(
				$client['street1'],
				$client['zipCode'],
				$client['city'],
				$clientCountry['code']
			);

			if ($contractor->invoiceAddress() != $invoiceAddress) {
				$changed = TRUE;
				$contractor->changeInvoiceAddress($invoiceAddress);
			}
			if ($contractor->contactAddress() != NULL) {
				$changed = TRUE;
				$contractor->changeContactAddress();
			}
		} else {
			$invoiceAddress = new Contractors\InvoiceAddress(
				$client['invoiceStreet1'],
				$client['invoiceZipCode'],
				$client['invoiceCity'],
				$clientInvoiceCountry['code'] ?? NULL
			);
			$contactAddress = new Contractors\ContactAddress(
				$client['companyName'],
				$client['street1'],
				$client['zipCode'],
				$client['city'],
				$clientCountry['code'] ?? NULL,
				trim($client['companyContactFirstName'] . ' ' . $client['companyContactLastName'])
			);

			if ($contractor->invoiceAddress() != $invoiceAddress) {
				$changed = TRUE;
				$contractor->changeInvoiceAddress($invoiceAddress);
			}

			if ($contractor->contactAddress() != $contactAddress) {
				$changed = TRUE;
				$contractor->changeContactAddress($contactAddress);
			}
		}

		$paymentSettings = new Contractors\PaymentSettings(
			$client['invoiceMaturityDays'],
			Payments\PaymentMethod::transfer(),
			NULL,
			FALSE
		);
		if ($contractor->paymentSettings() != $paymentSettings) {
			$changed = TRUE;
			$contractor->changePaymentSettings($paymentSettings);
		}

		if ($wFirmaId) {
			if ($changed) {
				$wFirmaContractors->edit($contractor);
				return TRUE;
			}
			return FALSE;
		} else {
			$contractor = $wFirmaContractors->add($contractor);
			$crm->patch(
				'/clients/' . $ucrmClientId,
				[
					'attributes' => [
						[
							'customAttributeId' => $clientAttributeId,
							'value' => (string)($contractor->id()->id()),
						],
					],
				]
			);
			return TRUE;
		}
	}
}
