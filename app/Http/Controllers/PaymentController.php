<?php

namespace App\Http\Controllers;
use ECPay_AllInOne;
use ECPay_PaymentMethod;
use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Requests\ApplyRefundRequest;

use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;
use PayPal\Api\Capture;
use PayPal\Api\Refund;
use PayPal\Api\RefundRequest;
use PayPal\Api\Authorization;
use PayPal\Api\Amount;

function getApiContext($clientId, $clientSecret)
        {
            // #### SDK configuration
            // Register the sdk_config.ini file in current directory
            // as the configuration source.
            /*
            if(!defined("PP_CONFIG_PATH")) {
                define("PP_CONFIG_PATH", __DIR__);
            }
            */
            // ### Api context
            // Use an ApiContext object to authenticate
            // API calls. The clientId and clientSecret for the
            // OAuthTokenCredential class can be retrieved from
            // developer.paypal.com
            $get_apiContext = new ApiContext(
                new OAuthTokenCredential(
                    $clientId,
                    $clientSecret
                )
            );
            // Comment this line out and uncomment the PP_CONFIG_PATH
            // 'define' block if you want to use static file
            // based configuration
            $get_apiContext->setConfig(
                array(
                    'mode' => 'sandbox',
                    'log.LogEnabled' => true,
                    'log.FileName' => '../PayPal.log',
                    'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                    'cache.enabled' => true,
                    //'cache.FileName' => '/PaypalCache' // for determining paypal cache directory
                    // 'http.CURLOPT_CONNECTTIMEOUT' => 30
                    // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
                    //'log.AdapterFactory' => '\PayPal\Log\DefaultLogFactory' // Factory class implementing \PayPal\Log\PayPalLogFactory
                )
            );
            // Partner Attribution Id
            // Use this header if you are a PayPal partner. Specify a unique BN Code to receive revenue attribution.
            // To learn more or to request a BN Code, contact your Partner Manager or visit the PayPal Partner Portal
            // $apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', '123123123');
            return $get_apiContext;
        }

    

class PaymentController extends Controller
{
    private $apiContext;
    private $out;

