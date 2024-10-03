<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

$domain = get_string(domain);
$address = get_required(address);
$page = get_int(page, 1);
$size = get_int(size, 10);

$sql = "select * from trans where 1 = 1";

if ($address != null){
    $sql .= " and (`from` = '$address' or `to` = '$address')";
}

if ($domain != null){
    $sql .= " and `domain` = '$domain'";
}

$sql .= " order by time desc limit " . ($page - 1) * $size . ", $size";

$response[trans] = select($sql);

commit($response);