<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->exclude(['tests/_Integration/_Server', '.github', 'bin', 'git-hooks'])
    ->in(__DIR__);

return (new Config())
    ->setFinder($finder)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@PER-CS' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'operator_linebreak' => ['only_booleans' => true, 'position' => 'end'],
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
