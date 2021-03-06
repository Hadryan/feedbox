<?php

use Nogo\Feedbox\Helper\ConfigLoader;
use Slim\Slim;

define('ROOT_DIR', dirname(__FILE__));

require_once ROOT_DIR . '/vendor/autoload.php';

// Load config files
// TODO cache
$configLoader = new ConfigLoader(
    ROOT_DIR . '/src/Nogo/Feedbox/Resources/config/default.yml',
    ROOT_DIR . '/data/config.yml'
);

$app = new Slim($configLoader->getConfig());