# Xendit Payment Gateway Module for WHMCS #

## Summary ##	

Xendit Payment Gateway allow you to enable the multi payment channels on WHMCS

## System requirements
This module has been tested against the following tech stacks:

| Requirement            | Minimum                                                     | Recommended                                                 |
|------------------------|-------------------------------------------------------------|-------------------------------------------------------------|
| PHP Version            | 7.2                                                         | Latest 7.4 or 8.1 Release                                   |
| PHP Memory Limit       | 64MB                                                        | 128MB**                                                     |
| PHP Database Extension | PDO                                                         | PDO                                                         |
| PHP Extensions         | Curl with SSL*** , GD2 Image Library, JSON Support, XML 	  | Iconv, MBString, GMP, OpenSSL***, BC Math, Intl, Fileinfo 	 |
| MySQL Version          | 5.2.0                                                       | Latest 8.0                                                  |
| Ioncube Loaders        | 10.4.5 or later                                             | The latest 11.x Ioncube for your PHP version                |

For the latest WHMCS minimum system requirements, please refer to
https://docs.whmcs.com/System_Requirements

## Installation ##
- Clone this to your directory
- Copy `modules/gateways/xendit` to your `<root directory>/modules/gateways`
- Copy `modules/gateways/callback/xendit.php` to your `<root directory>/modules/gateways/callback`

## Configuration ##
1. Access your WHMCS admin page.
2. Go to menu Setup -> Payments -> Payment Gateways.
3. There are will be `Xendit Payment Gateway Module`
4. Then choose Setup -> Payments -> Payment Gateways -> Manage Existing Gateways
5. Put the `secretKey` and `publicKey` (Open Xendit Dashboard > Settings > API Keys > Generate Secret Key > Copy SecretKey & PublicKey)
6. Click Save Changes

## Ownership

Team: [TPI Team](https://www.draw.io/?state=%7B%22ids%22:%5B%221Vk1zqYgX2YqjJYieQ6qDPh0PhB2yAd0j%22%5D,%22action%22:%22open%22,%22userId%22:%22104938211257040552218%22%7D)

Slack Channel: [#integration-product](https://xendit.slack.com/messages/integration-product)

Slack Mentions: `@troops-tpi`
