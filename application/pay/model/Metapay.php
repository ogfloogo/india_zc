<?php

namespace app\pay\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\model\Report;
use app\api\model\Usercash;
use app\api\model\Userrecharge;
use app\api\model\Usertotal;
use fast\Random;
use think\Cache;
use think\Model;
use think\Db;
use think\Log;
use think\Exception;


class Metapay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://c.metapaycm.com/openapi/payment/remit/payout';
    //代收提交url(充值)
    public $pay_url = 'https://c.metapaycm.com/openapi/payment/collect/collect';
    //代付回调(提现)
    public $notify_dai = 'https://api.taya777.cloud/pay/metapay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.taya777.cloud/pay/metapay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';
    //代收秘钥
    public $key = "1850acfc21644ba3bdf034ff5ec4f4e6";
    //代付秘钥 
    public $daikey = "XLBVCINJHGHAUSHQZ6EAZ8ABGVSUO0R4";
    private $publicKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC5C68ZS7A0xby3wozHFn6TC0841oicGsRl3xDIiBX0ghEmWm7y/elqqA6NeF/LUFaty19TDjPw5erIhgwQjFYiBSwUqPTERymzgS6jGJLB44P9h4aZQTKl5Zg4S/vEZSNa5tUJQYbWZFHSWqeFBAWQYORsMUjq/gXIPae6HUsAlQIDAQAB";
    private $privateKey = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALkLrxlLsDTFvLfCjMcWfpMLTzjWiJwaxGXfEMiIFfSCESZabvL96WqoDo14X8tQVq3LX1MOM/Dl6siGDBCMViIFLBSo9MRHKbOBLqMYksHjg/2HhplBMqXlmDhL+8RlI1rm1QlBhtZkUdJap4UEBZBg5GwxSOr+Bcg9p7odSwCVAgMBAAECgYB639VPmMDS6hLceuV8NeWqwrHCbkKcVfHgK3U7k5HwoIW+0AIofI6IcjvnmO0TVq+YDBmqTx4ScrmqmchdHLL8FRxKa6YMDiirdvc536Z8uhVPWN3PqwWujG5FrVsh8uIX3bvH9B2ajmG6X5c7DGX4ua3ZAZhg9pLQgKNH58MIaQJBAPTyqxclPEfBcLwu1TKi1bx94l/66g3omqyhVSXBK3PBlinivU+Ejgd24baundXkMgfwMPGsfkiCH9etRqsLO/cCQQDBZRl43Zeq87pvnWIQhnfljM9Kh1cAzvJTm5ykdBYmB/jD182cWUhf45VAiOTmYT9KDkyX/sRdxTgdMyT9TwzTAkEA4x+gRPXhzycuwU8roKgcR4ryPM0L+ZmU0j1GFpvnDo6SDoSPxQvEJme1Iw8Giy3sti+hMnYfIlyF6hZhUHg6QQJBAI7Fkj52aMed0x8fMww8GTtv7oB41bQVEzTCBqwpv0goTnBWWsZ360RPARp5dXLWjCCh2c3EGTeYp0p3PXF78F0CQHd1FS76pIAaXwXsm1kNYrfl7KehH9kvZwteFA38hl30q0jc/UGK4psZZq0U3ml7oqhl4Cm/s5pv29FCUm0nn90=";
    private $pt = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCuFJaHZn8RdKiJhztiuKX3dCkImsDSKJizHBWdGTvKG8AWqgKa9A5dI0G2L7betXz7il2Y/AvaizgYU2a1Zr5jhdZqmw2Jan5OnQislQUAOczqu8vTbjWAprTDvbRGpkV+WJl+r7uI3GLmboR6UgxLfwWl6XbYaAcAtsE6bYZQ7QIDAQAB";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'appId' => $channel_info['merchantid'],
            'channel' => $channel_info['busi_code'],
            'referenceNo' => $order_id,
            'amount' => $price,
            'mobile' => '09123123123',
            'userName' => Random::alpha(5),
            'address' => 'Manila',
            'remark' => 'Manila',
            'email' => 'werwe@gmail.com',
            'productType' => 'GCASH_ONLINE',
            'notificationURL' => $this->notify_pay,
        ];
        $sign = $this->generateSign($param, $this->privateKey);
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "metapay");
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_post($this->pay_url, $header, json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "metapay");
        $return_array = json_decode($return_json, true);
        if ($return_array['platRespCode'] == 0) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['url'])) ? urlencode($return_array['url']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['msg'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['status'] == 0) {
            $sign = $params['sign'];
            unset($params['sign']);
            ksort($params);
            $params_str = '';
            foreach ($params as $key => $val) {
                $params_str = $params_str . $val;
            }
            $check = $this->public_key_decrypt($sign, $this->pt);
            if ($params_str != $check) {
                Log::mylog('验签串', $params_str, 'metapaydfhd');
                Log::mylog('解密的串', $check, 'metapaydfhd');
                return false;
            }
            $order_id = $params['referenceNo']; //商户订单号
            $order_num = $params['transId']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'metapayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['referenceNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['referenceNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'metapayhd');
        }
    }

    /**
     * 代收回调
     */
    public function paynotifytest($params)
    {
        if ($params['tradeResult'] == 1) {
            //$sign = $params['sign'];
            // unset($params['sign']);
            // unset($params['signType']);
            // $check = $this->generateSign($params, $this->key);
            // if ($sign != $check) {
            //     Log::mylog('验签失败', $params, 'metapayhd');
            //     return false;
            // }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'metapayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'metapayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $mobile = $data['phone'];
        $mobile_check = substr($mobile, 0, 1);
        if ($mobile_check != 0) {
            $mobile = "0" . $mobile;
        }
        // $strarray = str_split($data['username'], 2);
        // $strarray = implode(",",$strarray);
        $str = strtoupper(Random::alpha(3)) . "," . strtoupper(Random::alpha(3)) . "," . strtoupper(Random::alpha(2));
        $params = array(
            'appId' => $channel['merchantid'],
            'pickupCenter' => "7", //渠道编码   
            'referenceNo' => $data['order_id'],
            'collectedAmount' => $data['trueprice'],
            'accountNo' => $mobile,
            'userName' => $str, //收款姓名
            'birthDate' => date('Y-m-d', time()),
            'mobileNumber' => $mobile,
            'certificateType' => "SSS",
            'certificateNo' => Random::numeric(10),
            'address' => "190 Poblacion Street",
            'city' => "HOUSTON",
            'province' => "TEXAS",
            'notificationURL' => $this->notify_dai,
            // 'bankCode' => $data['bankname'], //银行编码	 
        );
        $sign = $this->generateSign($params, $this->privateKey);
        $params['sign'] = $sign;
        Log::mylog('提现提交参数', $params, 'metapaydf');
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_post($this->dai_url, $header, json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'metapaydf', 'metapaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }
        $check = $this->public_key_decrypt($sign, $this->pt);
        if ($params_str != $check) {
            Log::mylog('验签串', $params_str, 'metapaydfhd');
            Log::mylog('解密的串', $check, 'metapaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['status'] == 2) {
            try {
                $r = $usercash->where('order_id', $params['referenceNo'])->find()->toArray();
                if ($r['status'] == 5) {
                    return false;
                }
                $upd = [
                    'status'  => 4, //新增状态 '代付失败'
                    'updatetime'  => time(),
                ];
                $res = $usercash->where('id', $r['id'])->update($upd);
                if (!$res) {
                    return false;
                }
                Log::mylog('代付失败,订单号:' . $params['referenceNo'], 'metapaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['referenceNo'], $e, 'metapaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['referenceNo'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['transId'],
                    'updatetime'  => time(),
                    'status' => 3, //新增状态 '代付成功'
                    'paytime' => time(),
                ];
                $res = $usercash->where('status', 'lt', 3)->where('id', $r['id'])->update($upd);
                if (!$res) {
                    return false;
                }
                //统计当日提现金额
                $report = new Report();
                $report->where('date', date("Y-m-d", time()))->setInc('cash', $r['price']);
                //用户提现金额
                (new Usertotal())->where('user_id', $r['user_id'])->setInc('total_withdrawals', $r['price']);
                Log::mylog('提现成功', $params, 'metapaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['referenceNo'], $e, 'metapaydfhd');
            }
        }
    }

    /**
     * 生成签名   sign = Md5(key1=vaIue1&key2=vaIue2…商户密钥);
     *  @$params 请求参数
     *  @$secretkey   密钥
     */
    public function generateSign(array $params, $mchPrivateKey)
    {
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }

        Log::mylog('验签串', $params_str, 'metapay');
        $sign = $this->pivate_key_encrypt($params_str, $mchPrivateKey);
        return $sign;
    }

    public function curl($postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->pay_url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json; charset=utf-8',
            )
        );
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    function http_post($sUrl, $aHeader, $aData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $sUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aData); // Post提交的数据包
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        //curl_setopt($ch, CURLOPT_HEADER, 1); //取得返回头信息

        $sResult = curl_exec($ch);
        if ($sError = curl_error($ch)) {
            die($sError);
        }
        curl_close($ch);
        return $sResult;
    }

    public function curls($postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://pyabxum.weglobalex.com/pay/transfer"); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    function pivate_key_encrypt($data, $pivate_key)
    {
        $pivate_key = '-----BEGIN PRIVATE KEY-----' . "\n" . $pivate_key . "\n" . '-----END PRIVATE KEY-----';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    function public_key_decrypt($data, $public_key)
    {
        $public_key = '-----BEGIN PUBLIC KEY-----' . "\n" . $public_key . "\n" . '-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key =  openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }
}
