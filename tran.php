<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$next_hash = get_string(next_hash);

$response[tran] = tokenTran($next_hash);

commit($response);