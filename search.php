<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$search_text = get_required(search_text);
$page = get_int(page, 0);
$size = get_int(size, 10);

$search_text = strtolower($search_text);

$response[tokens] =
    select("select * from tokens where `domain` like '%$search_text%'"
        . " limit " . $page * $size . ", $size");

echo json_encode($response);