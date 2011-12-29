<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

	// register additional driver
$TYPO3_CONF_VARS['SYS']['fal']['registeredDrivers']['AmazonS3'] = 'Tx_FalAmazonS3_Driver_AmazonS3Driver',

