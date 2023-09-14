<?php
use Swoole\Coroutine;
use think\Log;
use think\Db;

use function Swoole\Coroutine\run;

run(function () {
    Coroutine::create(function() {
        $orderid = "INDIA_155248_1661246298493";
        $param = [
           "merchantOrderNo" => $orderid,
        ];
        $str=json_encode(($param));
        $merchant_key='OjhZB58F41ECtjqV';//'merchant_key';  
        $headers = array();
        $headers[]= 'Content-Type: '. 'application/json;charset=UTF-8';
        $headers[]= 'merchant_key: '.$merchant_key;
        $aes_key='MhJevog6NyBU4F4P';//'aes_key';
        $aes_iv='7lMZq32ngJ15IFop';//'aes_iv';
        $data=base64_encode(openssl_encrypt($str, 'AES-128-CBC', (($aes_key)), OPENSSL_RAW_DATA, $aes_iv));
        $info['data']=$data;
        $data=json_encode($info);
        // var_dump($data);exit;
        // Log::mylog('查询订单'.$orderid,$param,'helppayquery');
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "http://payment.timetechnology.in/gateway/payment/orderQuery",
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        ));
        $return_json = curl_exec($curl);       
        $err = curl_error($curl);
        $rs = $return_json;
        if ($err) {
            $rs = $err;
        }
        curl_close($curl);
        var_dump($rs);
    });

    Coroutine::create(function() {
        $orderid = "INDIA_155248_1661246298493";
        $param = [
           "merchantOrderNo" => $orderid,
        ];
        $str=json_encode(($param));
        $merchant_key='OjhZB58F41ECtjqV';//'merchant_key';  
        $headers = array();
        $headers[]= 'Content-Type: '. 'application/json;charset=UTF-8';
        $headers[]= 'merchant_key: '.$merchant_key;
        $aes_key='MhJevog6NyBU4F4P';//'aes_key';
        $aes_iv='7lMZq32ngJ15IFop';//'aes_iv';
        $data=base64_encode(openssl_encrypt($str, 'AES-128-CBC', (($aes_key)), OPENSSL_RAW_DATA, $aes_iv));
        $info['data']=$data;
        $data=json_encode($info);
        // var_dump($data);exit;
        // Log::mylog('查询订单'.$orderid,$param,'helppayquery');
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "http://payment.timetechnology.in/gateway/payment/orderQuery",
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        ));
        $return_json = curl_exec($curl);       
        $err = curl_error($curl);
        $rs = $return_json;
        if ($err) {
            $rs = $err;
        }
        curl_close($curl);
        var_dump($rs);
    });
});
echo 1;//可以得到执行
