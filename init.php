<?php
include_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

onlyInDebug();

requestEquals("/mfm-data/init.php");

$address = get_required(address);
$password = get_required(password);

query("DROP TABLE IF EXISTS `accounts`;");
query("CREATE TABLE IF NOT EXISTS `accounts` (
  `domain` varchar(256) COLLATE utf8_bin NOT NULL,
  `address` varchar(256) COLLATE utf8_bin NOT NULL,
  `prev_key` varchar(256) COLLATE utf8_bin NOT NULL,
  `next_hash` varchar(256) COLLATE utf8_bin NOT NULL,
  `delegate` varchar(256) COLLATE utf8_bin DEFAULT NULL,
  `balance` float NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");

query("DROP TABLE IF EXISTS `trans`;");
query("CREATE TABLE IF NOT EXISTS `trans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(256) COLLATE utf8_bin NOT NULL,
  `from` varchar(256) COLLATE utf8_bin NOT NULL,
  `to` varchar(256) COLLATE utf8_bin NOT NULL,
  `key` varchar(256) COLLATE utf8_bin NOT NULL,
  `next_hash` varchar(256) COLLATE utf8_bin NOT NULL,
  `delegate` varchar(256) COLLATE utf8_bin DEFAULT NULL,
  `amount` float NOT NULL,    
  `time` int(11) NOT NULL,    
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");

$amount = 1000000000;
$gas_domain = get_required(gas_domain);

tokenAccountReg($gas_domain, $address, $password, 100000000);

$response[success] = true;

echo json_encode($response);

