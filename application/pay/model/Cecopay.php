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


class Cecopay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://api.pay-real.com/api/pay/create_payment_order';
    //代收提交url(充值)
    public $pay_url = 'https://api.pay-real.com/api/pay/create_receivable_order';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/cecopay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/cecopay/paynotify';
    //代收秘钥
    public $key = "21E8209E66ED40E48D5A54EF51BA3C4D";
    public $merchantId = "CC_ZSTOVYD4";
    //代付秘钥
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'orderNo' => $order_id,
            'payType' => $channel_info['busi_code'],
            'amount' => $price,
            'callbackUrl' => $this->notify_pay,
            'successUrl' => "https://www.alaoph.org",
            'name' => 'jsons',
            'mobile' => "963963966",
            'email' => "alao@gmail.com"
        ];
        // $sign = $this->sendSign($param, $this->key);
        // $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "Cecopay");
        $time = $this->get_total_millisecond();
        $header[] = "timestamp:" . $time;
        $header[] = "merchant-id:" . $channel_info['merchantid'];
        $header[] = "auth:" . strtoupper(md5($channel_info['merchantid'] . "-" . $this->key . "-" . $time));
        $header[] = "Content-Type:application/json;charset=utf-8";
        Log::mylog("头部", $header, "Cecopay");
        $return_json = $this->http_Post($this->pay_url, $header, json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "Cecopay");
        $return_array = json_decode($return_json, true);
        if ($return_array['errCode'] == 0) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['data']['payLink'])) ? urlencode($return_array['data']['payLink']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['errMsg'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['paidSuccess'] == true) {
            $sign = $params['sign'];
            unset($params['sign']);
            $check = $this->sendSign($params, $this->key, $this->merchantId);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'Cecopayhd');
                return false;
            }
            $order_id = $params['orderNo']; //商户订单号
            $order_num = ""; //平台订单号
            $amount = $params['paidAmount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Cecopayhdhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['orderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['orderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Cecopayhdhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = array(
            'orderNo' => $data['order_id'],
            'amount' => $data['trueprice'],
            'payType' => $channel['busi_code'],
            'name' => $data['username'], //收款姓名
            'bankAccountNo' => $data['bankcard'], //收款账号
            'callbackUrl' => $this->notify_dai,
            'mobile' => $data['phone'],
            'email' => "alao@gmail.com"
        );
        Log::mylog('提现提交参数', $param, 'Cecopaydf');
        $time = $this->get_total_millisecond();
        $header[] = "timestamp:" . $time;
        $header[] = "merchant-id:" . $channel['merchantid'];
        $header[] = "auth:" . strtoupper(md5($channel['merchantid'] . "-" . $this->key . "-" . $time));
        $header[] = "Content-Type:application/json;charset=utf-8";
        Log::mylog("头部", $header, "Cecopaydf");        
        $return_json = $this->http_Post($this->dai_url, $header, json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'Cecopaydf', 'Cecopaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = $this->sendSign($params, $this->key, $this->merchantId);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'Cecopayhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['paidSuccess'] != true) {
            try {
                $r = $usercash->where('order_id', $params['orderNo'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['orderNo'], 'Cecopaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderNo'], $e, 'Cecopaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['orderNo'])->find()->toArray();
                $upd = [
                    // 'order_no'  => $params['platOrderId'],
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
                Log::mylog('提现成功', $params, 'Cecopaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderNo'], $e, 'Cecopaydfhd');
            }
        }
    }

    function sendSign($params, $appsecret, $merchantId)
    {
        return strtoupper(md5($merchantId . "-" . $appsecret . "-" . $params['orderNo'] . "-" . $params['timestamp']));
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

    function get_total_millisecond()

    {

        list($t1, $t2) = explode(' ', microtime());

        return sprintf('%u', (floatval($t1) + floatval($t2)) * 1000);

        // return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);

    }
}
