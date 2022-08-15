<?php

namespace SIPL\UCRM\wFirma;

use \Webit\WFirmaSDK\Invoices as Invoices;
use \Webit\WFirmaSDK\Payments as Payments;

class PaymentSynchronizer extends Synchronizer {
	protected $ucrmMainDir;

	function __construct(\Webit\WFirmaSDK\Entity\ModuleApiFactory $wFirmaApi, UcrmHelper $ucrmHelper) {
		parent::__construct($wFirmaApi, $ucrmHelper);

		$backtrace = debug_backtrace();
		$backtrace = end($backtrace);
		// (...)/web/_plugins/wfirma/public.php
		$this->ucrmMainDir = dirname(dirname(dirname(dirname($backtrace['file']))));
	}

	function comparePayment(Payments\Payment $p1, Payments\Payment $p2) {
		return
			[$p1->objectName(), $p1->objectId(), $p1->amount()->value(), $p1->date()->format('Y-m-d'), $p1->paymentMethod()]
			<=>
			[$p2->objectName(), $p2->objectId(), $p2->amount()->value(), $p2->date()->format('Y-m-d'), $p2->paymentMethod()];
	}

	function synchronize(int $ucrmPaymentId) {
		$crm = $this->helper->getApi();
		$wFirmaPaymentsApi = $this->wfirma->paymentsApi();

		$invoiceAttributeId = $this->helper->getAttributes()->getIdForCode('invoice');
		$paymentAttributeId = $this->helper->getAttributes()->getIdForCode('payment');

		$paymentData = $crm->get('/payments/' . $ucrmPaymentId);

		$paymentDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $paymentData['createdDate']);
		if ($paymentData['providerPaymentTime']) {
			$paymentDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $paymentData['providerPaymentTime']);
		}

		$paymentMethod = Payments\PaymentMethod::transfer();
		switch ($paymentData['methodId'] ?? $paymentData['method']) {
			case 2:
			case '6efe0fa8-36b2-4dd1-b049-427bffc7d369':
				$paymentMethod = Payments\PaymentMethod::cash();
				break;
			case $this->helper->getPaymentMethods()->get('Compensation'):
				$paymentMethod = Payments\PaymentMethod::compensation();
				break;
			case $this->helper->getPaymentMethods()->get('Credit card'):
				$paymentMethod = Payments\PaymentMethod::paymentCard();
				break;
		}

		$expectedPayments = [];
		foreach ($paymentData['paymentCovers'] as $covered) {
			if (!$covered['invoiceId']) {
				continue;
			}

			$invoiceData = $crm->get('/invoices/' . $covered['invoiceId']);
			$wFirmaInvoiceId = NULL;
			foreach ($invoiceData['attributes'] as $attribute) {
				if ($attribute['customAttributeId'] === $invoiceAttributeId) {
					$wFirmaInvoiceId = $attribute['value'];
				}
			}

			if (!$wFirmaInvoiceId) {
				continue;
			}

			$expectedPayments[] = Payments\Payment::forInvoiceOfId(
				Invoices\InvoiceId::create($wFirmaInvoiceId),
				Payments\PaymentAmount::forCurrencyAccount($covered['amount']),
				$paymentDate,
				$paymentMethod
			);
		}
		/** @var Payments\Payment[] $expectedPayments */

		$wFirmaIds = '';
		foreach ($paymentData['attributes'] as $attribute) {
			if ($attribute['customAttributeId'] === $paymentAttributeId) {
				$wFirmaIds = $attribute['value'];
			}
		}
		if (strlen($wFirmaIds) > 0) {
			$wFirmaIds = explode(',', $wFirmaIds);
		} else {
			$wFirmaIds = [];
		}

		$existingPayments = [];
		$wFirmaPaymentIds = [];

		foreach ($wFirmaIds as $id) {
			$payment = $wFirmaPaymentsApi->get(
				Payments\PaymentId::create($id)
			);
			$existingPayments[] = $payment;
		}
		/** @var Payments\Payment[] $existingPayments */


		$changed = FALSE;

		foreach ($existingPayments as $o1 => $p1) {
			foreach ($expectedPayments as $o2 => $p2) {
				if ($this->comparePayment($p1, $p2) === 0) {
					unset($existingPayments[$o1]);
					unset($expectedPayments[$o2]);
					$wFirmaPaymentIds[] = (string)$p1->id()->id();
					break;
				}
			}
		}

		foreach ($existingPayments as $o1 => $p1) {
			foreach ($expectedPayments as $o2 => $p2) {
				if ($p1->objectName() === $p2->objectName() && $p1->objectId() === $p2->objectId()) {
					if ($p1->amount()->value() != $p2->amount()->value()) {
						$p1->changeAmount($p2->amount());
					}
					if ($p1->date()->format('Y-m-d') != $p2->date()->format('Y-m-d')) {
						$p1->changeDate($p2->date());
					}
					if ($p1->paymentMethod() != $p2->paymentMethod()) {
						$p1->changePaymentMethod($p2->paymentMethod());
					}

					$p1 = $wFirmaPaymentsApi->edit($p1);
					$changed = TRUE;

					unset($existingPayments[$o1]);
					unset($expectedPayments[$o2]);
					$wFirmaPaymentIds[] = (string)$p1->id()->id();
					break;
				}
			}
		}

		foreach ($existingPayments as $o1 => $p1) {
			$wFirmaPaymentsApi->delete($p1->id());
			$changed = TRUE;
		}

		foreach ($expectedPayments as $o2 => $p2) {
			$p2 = $wFirmaPaymentsApi->add($p2);
			$wFirmaPaymentIds[] = (string)$p2->id()->id();
			$changed = TRUE;
		}

		sort($wFirmaPaymentIds);

		if ($wFirmaIds != $wFirmaPaymentIds) {
			$crm->patch(
				'/payments/' . $ucrmPaymentId,
				[
					'attributes' => [
						[
							'customAttributeId' => $paymentAttributeId,
							'value' => join(',', $wFirmaPaymentIds),
						],
					],
				]
			);
			$changed = TRUE;
		}

		return $changed;
	}
}
