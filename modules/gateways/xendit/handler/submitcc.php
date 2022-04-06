<?php
/**
 * Remote iFrame Demo Submit Handler.
 *
 * This sample file demonstrates how a payment gateway might process a
 * payment form submission from an iFrame displayed within WHMCS.
 *
 * In a real world scenario, this file/page would be hosted by the payment
 * gateway being implemented. On submission they would:
 *  * Validate the input
 *  * Create a token
 *  * Process the payment (if applicable)
 *  * Redirect back to WHMCS with the newly created token
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

$publicKey = $_POST['public_key'] ?? '';
$secretKey = $_POST['secret_key'] ?? '';
$action = $_POST['action'] ?? '';
$invoiceId = $_POST['invoice_id'] ?? '';
$amount = $_POST['amount'] ?? '';
$currencyCode = $_POST['currency'] ?? '';
$customerId = $_POST['customer_id'] ?? '';
$cardType = $_POST['xendit_card_type'] ?? '';
$cardNumber = $_POST['xendit_card_number'] ?? '';
$cardExpiryMonth = $_POST['xendit_card_exp_month'] ?? '';
$cardExpiryYear = $_POST['xendit_card_exp_year'] ?? '';
$cardCvv = $_POST['xendit_card_cvn'] ?? '';
$customReference = $_POST['custom_reference'] ?? '';
$returnUrl = $_POST['return_url'] ?? '';

// Payment gateway performs input validation, creates a token, process the
// payment (if applicable).

// Redirect back to WHMCS.
$redirectUri = $returnUrl . '?' . http_build_query([
    'success' => true,
    'action' => $action,
    'invoice_id' => $invoiceId,
    'customer_id' => $customerId,
    'amount' => $amount,
    'currency' => $currencyCode,
    'transaction_id' => "",
    'card_token' => $_POST['xendit_token'] ?? '',
    'card_type' => $cardType,
    'card_last_four' => substr($cardNumber, -4, 4),
    'card_expiry_date' => $cardExpiryMonth . substr($cardExpiryYear, -2, 2),
    'custom_reference' => $customReference,
    'verification_hash' => $_POST['verification_hash'] ?? ''
]);

header('Location: ' . $redirectUri);
exit;
