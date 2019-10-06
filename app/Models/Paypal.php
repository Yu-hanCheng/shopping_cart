<?php

namespace App;

use AllInOne;
use App\Jobs\SendMailWhenPaymentPaid;
use App\Mail\PaymentReceived;
use Carbon\Carbon;
use CheckMacValue;
use EncryptType;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PaymentMethod;

class Paypal extends Model
{

    protected $fillable = ['status', 'expiry_time', 'to_be_completed_date', 'TradeNo'];
    public function initial(Order $order)
    {
        try {
            $this->ServiceURL  = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";  //服務位置
            $this->HashKey     = '5294y06JbISpM5x9';                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
            $this->HashIV      = 'v77hoKGq4kWxNNIS';                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
            $this->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
            $this->EncryptType = '1';                                                          //CheckMacValue加密類型，請固定填入1，使用SHA256加密
            //基本參數(請依系統規劃自行調整)
            $this->Send['ReturnURL']         = "http://f5268753.ngrok.io/payment/website/listenPayResult";     //付款完成通知回傳的網址
            $this->Send['OrderResultURL']    = "http://f5268753.ngrok.io/payment/website/notify";
            $this->Send['ClientBackURL']    = "http://f5268753.ngrok.io/orders";
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function pay(Order $order)
    {

        try {
            //服務參數
            $MerchantTradeNo = substr($order['no'], 10, 10) . time();

            $this->Send['MerchantTradeNo']   = $MerchantTradeNo;              //訂單編號
            $this->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
            $this->Send['TotalAmount']       = $order->total_amount;                                       //交易金額
            $this->Send['TradeDesc']         = "ecpay test";                           //交易描述
            $this->Send['ChoosePayment']     = ECPay_PaymentMethod::ALL;
            //付款方式:全功能

            $order->update([
                'payment_no'        => $MerchantTradeNo,
            ]);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        //訂單的商品資料
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
        $redirectUrls->setReturnUrl("http://e0102c5b.ngrok.io/payment/website/PaypalExec")
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
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            // This will print the detailed information on the exception.
            //REALLY HELPFUL FOR DEBUGGING
            echo $ex->getData();
        }
    }


    public static function refund($order, $paymentServiceInstance, $orderRelation)
    {
        //載入SDK(路徑可依系統規劃自行調整)
        
    }
}
