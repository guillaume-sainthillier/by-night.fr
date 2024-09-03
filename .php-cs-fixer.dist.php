<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$header = <<<'EOF'
This file is part of By Night.
(c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/migrations',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->append([
        __FILE__,
        __DIR__ . '/rector.php',
    ])
;

$config = new PhpCsFixer\Config();

return $config
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@DoctrineAnnotation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'phpdoc_summary' => false,
        'no_superfluous_phpdoc_tags' => true,
        'header_comment' => ['header' => $header],
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'native_constant_invocation' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
