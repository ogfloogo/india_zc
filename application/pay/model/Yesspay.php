<?php

namespace app\pay\model;

use fast\Http;
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


class Yesspay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://api.yess-pay.com/api/payout/createOrder';
    //代收提交url(充值)
    public $pay_url = 'https://api.yess-pay.com/api/payment/createOrder';
    //代付回调(提现)
    public $notify_dai = 'https://api.alphafund.in/pay/yesspay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alphafund.in/pay/yesspay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alphafund.in/topupsuccess.html';
    //代收秘钥
    public $key = "NKmZ8ou5uq8DeXy";
    //代付秘钥
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [

            'merchantId' => $channel_info['merchantid'],
            'amount' => (int)$price*100,
            'orderId' => $order_id,
            'timestamp' => time().'000',
        ];
        $sign = $this->sendSign($param, $this->key);
        $param['phone'] = '9512345678';
        $param['notifyUrl'] = $this->notify_pay;
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "ysspay");
        $return_json = $this->curl($this->pay_url,$param);
        Log::mylog("返回参数", $return_json, "ysspay");
        $return_array = json_decode($return_json, true);
        if ($return_array['code'] == 100) {
            $payurl = !empty(urldecode($return_array['paymentUrl'])) ? urldecode($return_array['paymentUrl']) : '';
            $return_array = [
                'code' => 1,
                'payurl' => $payurl,
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
        if ($params['status'] == 1) {
            $sign = $params['sign'];
            unset($params['sign']);
            $data = [
                'amount' => $params['amount'],
                'merchantId' => $params['merchantId'],
                'orderId' => $params['orderId'],
                'timestamp' => $params['timestamp'],
            ];
            $check = $this->sendSign($data, $this->key);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'yesspayhd');
                return false;
            }
            $order_id = $params['orderId']; //商户订单号
            $order_num = $params['payOrderId']; //平台订单号
            $amount = $params['amount']/100; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'yesspayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['orderId'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['orderId'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'yesspayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {

        $param = array(
            'amount' => (int)$data['trueprice']*100,
            'merchantId' => $channel['merchantid'],
            'orderId' => $data['order_id'],
            'timestamp' => time().'000',
            'notifyUrl' => $this->notify_dai,
            'outType' => 'IMPS',
            'accountHolder' => $data['username'], //收款姓名
            'accountNumber' => $data['bankcard'], //收款账号
            'ifsc' => $data['ifsc']

        );
        $data = [
            'amount' => $param['amount'],
            'merchantId' => $param['merchantId'],
            'orderId' => $param['orderId'],
            'timestamp' => $param['timestamp'],
        ];
        $sign = $this->sendSign($data, $this->key);
        $param['sign'] = $sign;
        Log::mylog('提现提交参数', $param, 'yesspaydf');
        $return_json = $this->curl($this->dai_url,$param);
        Log::mylog($return_json, 'yesspaydf', 'yesspaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $data = [
            'amount' => $params['amount'],
            'merchantId' => $params['merchantId'],
            'orderId' => $params['orderId'],
            'timestamp' => $params['timestamp'],
        ];
        $check = $this->sendSign($data, $this->key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'yesspaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['status'] != 2) {
            try {
                $r = $usercash->where('order_id', $params['orderId'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['orderId'], 'yesspaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderId'], $e, 'yesspaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['orderId'])->find()->toArray();
                $upd = [
                    // 'order_no'  => $params['ptOrderNo'],
                    'updatetime'  => time(),
                    'status' => 3, //新增状态 '代付成功'
                    'paytime' => time(),
                ];
                if($r['status'] == 4){
                    $res = $usercash->where(['status'=>4])->where('id', $r['id'])->update($upd);
                    if (!$res) {
                        return false;
                    }
                }else{
                    $res = $usercash->where('status', 'lt', 3)->where('id', $r['id'])->update($upd);
                    if (!$res) {
                        return false;
                    }
                }

                //统计当日提现金额
                $report = new Report();
                $report->where('date', date("Y-m-d", time()))->setInc('cash', $r['price']);
                //用户提现金额
                (new Usertotal())->where('user_id', $r['user_id'])->setInc('total_withdrawals', $r['price']);
                (new Paycommon())->withdrawa($r['user_id'],$r['id']);
                Log::mylog('提现成功', $params, 'yesspaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderId'], $e, 'yesspaydfhd');
            }
        }
    }

    function sendSign($param, $salt)
    {
        $data = $param;
        ksort($data);

        $str = "";
        foreach ($data as $key => $value) {
            $str = $str . $value;
        }
        $str =md5( $str . $salt);
        return ($str);
    }

    function httpPost($url, $data)
    {

        $postData = http_build_query($data); //重要！！！
        $ch = curl_init();
        // 设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        $header = array();
        $header[] = 'User-Agent: ozilla/5.0 (X11; Linux i686) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/14.0.835.186 Safari/535.1';
        $header[] = 'Accept-Charset: UTF-8,utf-8;q=0.7,*;q=0.3';
        $header[] = 'Content-Type:application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // 对证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);    // 从证书中检查SSL加密算法是否存在
        //curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // 使用自动跳转
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);       // 自动设置Referer
        curl_setopt($ch, CURLOPT_POST, 1);      // 发送一个 常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);    // Post提交的数据包
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);      // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_HEADER, 0);        // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //获取的信息以文件流的形式返回

        $output = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Errno" . curl_error($ch);   // 捕抓异常
        }
        curl_close($ch);    // 关闭CURL
        return $output;
    }


    public function curl($url,$postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
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

    function fetch_page_json($url, $params = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:application/json;charset=UTF-8"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        if ($errno != 0) {
            throw new Exception($errmsg, $errno);
        }
        curl_close($ch);
        return $result;
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
}
