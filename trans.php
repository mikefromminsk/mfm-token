<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-data/utils.php";

$domain = get_string(domain);
$from_address = get_required(from_address);
$to_address = get_string(to_address);
$page = get_int(page, 0);
$size = get_int(size, 10);

$response[trans] = tokenTrans($domain, $from_address, $to_address, $page, $size) ?: [];

commit($response);