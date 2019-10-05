<?php

namespace App\Http\Controllers;
use ECPay_AllInOne;
use ECPay_PaymentMethod;
use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Requests\ApplyRefundRequest;
use ResultPrinter_by_s as ResultPrinter_by;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

class PaymentController extends Controller
{
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
                $obj->HashKey     = '5294y06JbISpM5x9' ;                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
                $obj->HashIV      = 'v77hoKGq4kWxNNIS' ;                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
                $obj->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
                $obj->EncryptType = '1';                                                          //CheckMacValue加密類型，請固定填入1，使用SHA256加密
        

                $MerchantTradeNo = substr($order['no'],10,10).time() ; 
                //基本參數(請依系統規劃自行調整)
                $obj->Send['ReturnURL']         = "http://c7d4deb2.ngrok.io/payment/website/listenPayResult" ;     //付款完成通知回傳的網址
                $obj->Send['OrderResultURL']    = "http://c7d4deb2.ngrok.io/payment/website/notify";
                $obj->Send['ClientBackURL']    ="http://c7d4deb2.ngrok.io/orders";
                $obj->Send['MerchantTradeNo']   = $MerchantTradeNo;              //訂單編號
                $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
                $obj->Send['TotalAmount']       = $order->total_amount;                                       //交易金額
                $obj->Send['TradeDesc']         = "ecpay test" ;                           //交易描述
                $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::ALL ;
                                  //付款方式:全功能

                $order->update([
                    'payment_no'        => $MerchantTradeNo,
                ]);
            }catch (Exception $e) {
                echo $e->getMessage();
            }  
            //訂單的商品資料
            foreach ($order['items'] as $a_item)
            {
                array_push($obj->Send['Items'], array('Name'     => $a_item['product']->title,
                                                        'Price'    => (int) $a_item->price,
                                                        'Currency' => "元",
                                                        'Quantity' => (int) $a_item['amount'],
                                                        'URL'      => "dedwed"));
            }
            
            $obj->CheckOut();
    }

    public function PaypalExec(Request $request)
    {
        // require __DIR__ . '/../bootstrap.php';
        $clientId = 'AUvyf-GcLIYKxkpfIPQ5gcGzKjwlPodvQvyww5OU4iwpLUTXlcy9dZq_t91toX1XE4-PR1oH2KA2h22A';
        $clientSecret = 'EHIwO_yPRI_-8UY6olHCFuU2_9nh03qbFA1sGEomkYA-HAd2KgjogCBYUHMzoNPwThh1DNxS_PRNgulS';

        $apiContext = getApiContext($clientId, $clientSecret);
        include('ResultPrinter_by.php');
        
        
        $out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $data = $request->all();
        $out->writeln("data: ".json_encode($data['paymentId']));

        $paymentId = $_GET['paymentId'];
        $payment = Payment::get($paymentId, $apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId($_GET['PayerID']);

            try {
                $result = $payment->execute($execution, $apiContext);
                ResultPrinter_by::printResult("Executed Payment", "Payment", $payment->getId(), $execution, $result);

                try {
                    $payment = Payment::get($paymentId, $apiContext);
                } catch (Exception $ex) {
                    // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                    ResultPrinter_by::printError("Get Payment", "Payment", null, null, $ex);
                    exit(1);
                }
            } catch (Exception $ex) {
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                ResultPrinter_by::printError("Executed Payment", "Payment", null, null, $ex);
                exit(1);
            }
            ResultPrinter_by::printResult("Get Payment", "Payment", $payment->getId(), null, $payment);
            
            return $payment;
    }

    public function listenPayResult(Request $request)
    {
        if ($request->all()['RtnCode']){
            $out = new \Symfony\Component\Console\Output\ConsoleOutput();
            $data = $request->all();
            $order = Order::where('payment_no', $data['MerchantTradeNo'])->first();
            try {
                $order->update([
                    'paid_at'        => now(),
                    'payment_method' => 'website',
                    'trade_no'       => strval($data['TradeNo']),
                ]);
            } catch (\Throwable $th) {
                $out->writeln("error: ".$th);
            }
        }
        return '1|OK';
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

    public function refund(Order $order,ApplyRefundRequest $request){
        
        include('ECPay.Payment.Integration.php');
        
        try {
                
            $obj = new ECPay_AllInOne();

            $ThatTime ="20:00:00";
            if (time() >= strtotime($ThatTime)) {
                $action = 'R';
            }else {
                $action = 'N';
            }
            $the_action_arr =array(
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
            $obj->HashKey     = '5294y06JbISpM5x9' ;                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
            $obj->HashIV      = 'v77hoKGq4kWxNNIS' ;                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
            $obj->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
            $obj->EncryptType = '1';                                                           //CheckMacValue加密類型，請固定填入1，使用SHA256加密
            $obj->Capture = $capture; 
            $obj->TradeNo = $tradeNo; 
            $obj->Action = $the_action_arr; 
    

            //基本參數(請依系統規劃自行調整)
            $obj->Send['ReturnURL']         = "http://c7d4deb2.ngrok.io/payment/website/listenPayResult" ;     //付款完成通知回傳的網址
            $obj->Send['OrderResultURL']    = "http://c7d4deb2.ngrok.io/payment/website/notify";
            $obj->Send['ClientBackURL']    ="http://c7d4deb2.ngrok.io/orders";
            $obj->Send['MerchantTradeNo']   = $order['payment_no'];              //訂單編號
            $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
            $obj->Send['TotalAmount']       = $order->total_amount;                                       //交易金額
            $obj->Send['TradeDesc']         = "ecpay test" ;                           //交易描述
            $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::ALL ;
                              //付款方式:全功能
            

            
            
        }catch (Exception $e) {
            echo $e->getMessage();
        }  
        $obj->DoAction();
    }
}


/**
 * Helper method for getting an APIContext for all calls
 * @param string $clientId Client ID
 * @param string $clientSecret Client Secret
 * @return PayPal\Rest\ApiContext
 */
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

    $apiContext = new ApiContext(
        new OAuthTokenCredential(
            $clientId,
            $clientSecret
        )
    );

    // Comment this line out and uncomment the PP_CONFIG_PATH
    // 'define' block if you want to use static file
    // based configuration

    $apiContext->setConfig(
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

    return $apiContext;
}