    public function __construct()
    {  
        $clientId = 'AUvyf-GcLIYKxkpfIPQ5gcGzKjwlPodvQvyww5OU4iwpLUTXlcy9dZq_t91toX1XE4-PR1oH2KA2h22A';
        $clientSecret = 'EHIwO_yPRI_-8UY6olHCFuU2_9nh03qbFA1sGEomkYA-HAd2KgjogCBYUHMzoNPwThh1DNxS_PRNgulS';
        $this->out = new \Symfony\Component\Console\Output\ConsoleOutput();
        // require __DIR__ . '/../bootstrap.php';
        $this->apiContext = getApiContext($clientId, $clientSecret);
    }
    /**
     * Simulated payment page.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function payByWebsite(Order $order)
    {
        $this->authorize('own', $order);

        // 訂單已支付或關閉
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('無法付款');
        }


        include('ECPay.Payment.Integration.php');
        try {

            $obj = new ECPay_AllInOne();

            //服務參數
            $obj->ServiceURL  = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";  //服務位置
            $obj->HashKey     = '5294y06JbISpM5x9';                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
            $obj->HashIV      = 'v77hoKGq4kWxNNIS';                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
            $obj->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
            $obj->EncryptType = '1';                                                          //CheckMacValue加密類型，請固定填入1，使用SHA256加密


            $MerchantTradeNo = substr($order['no'], 10, 10) . time();
            //基本參數(請依系統規劃自行調整)
            $obj->Send['ReturnURL']         = "http://f703e5b3.ngrok.io/payment/website/listenPayResult";     //付款完成通知回傳的網址
            $obj->Send['OrderResultURL']    = "http://f703e5b3.ngrok.io/payment/website/notify";
            $obj->Send['ClientBackURL']    = "http://f703e5b3.ngrok.io/orders";
            $obj->Send['MerchantTradeNo']   = $MerchantTradeNo;              //訂單編號
            $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
            $obj->Send['TotalAmount']       = $order->total_amount;                                       //交易金額
            $obj->Send['TradeDesc']         = "ecpay test";                           //交易描述
            $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::ALL;
            //付款方式:全功能

            $order->update([
                'payment_no'        => $MerchantTradeNo,
            ]);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        //訂單的商品資料
        foreach ($order['items'] as $a_item) {
            array_push($obj->Send['Items'], array(
                'Name'     => $a_item['product']->title,
                'Price'    => (int) $a_item->price,
                'Currency' => "元",
                'Quantity' => (int) $a_item['amount'],
                'URL'      => "dedwed"
            ));
        }

        $obj->CheckOut();
    }
    
    public function build(Order $order)
    {

        // 2. Provide your Secret Key. Replace the given one with your app clientId, and Secret
        // https://developer.paypal.com/webapps/developer/applications/myapps
        // $apiContext = new \PayPal\Rest\ApiContext(
        //     new \PayPal\Auth\OAuthTokenCredential(
        //         'AUvyf-GcLIYKxkpfIPQ5gcGzKjwlPodvQvyww5OU4iwpLUTXlcy9dZq_t91toX1XE4-PR1oH2KA2h22A',     // ClientID
        //         'EHIwO_yPRI_-8UY6olHCFuU2_9nh03qbFA1sGEomkYA-HAd2KgjogCBYUHMzoNPwThh1DNxS_PRNgulS'      // ClientSecret
        //     )
        // );

        // 3. Lets try to create a Payment
        // https://developer.paypal.com/docs/api/payments/#payment_create
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');

        $arr_itemlist = [];
        $total = 0;
        $item_setting = new Item();
        foreach ($order['items'] as $a_item) {
            $item_setting->setName($a_item['product']->title)
                ->setCurrency($order->currency_code)
                ->setQuantity((int) $a_item['amount'])
                ->setPrice((int) $a_item->price);
            $clone_item_setting = clone $item_setting;
            $total += ((int) $a_item->price) * (int) $a_item['amount'];
            array_push($arr_itemlist, $clone_item_setting);
        }

        $itemList = new ItemList();
        $itemList->setItems($arr_itemlist);

        


        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency($order->currency_code)
            ->setTotal($total);

        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList);



        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl("http://f703e5b3.ngrok.io/payment/website/PaypalExec")
            ->setCancelUrl("https://example.com/your_cancel_url.html");

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('authorize')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        

        // 4. Make a Create Call and print the values
        try {
            $payment->create($this->apiContext);
            $order->update([
                'payment_no'        => $payment->id,
            ]);

            // echo $payment;
            return redirect($payment->getApprovalLink());
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            // This will print the detailed information on the exception.
            //REALLY HELPFUL FOR DEBUGGING
            $this->out->writeln("error: ");
            echo $ex->getData();
        }
    }
    public function listenPayResult(Request $request)
    {
        if ($request->all()['RtnCode']) {
            
            $data = $request->all();
            $order = Order::where('payment_no', $data['MerchantTradeNo'])->first();
            try {
                $order->update([
                    'paid_at'        => now(),
                    'payment_method' => 'website',
                    'trade_no'       => strval($data['TradeNo']),
                ]);
            } catch (\Throwable $th) {
                $this->out->writeln("error: " . $th);
            }
        }
        return '1|OK';
    }
    public function MerchantCapture(Order $order,Request $request){

        $amount=$request->all()['amount'];
        $authorization = Authorization::get($order->authorize_id, $this->apiContext);

        $amt = new Amount();
        $amt->setCurrency($order->currency_code)
            ->setTotal($amount);

        ### Capture
        $capture = new Capture();
        $capture->setAmount($amt);

        // Perform a capture
        try {
            
            $getCapture = $authorization->capture($capture, $this->apiContext);
            $this->out->writeln("getCapture: " . $getCapture);
            $order->update([
                'capture_id' =>$getCapture->id,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            $this->out->writeln("capture error: " . $th);
        }
        
        
    }
    public function PaypalExec(Request $request)
    {
        
        $data = $request->all();
        $paymentId=$data['paymentId'];
        // require __DIR__ . '/common.php';
        $order = Order::where('payment_no', $paymentId)->first();

        $this->out->writeln("after execution");
        $payment = Payment::get($paymentId, $this->apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId($_GET['PayerID']);
        $this->out->writeln("after execution");
        try {
            
            $result = $payment->execute($execution, $this->apiContext);
            // ResultPrinter::printResult("Executed Payment", "Payment", $payment->getId(), $execution, $result);
            // $out->writeln("after pay exec: " . $result);
            $authorization_id=((($result->transactions)[0]->related_resources)[0])->authorization->id;
            $this->out->writeln("authorize_id: " . $authorization_id);
            $order->update([
                'paid_at'      => time(),
                'authorize_id' =>$authorization_id,
            ]);
        } catch (Exception $ex) {

            // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            // ResultPrinter::printError("Executed Payment", "Payment", null, null, $ex);
            $this->out->writeln("Executed Payment ");
            exit(1);
        }

        // ResultPrinter::printResult("Get Payment", "Payment", $payment->getId(), null, $payment);
        return redirect()->route('orders.show', compact('order'));
    }
    /**
     * Payment notify.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function websiteNotify(Request $request)
    {
        $data = $request->all();
        $order = Order::where('payment_no', $data['MerchantTradeNo'])->first();
        return redirect()->route('orders.show', compact('order'));
    }

    /**
     * The after paid called.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function afterPaid(Order $order)
    {
        event(new OrderPaid($order));
    }

    public function refund(Order $order, ApplyRefundRequest $request)
    {

        include('ECPay.Payment.Integration.php');

        try {

            $obj = new ECPay_AllInOne();

            $ThatTime = "20:00:00";
            if (time() >= strtotime($ThatTime)) {
                $action = 'R';
            } else {
                $action = 'N';
            }
            $the_action_arr = array(
                'MerchantTradeNo' => $order['payment_no'],
                'TradeNo' => $order['trade_no'],
                'Action' => $action,
                'TotalAmount' => $order['total_amount']
            );

            $capture = array(
                'MerchantTradeNo' => $order['payment_no'],
                'CaptureAMT' => 0,
                'UserRefundAMT' => 0,
                'PlatformID' => ''
            );

            $tradeNo = array(
                'DateType' => '',
                'BeginDate' => $order['trade_no'],
                'EndDate' => '',
                'MediaFormated' => ''
            );
            // 還要再加日期！?

            //服務參數
            $obj->ServiceURL  = "https://payment.ecpay.com.tw/CreditDetail/DoAction";  //服務位置
            $obj->HashKey     = '5294y06JbISpM5x9';                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
            $obj->HashIV      = 'v77hoKGq4kWxNNIS';                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
            $obj->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
            $obj->EncryptType = '1';                                                           //CheckMacValue加密類型，請固定填入1，使用SHA256加密
            $obj->Capture = $capture;
            $obj->TradeNo = $tradeNo;
            $obj->Action = $the_action_arr;


            //基本參數(請依系統規劃自行調整)
            $obj->Send['ReturnURL']         = "http://f703e5b3.ngrok.io/payment/website/listenPayResult";     //付款完成通知回傳的網址
            $obj->Send['OrderResultURL']    = "http://f703e5b3.ngrok.io/payment/website/notify";
            $obj->Send['ClientBackURL']    = "http://f703e5b3.ngrok.io/orders";
            $obj->Send['MerchantTradeNo']   = $order['payment_no'];              //訂單編號
            $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
            $obj->Send['TotalAmount']       = $order->total_amount;                                       //交易金額
            $obj->Send['TradeDesc']         = "ecpay test";                           //交易描述
            $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::ALL;
            //付款方式:全功能

        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $obj->DoAction();
    }

    public function refund_paypal(Order $order){
        $amount = new Amount();
        $amount->setCurrency($order->currency_code)
            ->setTotal($order->total_amount);

        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amount);
        // Replace $captureId with any static Id you might already have. 
        $captureId = $order->capture_id;
        try {
            // ### Retrieve Capture details
            $capture = Capture::get($captureId, $this->apiContext);
            // ### Refund the Capture 
            $captureRefund = $capture->refundCapturedPayment($refundRequest, $this->apiContext);
            $this->out->writeln("after captureRefund");
        } catch (Exception $ex) {
            dd($ex->getMessage());
            exit(1);
        }
        return $order;
    }
}
