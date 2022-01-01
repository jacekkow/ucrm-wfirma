<?php
require_once(__DIR__ . '/vendor/autoload.php');

$helper = new \SIPL\UCRM\wFirma\UcrmHelper();
$attributes = $helper->getAttributes();
if ($attributes->updateMapping()) {
	$attributes->saveMapping();
}
$paymentMethods = $helper->getPaymentMethods();
