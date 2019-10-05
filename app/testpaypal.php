<?php

// 1. Autoload the SDK Package. This will include all the files and classes to your autoloader
// require __DIR__  . '/../vendor/autoload.php';
include('vendor/autoload.php');
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;

// 2. Provide your Secret Key. Replace the given one with your app clientId, and Secret
// https://developer.paypal.com/webapps/developer/applications/myapps
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        'AUvyf-GcLIYKxkpfIPQ5gcGzKjwlPodvQvyww5OU4iwpLUTXlcy9dZq_t91toX1XE4-PR1oH2KA2h22A',     // ClientID
        'EHIwO_yPRI_-8UY6olHCFuU2_9nh03qbFA1sGEomkYA-HAd2KgjogCBYUHMzoNPwThh1DNxS_PRNgulS'      // ClientSecret
        
    )
);

// 3. Lets try to create a Payment
// https://developer.paypal.com/docs/api/payments/#payment_create
$payer = new \PayPal\Api\Payer();
$payer->setPaymentMethod('paypal');

$item1 = new Item();
$item1->setName('Vegan drink')
    ->setCurrency('USD')
    ->setQuantity(1)
    ->setPrice(7.5);
$item2 = new Item();
$item2->setName('Vegan pizza')
    ->setCurrency('USD')
    ->setQuantity(5)
    ->setPrice(2);
$item3 = new Item();
$item3->setName('Vegan cake')
    ->setCurrency('USD')
    ->setQuantity(10)
    ->setPrice(1);


$itemList = new ItemList();
$itemList->setItems(array($item1, $item2, $item3));

$details = new Details();
$details->setShipping(1.2)
    ->setTax(1.3)
    ->setSubtotal(27.50);

$amount = new \PayPal\Api\Amount();
$amount->setCurrency("USD")
    ->setTotal(30)
    ->setDetails($details);



$transaction = new \PayPal\Api\Transaction();
$transaction->setAmount($amount);
$transaction->setItemList($itemList);


$redirectUrls = new \PayPal\Api\RedirectUrls();
$redirectUrls->setReturnUrl("http://f5268753.ngrok.io/payment/website/PaypalExec")
    ->setCancelUrl("https://example.com/your_cancel_url.html");

$payment = new \PayPal\Api\Payment();
$payment->setIntent('sale')
    ->setPayer($payer)
    ->setTransactions(array($transaction))
    ->setRedirectUrls($redirectUrls);


// 4. Make a Create Call and print the values
try {
    $payment->create($apiContext);
    echo $payment;

    echo "\n\nRedirect user to approval_url: " . $payment->getApprovalLink() . "\n";
}
catch (\PayPal\Exception\PayPalConnectionException $ex) {
    // This will print the detailed information on the exception.
    //REALLY HELPFUL FOR DEBUGGING
    echo $ex->getData();
}