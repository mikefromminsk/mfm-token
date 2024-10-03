<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$domain = get_required(domain);
$from_address = get_required(from_address);
$to_address = get_required(to_address);
$amount = get_int_required(amount);
$pass = get_required(pass);
$delegate = get_string(delegate);

tokenSend($domain, $from_address, $to_address, $amount, $pass, $delegate);

commit();
