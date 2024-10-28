<?php
require_once $_SERVER[DOCUMENT_ROOT] . "/mfm-data/utils.php";

$domain = get_string(domain);
$address = get_required(address);
$page = get_int(page, 1);
$size = get_int(size, 10);

$response[trans] = tokenTrans($domain, $address, $page, $size);

commit($response);