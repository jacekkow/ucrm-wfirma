<?php
require_once(__DIR__ . '/vendor/autoload.php');

try {
	require_once(__DIR__ . '/hook_enable.php');

	$helper = new \SIPL\UCRM\wFirma\UcrmHelper();
	$event = $helper->getCurrentEvent();

	$wFirmaApiFactory = new \SIPL\UCRM\wFirma\wFirmaApiFactory($helper);
	$wFirmaApi = $wFirmaApiFactory->create();

	if (!isset($event['entity'])) {
		throw new Exception('Webhook entity empty!');
	} elseif ($event['entity'] === 'client') {
		$synchronizer = new \SIPL\UCRM\wFirma\ContractorSynchronizer($wFirmaApi, $helper);
	} elseif ($event['entity'] === 'invoice') {
		$synchronizer = new \SIPL\UCRM\wFirma\InvoiceSynchronizer($wFirmaApi, $helper);
	} elseif ($event['entity'] === 'payment') {
		$synchronizer = new \SIPL\UCRM\wFirma\PaymentSynchronizer($wFirmaApi, $helper);
	} elseif ($event['entity'] === 'webhook') {
		echo 'Webhook OK! UCRM version: ' . $helper->getVersion();
		die();
	} else {
		echo 'Nothing to do with entity ' . $event['entity'];
		die();
	}

	if ($event['changeType'] === 'delete') {
		if($synchronizer->delete($event['extraData']['entity'])) {
			echo 'Object deleted';
		} else {
			echo 'Nothing to do';
		}
	} else {
		if ($synchronizer->synchronize($event['entityId'])) {
			echo 'Object synchronized';
		} else {
			echo 'Nothing to do';
		}
	}
} catch (Exception $e) {
	header('HTTP/1.1 500 Internal Server Error');
	echo $e;
}
