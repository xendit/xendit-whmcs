<?php

namespace Xendit\Tests;

/**
 * WHMCS Xendit Payment Gateway Test
 *
 * Sample PHPUnit test that asserts the fundamental requirements of a WHMCS
 * module, ensuring that the required config function is defined and contains
 * the required array keys.
 *
 * This is by no means intended to be a complete test, and does not exercise any
 * of the actual functionality of the functions within the module. We strongly
 * recommend you implement further tests as appropriate for your module use
 * case.
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

use PHPUnit\Framework\TestCase;

class WHMCSModuleTest extends TestCase
{
    /** @var string $moduleName */
    protected $moduleName = 'xendit';

    /**
     * Asserts the required config options function is defined.
     */
    public function testRequiredConfigOptionsFunctionExists()
    {
        $this->assertTrue(function_exists($this->moduleName . '_config'));
    }

    /**
     * Asserts the required config option array keys are present.
     */
    public function testRequiredConfigOptionsParametersAreDefined()
    {
        $result = call_user_func($this->moduleName . '_config');
        $this->assertArrayHasKey('FriendlyName', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('xenditTestMode', $result);
        $this->assertArrayHasKey('xenditTestPublicKey', $result);
        $this->assertArrayHasKey('xenditTestSecretKey', $result);
        $this->assertArrayHasKey('xenditPublicKey', $result);
        $this->assertArrayHasKey('xenditSecretKey', $result);
        $this->assertArrayHasKey('xenditExternalPrefix', $result);
    }

    /**
     * @return array
     */
    public function totalDataProvider(): array
    {
        return [
            [
                "xenditTotal" => 1000,
                "whmcsTotal" => 999.9555555,
                "expectTotal" => 999.9555555
            ],
            [
                "xenditTotal" => 1000,
                "whmcsTotal" => 999,
                "expectTotal" => 1000
            ],
            [
                "xenditTotal" => 9999,
                "whmcsTotal" => 9999.5,
                "expectTotal" => 9999
            ],
            [
                "xenditTotal" => 9999,
                "whmcsTotal" => 9998,
                "expectTotal" => 9999
            ],
            [
                "xenditTotal" => 20000,
                "whmcsTotal" => 19999.01,
                "expectTotal" => 19999.01
            ]
        ];
    }

    /**
     * Test the round up total should have no decimal
     */
    public function testRoundUpTotalNotHasDecimal()
    {
        $actionBase = new \Xendit\Lib\ActionBase();

        foreach ($this->totalDataProvider() as $total) {
            $roundedTotal = $actionBase->roundUpTotal($total["whmcsTotal"]);
            $this->assertTrue($roundedTotal == ceil($total["whmcsTotal"]));
        }
    }

    /**
     * Test the callback paid total
     */
    public function testCallbackPaidTotal()
    {
        $actionBase = new \Xendit\Lib\ActionBase();

        foreach ($this->totalDataProvider() as $total) {
            $this->assertIsFloat($actionBase->extractPaidAmount($total["xenditTotal"], $total["whmcsTotal"]));
            $this->assertEquals(
                $total["expectTotal"],
                $actionBase->extractPaidAmount($total["xenditTotal"], $total["whmcsTotal"])
            );
        }
    }

    public function testVersionCompatibility()
    {
        $actionBase = new \Xendit\Lib\ActionBase();
        $versions = [
            "7.5" => false,
            "7.6" => false,
            "7.7.2" => false,
            "7.8.1" => false,
            "7.9" => true,
            "8.0.1" => true,
            "8.1.1" => true,
            "8.2" => true,
            "8.4" => true
        ];
        foreach ($versions as $version => $expect) {
            if ($expect) {
                $this->assertTrue($actionBase->validateCompatibilityVersion($version));
            } else {
                $this->assertFalse($actionBase->validateCompatibilityVersion($version));
            }
        }
    }
}
