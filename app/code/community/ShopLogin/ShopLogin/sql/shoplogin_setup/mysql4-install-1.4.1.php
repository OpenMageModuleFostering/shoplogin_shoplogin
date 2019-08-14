<?php
/*
 * Log in with ShopLogin for Magento
 * https://www.shoplogin.com/for-merchants/
 * v1.4.1 for Magento
 */

$installer = $this;
$installer->startSetup();

$installer->run("
CREATE TABLE IF NOT EXISTS {$this->getTable('shoplogin_customer')} (
`customer_id` int(11) NOT NULL,
`shoplogin_id` int(11) NOT NULL,
`data_token` VARCHAR( 74 ) NOT NULL,
UNIQUE KEY `customer_id` (`customer_id`),
UNIQUE KEY `shoplogin_id` (`shoplogin_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
");

$installer->endSetup();
?>
