<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->exclude(['tests/_Integration/_Server', '.github', 'bin', 'examples', 'git-hooks'])
    ->in(__DIR__);
$config = new Config();

return $config->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'strict_param' => true,
        'single_class_element_per_statement' => false,
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
