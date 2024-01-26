<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->exclude(['tests/_Integration/_Server', '.github', 'bin', 'git-hooks'])
    ->in(__DIR__);
$config = new Config();

return $config->setFinder($finder)
    ->setRules([
        '@PER' => true,
        'strict_param' => true,
        'single_class_element_per_statement' => false,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
