<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-data/utils.php";

$address = get_required(address);

$response[accounts] = getAccounts($address);

commit($response);