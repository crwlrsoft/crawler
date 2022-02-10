<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);
$config = new Config();

return $config->setFinder($finder)
    ->setRules([
        '@PSR2' => true,
        'strict_param' => true,
        'single_class_element_per_statement' => false,
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
