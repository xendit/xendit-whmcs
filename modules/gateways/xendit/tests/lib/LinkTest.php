<?php

namespace Xendit\Tests\Lib;

use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase
{
    /**
     * Test if xendit customer address object return empty if WHMCS customer address object empty
     *
     * @return void
     */
    public function testCustomerAddressObjectReturnEmpty()
    {
        // WHMCS client details
        $mockCustomerDetails = [
            'country' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'postcode' => ''
        ];

        $link = new \Xendit\Lib\Link();
        $customerAddressObject = $link->extractCustomerAddress($mockCustomerDetails);
        $this->assertIsArray($customerAddressObject, 'customerAddressObject should be array');
        $this->assertEquals([], $customerAddressObject);
    }

    /**
     * Test if xendit customer address object not empty if WHMCS customer address object has values
     *
     * @return void
     */
    public function testCustomerAddressObjectReturnValues()
    {
        // WHMCS client details
        $mockCustomerDetails = [
            'country' => 'ID',
            'address1' => 'test address1',
            'address2' => '',
            'city' => 'test city',
            'state' => 'test state',
            'postcode' => 'test postcode',
        ];

        $link = new \Xendit\Lib\Link();
        $customerAddressObject = $link->extractCustomerAddress($mockCustomerDetails);

        $this->assertIsArray($customerAddressObject, 'customerAddressObject should be array');
        $this->assertEquals(
            [
                'country' => 'ID',
                'street_line1' => 'test address1',
                'city' => 'test city',
                'province_state' => 'test state',
                'postal_code' => 'test postcode'
            ],
            $customerAddressObject
        );
    }

    /**
     * Test if xendit customer object return empty if WHMCS customer object empty
     *
     * @return void
     */
    public function testCustomerObjectReturnEmpty()
    {
        // WHMCS client details
        $mockCustomerDetails = [
            'firstname' => '',
            'lastname' => '',
            'phonenumber' => ''
        ];

        $link = new \Xendit\Lib\Link();
        $customerObject = $link->extractCustomer($mockCustomerDetails);

        $this->assertIsArray($customerObject, 'customerObject should be array');
        $this->assertEquals([], $customerObject);
    }

    /**
     * Test if xendit customer object not empty if WHMCS customer address object has values
     *
     * @return void
     */
    public function testCustomerObjectReturnValues()
    {
        // WHMCS client details
        $mockCustomerDetails = [
            'firstname' => 'test',
            'lastname' => 'test',
            'phonenumber' => '123456789'
        ];

        $link = new \Xendit\Lib\Link();
        $customerObject = $link->extractCustomer($mockCustomerDetails);

        $this->assertIsArray($customerObject, 'customerObject should be array');
        $this->assertEquals(
            [
                'given_names' => 'test test',
                'mobile_number' => '123456789'
            ],
            $customerObject
        );
    }
}
