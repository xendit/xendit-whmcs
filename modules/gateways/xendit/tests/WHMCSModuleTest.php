<?php
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
}
