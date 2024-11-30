<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/utils.php";

onlyInDebug();

query("DROP TABLE IF EXISTS `accounts`;");
query("CREATE TABLE IF NOT EXISTS `accounts` (
  `domain` varchar(16) COLLATE utf8_bin NOT NULL,
  `address` varchar(256) COLLATE utf8_bin NOT NULL,
  `prev_key` varchar(256) COLLATE utf8_bin NOT NULL,
  `next_hash` varchar(256) COLLATE utf8_bin NOT NULL,
  `delegate` varchar(256) COLLATE utf8_bin DEFAULT NULL,
  `balance` double NOT NULL,
   CONSTRAINT id UNIQUE (`domain`,`address`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");

query("DROP TABLE IF EXISTS `trans`;");
query("CREATE TABLE IF NOT EXISTS `trans` (
  `domain` varchar(16) COLLATE utf8_bin NOT NULL,
  `from` varchar(256) COLLATE utf8_bin NOT NULL,
  `to` varchar(256) COLLATE utf8_bin NOT NULL,
  `key` varchar(256) COLLATE utf8_bin NOT NULL,
  `next_hash` varchar(256) COLLATE utf8_bin NOT NULL,
  `delegate` varchar(256) COLLATE utf8_bin DEFAULT NULL,
  `amount` double NOT NULL,    
  `fee` double NOT NULL,        
  `time` int(11) NOT NULL,    
  PRIMARY KEY (`next_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");

echo json_encode([success => true]);

