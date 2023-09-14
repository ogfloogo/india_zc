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


class Rpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://top.adkjk.in/rpay-api/payout/submit';
    //代收提交url(充值)
    public $pay_url = 'https://top.adkjk.in/rpay-api/order/submit';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/rpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/rpay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';
    //代收秘钥
    public $key = "Tr1L3R3viCYFAdbk";
    //代付秘钥
    public $daikey = "5ZKPCGI1YWUOQPOQG6RJLRZSAOAWE31P";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'merchantId' => $channel_info['merchantid'],
            'merchantOrderId' => $order_id,
            'amount' => $price,
            'timestamp' => time(),
            'payType' => $channel_info['busi_code'],
            'notifyUrl' => $this->notify_pay,
            'remark' => 'fungrouping'
        ];
        $sign = strtolower(md5("merchantId=".$param['merchantId']."&merchantOrderId=".$param['merchantOrderId']."&amount=".$param['amount']."&".$this->key));
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "rpay");
        $header[] = "Content-Type: application/json;charset=utf-8"; 
        $return_json = $this->http_post($this->pay_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "rpay");
        $return_array = json_decode($return_json, true);
        if ($return_array['code'] == 0) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['data']['h5Url'])) ? urlencode($return_array['data']['h5Url']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['error'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['status'] == 1) {
            $sign = $params['sign'];
            unset($params['sign']);
            $check = strtolower(md5("merchantId=".$params['merchantId']."&merchantOrderId=".$params['merchantOrderId']."&amount=".$params['amount']."&".$this->key));
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'rpayhd');
                return false;
            }
            $order_id = $params['merchantOrderId']; //商户订单号
            $order_num = $params['orderId']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'rpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['merchantOrderId'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['merchantOrderId'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'rpayhd');
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
            //     Log::mylog('验签失败', $params, 'rpayhd');
            //     return false;
            // }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'rpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'rpayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = [
            'merchantId' => $channel['merchantid'],
            'merchantOrderId' => $data['order_id'],
            'amount' => $data['trueprice'],
            'timestamp' => time(),
            'payType' => 1,
            'notifyUrl' => $this->notify_dai,
            'fundAccount' => [
                'accountType' => "ph",
                'contact' => [
                    'name' => $data['username'], //收款姓名
                ],
                'bankAccount' => [
                    'name' => $data['username'],
                    'ifsc' => $data['ifsc'] ?? "",
                    'accountNumber' => $data['bankcard'], //收款账号
                ],
                'ph' => [
                    'accountType' => 2,
                    'accountNumber' => $data['bankcard'], //收款账号
                    'bankCode' => "GCASH"
                ],
            ]
        ];
        $sign = strtolower(md5("merchantId=".$param['merchantId']."&merchantOrderId=".$param['merchantOrderId']."&amount=".$param['amount']."&".$this->key));
        $param['sign'] = $sign;
        Log::mylog('提现提交参数', $param, 'rpaydf');
        $header[] = "Content-Type: application/json;charset=utf-8"; 
        $return_json = $this->http_post($this->dai_url,$header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'rpaydf', 'rpaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = strtolower(md5("merchantId=".$params['merchantId']."&merchantOrderId=".$params['merchantOrderId']."&amount=".$params['amount']."&".$this->key));
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'rpaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['status'] != 1) {
            try {
                $r = $usercash->where('order_id', $params['merchantOrderId'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['merchantOrderId'], 'rpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merchantOrderId'], $e, 'rpaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['merchantOrderId'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['orderId'],
                    'updatetime'  => time(),
                    'status' => 3, //新增状态 '代付成功'
                    'paytime' => time(),
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
                Log::mylog('提现成功', $params, 'rpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merchantOrderId'], $e, 'rpaydfhd');
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
        $params_str = $params_str . 'key=' . $key;
        Log::mylog('验签串', $params_str, 'rpay');
        return md5($params_str);
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
}
