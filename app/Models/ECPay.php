<?php



namespace App\Models;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
include('../ECPay.Payment.Integration.php');

class ECPay extends Model
{
    function pay(Order $order){

        try {


            //服務參數
            $this->ServiceURL  = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";  //服務位置
            $this->HashKey     = '5294y06JbISpM5x9';                                          //測試用Hashkey，請自行帶入ECPay提供的HashKey
            $this->HashIV      = 'v77hoKGq4kWxNNIS';                                          //測試用HashIV，請自行帶入ECPay提供的HashIV
            $this->MerchantID  = '2000214';                                                    //測試用MerchantID，請自行帶入ECPay提供的MerchantID
            $this->EncryptType = '1';                                                          //CheckMacValue加密類型，請固定填入1，使用SHA256加密
            //基本參數(請依系統規劃自行調整)
            $this->Send['ReturnURL']         = "http://f5268753.ngrok.io/payment/website/listenPayResult";     //付款完成通知回傳的網址
            $this->Send['OrderResultURL']    = "http://f5268753.ngrok.io/payment/website/notify";
            $this->Send['ClientBackURL']    = "http://f5268753.ngrok.io/orders";


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
        foreach ($order['items'] as $a_item) {
            array_push($this->Send['Items'], array(
                'Name'     => $a_item['product']->title,
                'Price'    => (int) $a_item->price,
                'Currency' => "元",
                'Quantity' => (int) $a_item['amount'],
                'URL'      => "dedwed"
            ));
        }

        $this->CheckOut();
    }
    public static function refund($order, $paymentServiceInstance, $orderRelation)
    {
        //載入SDK(路徑可依系統規劃自行調整)
        
    }
    
}