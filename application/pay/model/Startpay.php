<?php

namespace app\pay\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\model\Report;
use app\api\model\Usercash;
use app\api\model\Userrecharge;
use app\api\model\Usertotal;
use PhpOffice\PhpSpreadsheet\Reader\Xls\MD5;
use think\Cache;
use think\Model;
use think\Db;
use think\Log;
use think\Exception;


class Startpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://withdraw.ppayglobal.com/withdraw/createOrder';
    //代收提交url(充值)
    public $pay_url = 'https://api.star-pay.vip/api/gateway/pay';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/startpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/startpay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';
    //代收秘钥
    public $key = "3469ba5305ab679104957d9e38be72ab";
    //代付秘钥
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'merchant_no' => $channel_info['merchantid'],
            'timestamp' => time(),
            'sign_type' => "MD5",
            'params' => [
                'merchant_ref' => $order_id,
                'product' => "GCash",
                'amount' => $price,
            ],
        ];
        //待验签串
        $str = $param['merchant_no'] . json_encode($param['params']) . $param['sign_type'] . $param['timestamp'] . $this->key;
        //$sign = $this->sendSign($param, $this->key);
        $param['params'] = json_encode($param['params']);
        Log::mylog("提交参数", $str, "Startpay");
        $param['sign'] = md5($str);
        Log::mylog("提交参数", $param, "Startpay");
        $return_json = $this->httpPost($this->pay_url, $param);
        Log::mylog("返回参数", $return_json, "Startpay");
        $return_array = json_decode($return_json, true);
        Log::mylog("return_array", $return_array, "Startpay");

        $params_info = json_decode($return_array['params'], true);
        Log::mylog("params_info", $params_info, "Startpay");

        if ($params_info['status'] == 0) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($params_info['payurl'])) ? urlencode($params_info['payurl']) : '',
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
        $params = json_decode($params['params'], true);
        if ($params['status'] == 1) {
            // $sign = $params['sign'];
            // unset($params['sign']);
            // unset($params['attch']);
            // $check = $this->sendSign($params, $this->key);
            // if ($sign != $check) {
            //     Log::mylog('验签失败', $params, 'Startpayhd');
            //     return false;
            // }
            $order_id = $params['merchant_ref']; //商户订单号
            $order_num = $params['system_ref']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Startpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['merchant_ref'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['merchant_ref'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Startpayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = [
            'merchant_no' => $channel['merchantid'],
            'timestamp' => time(),
            'sign_type' => "MD5",
            'params' => [
                'merchant_ref' => $data['order_id'],
                'product' => "CPayPayout",
                'amount' => $data['trueprice'],
                'extra' => [
                    'account_name' => $data['username'],
                    'account_no' => $data['bankcard'],
                    'bank_code' => 'PH_GCASH',
                ],
            ],
        ];
        //待验签串
        $str = $param['merchant_no'] . json_encode($param['params']) . $param['sign_type'] . $param['timestamp'] . $this->key;
        //$sign = $this->sendSign($param, $this->key);
        $param['params'] = json_encode($param['params']);
        Log::mylog("待验签串", $str, "Startpaydf");
        $param['sign'] = md5($str);
        Log::mylog('提现提交参数', $param, 'Startpaydf');
        $return_json = $this->httpPost($this->dai_url, $param);
        Log::mylog($return_json, 'Startpaydf', 'Startpaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        // $sign = $params['sign'];
        // unset($params['sign']);
        // $check = $this->sendSign($params, $this->key);
        // if ($sign != $check) {
        //     Log::mylog('验签失败', $params, 'Startpaydfhd');
        //     return false;
        // }
        $params = json_decode($params['params'], true);
        $usercash = new Usercash();
        if ($params['status'] != 1) {
            try {
                $r = $usercash->where('order_id', $params['merchant_ref'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['merchant_ref'], 'Startpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merchant_ref'], $e, 'Startpaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['merchant_ref'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['system_ref'],
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
                Log::mylog('提现成功', $params, 'Startpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merchant_ref'], $e, 'Startpaydfhd');
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
        $signStr .= 'key=' . $appsecret;
        // echo $signStr;
        return strtolower(md5($signStr));
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
