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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Behat\Context;

use Behat\Behat\Context\Context;

final class ExampleContext implements Context
{
    /**
     * @Given I have Behat installed
     */
    public function iHaveBehatInstalled(): void
    {
        // No action needed, just to confirm Behat runs.
    }

    /**
     * @Then Behat should execute this test
     */
    public function behatShouldExecuteThisTest(): void
    {
        // No action needed, just to confirm Behat runs.
    }
}
