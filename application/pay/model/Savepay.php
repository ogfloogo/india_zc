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


class Savepay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'http://api.pnsafepay.com/gateway.aspx';
    //代收提交url(充值)
    public $pay_url = 'http://api.pnsafepay.com/gateway.aspx';
    //代付回调(提现)
    public $notify_dai = 'https://api.taya777.cloud/pay/savepay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.taya777.cloud/pay/savepay/paynotify';
    //代收秘钥
    public $key = "8f19552c63bb03daa2e16d1e695b439a";
    //代付秘钥
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'mer_no' => $channel_info['merchantid'],
            'order_no' => $order_id,
            'order_amount' => $price,
            'payname' => 'xiaoming',
            'payemail' => 'xiaoming@email.com',
            'payphone' => '959942552',
            "currency" => "PHP",
            'paytypecode' => $channel_info['busi_code'],
            'method' => 'trade.create',
            'returnurl' => $this->notify_pay,
        ];
        $sign = $this->sendSign($param, $this->key);
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "Savepay");
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_post($this->pay_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "Savepay");
        $return_array = json_decode($return_json, true);
        if ($return_array['status'] == "success") {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['order_data'])) ? urlencode($return_array['order_data']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'status_mes' => $return_array['msg'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['status'] == 'success') {
            $sign = $params['sign'];
            unset($params['sign']);
            $check = $this->sendSign($params, $this->key);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'Savapayhd');
                return false;
            }
            $order_id = $params['order_no']; //商户订单号
            $order_num = ''; //平台订单号
            $amount = $params['order_amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Savapayhdhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['order_no'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['order_no'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Savapayhdhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = array(
            'mer_no' => $channel['merchantid'],
            'order_no' => $data['order_id'],
            'method' => 'fund.apply',
            'order_amount' => $data['trueprice'],
            "currency" => "PHP",
            'acc_code' => 'PH_GCASH',
            'acc_name' => $data['username'], //收款姓名
            'acc_no' => $data['bankcard'], //收款账号
            'returnurl' => $this->notify_dai,
        );
        $sign = $this->sendSign($param, $this->key);
        $param['sign'] = $sign;
        Log::mylog('提现提交参数', $param, 'Savepaydf');
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_post($this->pay_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'Savepaydf', 'Savepaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = $this->sendSign($params, $this->key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'Savepaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['result'] != 'success') {
            try {
                $r = $usercash->where('order_id', $params['order_no'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['order_no'], 'Savepaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['order_no'], $e, 'Savepaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['order_no'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['sys_no'],
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
                Log::mylog('提现成功', $params, 'Savepaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['order_no'], $e, 'Savepaydfhd');
            }
        }
    }

    function sendSign($params, $appsecret)
    {
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $val) {
            if ($val != null) {
                $signStr .= $key . '=' . $val . '&';
            }
        }
        $signStr = rtrim($signStr,'&');
        $signStr .= $appsecret;
        $signStr = strtolower(md5($signStr));
        // echo $signStr;
        return $signStr;
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
