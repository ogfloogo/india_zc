<?php

namespace app\pay\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\model\Report;
use app\api\model\Usercash;
use app\api\model\Userrecharge;
use app\api\model\Usertotal;
use think\Cache;
use think\Model;
use think\Db;
use think\Log;
use think\Exception;


class Shpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://openapi.shpays.com/v1/nigeria/trans/payOut';
    //代收提交url(充值)
    public $pay_url = 'https://openapi.shpays.com/v1/nigeria/trans/payIn';
    //代付回调(提现)
    public $notify_dai = 'https://api.groupfuns.com/pay/shpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.groupfuns.com/pay/shpay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.groupfuns.com/topupstatus/?orderid=';
    //代收秘钥
    public $key = "f589cde519a448aa8c1ff8c01c17375c";
    //代付秘钥
    public $daikey = "f589cde519a448aa8c1ff8c01c17375c";
    //appid
    public $appid = "6221800729401520";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'mchtId' => $channel_info['merchantid'],
            'appId' => $this->appid,
            'requestTime' => date("Y-m-d H:i:s",time()),
            'signType' => "MD5",
            'transAmt' => number_format($price,2,'.',''),
            'name' => "Asiegbu Chidiebere",
            'mobile' => "2348039386322",
            'email' => "gg743567744@gmail.com",
            'outTradeNo' => $order_id,
            'body' => "fungrouping",
            'notifyUrl' => $this->notify_pay,
            'subject' => 'fungrouping',
            'extInfo' => 'fungrouping'
        ];
        $sign = $this->generateSign($param,$this->key);
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "Shpay");
        $header[] = "Content-Type: application/json;charset=utf-8"; 
        $return_json = $this->http_post($this->pay_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "Shpay");
        $return_array = json_decode($return_json, true);
        if ($return_array['success'] == true) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['result']['link'])) ? urlencode($return_array['result']['link']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['message'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['transStatus'] == "SUCCESS") {
            $sign = $params['sign'];
            unset($params['sign']);
            $check = $this->generateSign($params,$this->key);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'Shpayhd');
                return false;
            }
            $order_id = $params['outTradeNo']; //商户订单号
            $order_num = $params['transNo']; //平台订单号
            $amount = $params['transAmt']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Shpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['outTradeNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['outTradeNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Shpayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = [
            'mchtId' => $channel['merchantid'],
            'appId' => $this->appid,
            'requestTime' => date("Y-m-d H:i:s",time()),
            'signType' => "MD5",
            // 'transAmt' => number_format($data['trueprice'],2),
            'transAmt' => number_format($data['trueprice'],2,'.',''),
            'accountName' => $data['username'],
            'accountNo' => $data['bankcard'],
            'bankCode' => $data['bankname'],
            'outTradeNo' => $data['order_id'],
            'body' => "fungrouping",
            'notifyUrl' => $this->notify_dai,
            'subject' => 'fungrouping',
            'extInfo' => 'fungrouping',
        ];
        $sign = $this->generateSign($param,$this->key);
        $param['sign'] = $sign;
        Log::mylog('提现提交参数', $param, 'Shpaydf');
        $header[] = "Content-Type: application/json;charset=utf-8"; 
        $return_json = $this->http_post($this->dai_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'Shpaydf', 'Shpaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = $this->generateSign($params,$this->key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'Shpaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['transStatus'] != "SUCCESS") {
            try {
                $r = $usercash->where('order_id', $params['outTradeNo'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['outTradeNo'], 'Shpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['outTradeNo'], $e, 'Shpaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['outTradeNo'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['transNo'],
                    'updatetime'  => time(),
                    'status' => 3, //新增状态 '代付成功'
                ];
                $res = $usercash->where('status','lt',3)->where('id', $r['id'])->update($upd);
                if (!$res) {
                    return false;
                }
                //统计当日提现金额
                $report = new Report();
                $report->where('date', date("Y-m-d", time()))->setInc('cash', $r['price']);
                //用户提现金额
                (new Usertotal())->where('user_id', $r['user_id'])->setInc('total_withdrawals', $r['price']);
                Log::mylog('提现成功', $params, 'Shpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['outTradeNo'], $e, 'Shpaydfhd');
            }
        }
    }

    /**
     * 生成签名   sign = Md5(key1=vaIue1&key2=vaIue2…商户密钥);
     *  @$params 请求参数
     *  @$secretkey   密钥
     */
    public function generateSign(array $params, $key)
    {
        ksort($params);
        $params_str = '';
        foreach ($params as $k => $v) {
            if ($v) {
                $params_str = $params_str . $k . '=' . $v . '&';
            }
        }
        $params_str = substr($params_str,0,-1).$key;
        Log::mylog('验签串', $params_str, 'Shpay');
        return strtoupper(md5($params_str));
    }

    function http_post($sUrl, $aHeader, $aData){
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
        if($sError=curl_error($ch)){
            die($sError);
        }
        curl_close($ch);
        return $sResult;
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
        )
    );
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function curls($postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://payment.weglobalpayment.com/pay/transfer"); //支付请求地址
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
            //     Log::mylog('验签失败', $params, 'ppayhd');
            //     return false;
            // }
            $order_id = $params['merchantOrderId']; //商户订单号
            $order_num = $params['orderId']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'ppayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'ppayhd');
        }
    }
}
