<?php

use Cranberry\Core\File;

/*
 * Set data directory
 */
$dataDirectory = new File\Directory( '/var/opt/HAL4500' );
$app->registerCommandObject( 'dataDirectory', $dataDirectory );
