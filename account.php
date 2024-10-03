<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$domain = get_required(domain);
$address = get_required(address);

$response = getAccount($domain, $address);

if (!$response) {
    error("Address not found");
}

commit($response);
