<?php
/**
 * Remote iFrame Demo.
 *
 * This sample file demonstrates how a payment gateway might render a
 * payment form to be displayed via iFrame within WHMCS.
 *
 * In a real world scenario, this file/page would be hosted by the payment
 * gateway being implemented. On submission they would validate the input
 * and return the user to the callback file with a success confirmation.
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
// Parameters posted from remote input gateway module.
$publicKey = $_POST['public_key'] ?? '';
$secretKey = $_POST['secret_key'] ?? '';
$action = $_POST['action'] ?? '';
$customerId = $_POST['customer_id'] ?? '';
$cardToken = $_POST['card_token'] ?? '';
$invoiceId = $_POST['invoice_id'] ?? '';
$amount = $_POST['amount'] ?? 1;
$currencyCode = $_POST['currency'] ?? '';
$returnUrl = $_POST['return_url'] ?? '';
$customReference = $_POST['custom_reference'] ?? '';
$verificationHash = $_POST['verification_hash'] ?? '';

$comparisonHash = sha1(
 implode('|', [
     $publicKey,
     $customerId,
     $invoiceId,
     $amount,
     $currencyCode,
     $secretKey
 ])
);
if ($verificationHash !== $comparisonHash) {
 die('Invalid hash.');
}

if ($action === 'payment') {
    $title = 'Make a payment';
    $buttonLabel = "Pay {$amount} {$currencyCode} Now";
} else {
    $title = 'Add/Update card details';
    $buttonLabel = 'Save Changes';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title><?= $title ?></title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <script>
        const xenditParam = {
            apiKey: "<?=$publicKey?>",
            currency: "<?=$currencyCode?>",
            amount: <?=$amount?>
        };
    </script>
    <style type="text/css" media="screen">
        .validation {
            color: red;
        }
    </style>
</head>
<body>

<form method="post" id="frmUpdateCC" action="submitcc.php" style="margin:0 auto;width:80%;" autocomplete="on">
    <input type="hidden" name="action" value="<?= $action ?>">
    <input type="hidden" name="amount" value="<?= $amount ?>">
    <input type="hidden" name="currency" value="<?= $currencyCode ?>">
    <input type="hidden" name="card_token" value="<?= $cardToken ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
    <input type="hidden" name="customer_id" value="<?= $customerId ?>">
    <input type="hidden" name="return_url" value="<?= $returnUrl ?>">
    <input type="hidden" name="custom_reference" value="<?= $customReference ?>">
    <input type="hidden" name="verification_hash" value="<?= $verificationHash?>">

    <div id="newCardInfo">
        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <input type="text" name="card_description" id="inputCardDescription" class="form-control" placeholder="Card description"
                       value="<?=$_POST['card_description'] ?? ''?>"
                >
                <div class="input-group-append">
                                    <span class="input-group-text text-muted">
                                        (Optional)
                                    </span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Card Number</label>
            <input type="tel" name="card_number" id="inputCardNumber" class="form-control" required placeholder="1234 1234 1234 1234"
                   data-supported-cards="visa,mastercard,amex,jcb"
                   value="<?=$_POST['card_number'] ?? ''?>"
            >
        </div>
        <div class="row">
            <div class="form-group col-md-6 col-sm-6">
                <label>Expiry Date</label>
                <input type="text" name="card_expired" id="inputCardExpiry" class="form-control" required
                       placeholder="MM / YY"
                       value="<?=$_POST['card_expiry_date'] ?? ''?>"
                >
            </div>
            <div class="form-group col-md-6 col-sm-6">
                <label>CVV Number</label>
                <input type="password" name="card_cvv" id="inputCardCVV" class="form-control" required placeholder="CVN" >
            </div>
        </div>
    </div>
    <p class="validation"></p>
    <button type="submit" id="btnSaveCC" class="btn btn-primary">Save Changes</button>
    <button type="button" data-href="<?=$_POST['payment_method_url'] ?? ''?>" id="btnCancel" class="btn btn-secondary">Cancel</button>
</form>

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

<script type="text/javascript" src="https://js.xendit.co/v1/xendit.min.js"></script>
<script src="/assets/js/jquery.payment.js"></script>
<script src="../assets/js/xendit.js"></script>
</body>
</html>
