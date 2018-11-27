<?php

$installer = $this;
$installer->startSetup();
$installer->run("
DROP TABLE IF EXISTS `{$installer->getTable('alepay/alepaytoken')}`;    
CREATE TABLE `{$installer->getTable('alepay/alepaytoken')}` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `token` tinytext NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `{$installer->getTable('sales/quote_payment')}` 
ADD `alepay_method` VARCHAR(255) NOT NULL;
  
ALTER TABLE `{$installer->getTable('sales/order_payment')}` 
ADD `alepay_method` VARCHAR(255) NOT NULL;
");
$installer->endSetup();
