<?php

namespace App\Http\Controllers;
use ECPay_AllInOne;
use ECPay_PaymentMethod;
use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Illuminate\Http\Request;

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
                $obj->Send['ReturnURL']         = "http://4af10922.ngrok.io/payment/website/listenPayResult" ;     //付款完成通知回傳的網址
                $obj->Send['OrderResultURL']    = "http://4af10922.ngrok.io/payment/website/notify";
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
            return redirect()->route('orders.show', [$order]);
    }

    public function listenPayResult(Request $request)
    {
        if ($request->all()['RtnCode']){
            $out = new \Symfony\Component\Console\Output\ConsoleOutput();
            $out->writeln("可出貨");
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
        $order->update([
            'paid_at'        => now(),
            'payment_method' => 'website',
        ]);
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
}