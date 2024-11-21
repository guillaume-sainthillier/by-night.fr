<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$ruleset = new TwigCsFixer\Ruleset\Ruleset();
$ruleset->addStandard(new TwigCsFixer\Standard\TwigCsFixer());

$config = new TwigCsFixer\Config\Config();
$finder = new TwigCsFixer\File\Finder();
$finder->in([
    __DIR__ . '/templates',
]);
$config->setRuleset($ruleset);
$config->setCacheFile(__DIR__ . '/var/tools/.twig_cs.cache');

return $config;
