<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

	// register additional driver
$TYPO3_CONF_VARS['SYS']['fal']['registeredDrivers']['AmazonS3'] = array(
	'class' => 'Tx_FalAmazonS3_Driver_AmazonS3Driver',
	'label' => 'Amazon S3',
	'flexFormDS' => 'EXT:fal_amazons3/Configuration/AmazonS3DriverFlexForm.xml'
);
