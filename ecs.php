<?php

/*
 * This file is part of akawaka/sylius-soge-commerce-plugin
 *
 * AKAWAKA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets(['vendor/sylius-labs/coding-standard/ecs.php'])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Behat',
        __DIR__ . '/tests/Unit',
        __DIR__ . '/ecs.php',
    ])
    ->withSkip([
        VisibilityRequiredFixer::class => ['*Spec.php'],
    ])
    ->withRules([
        YodaStyleFixer::class,
    ])
    ->withConfiguredRule(HeaderCommentFixer::class, [
    'location' => 'after_open',
    'header' => 'This file is part of akawaka/sylius-soge-commerce-plugin

AKAWAKA

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.',
]);
