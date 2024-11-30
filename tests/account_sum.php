<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$domain = get_required(domain);

$response[success] = scalar("select sum(balance) from accounts where `domain` = '$domain'");

echo json_encode($response);