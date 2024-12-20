<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-data/utils.php";

$domain = get_required(domain);
$address = get_required(address);

$response[account] = getAccount($domain, $address);

if (!$response[account]) error("Address not found");

commit($response);
