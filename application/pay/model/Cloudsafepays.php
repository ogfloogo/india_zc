<?php

namespace app\pay\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\model\Report;
use app\api\model\Usercash;
use app\api\model\Userrecharge;
use app\api\model\Usertotal;
use fast\Http;
use think\Cache;
use think\Model;
use think\Db;
use think\Log;
use think\Exception;


class Cloudsafepays extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://security.cloudnetsafe.com/snc-gate/gateway/api';
    //代收提交url(充值)
    public $pay_url = 'https://security.cloudnetsafe.com/snc-gate/gateway/api';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/cloudsafepays/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/cloudsafepays/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org';
    //私钥
    public $privateKey = "MIIEogIBAAKCAQEAoCi4BqWuF2XP2xRuOkdVf6zKwVaa9qo/G4s2+RCVg25y7ohcWjaJnwt9n/HDdm0LsWE2obQhy8XWb6b9BtsblZKNwthA/I8mHeIyhkr472JjrYDnWLncxrBLiuoheSAT7omKTvfs2LVywmJPb8vPrZdLCHlUFT1I70DHKlRbmOUCtpwxH/4rTZKeBJQI6QMdDKcAUGVS9UGhzwhkrbweWxGN1vdvD29o1T00BL2VJf/FjD+wc1sJrMGFM3CuSJtje7SmvTB+tlwBZBIMZ3aEr4ITBX8QYRn65CyJPM8ZXLxnvmckAMB8e7EuAHAd3WLQsDIpN0kfPXjCxAoAnkfXmQIDAQABAoIBAEXUOxg62IK/Ezcz2zNxsqduESDmv73YUJeTxS4muumJGHdD4PA8YxiDDAzWfiB+PuDhv84VGb8czPf1WNDKa2Z1dXWEkCEN0NKqsti2i37j6Q3W7AdVUhsW9njkyB9liwsv0KvJkOyTgCucbYIS3MOU+VQCglSYWdpm5e9gxGcOkVC6zT4YxAd1/P4Zmo78CxrHNUX03FPuYFIn/2OR4JHdVEcXG97duQIOXjf67ctS+QXG7bDKWVbK7paHn2QnRhv+6jASDzmqkgifm37xM9OzD/osuTBSfv3o+gtzIqMQd+TcoxoWExOl4oDF5FifZO6mQRRimBsiyQqO+P5ZTgECgYEA24botRAB7lD7XKZ/XstC7YJLyOczNByYYmYx9jjDAL/Jb0OaoFcN3wlI2xu4UK7lPZfnnsNl/nOF0SLtrkukNMxKYmFNPO7qpcJR2rzcGvTkuYoEzGArtbSCeGL4idXV8KzARiIRIpGVlf3DmQmL2+KAgbBKj7XrQTeAOIiFX+ECgYEAusS6L7RC0VAk5WjYJfHmJ2qDNbdWMAp3ympkx3q2rEsCHGvylCUvYsW+DVhkF/QmMV249WWPSL69ngMEbNgnvSSXmVGwoDq2r9Zqhm7yVOZT2ekGO1uL/noCC6aZUJiFpLsjGF3uScEsb4t6LROYepTvkJqeMPDVTa5eNUSPTrkCgYBaIf4RuUzRqHZMCCBrr1D/a2vqROMFFmiKniMNUSjfed8ey8cE5jlPxeQf8jWvCuAcde4nhVqvKoda4thro6r78pTn58NqrT2yaSJqiPhmKP5wH3bw4tuPc1nOS/R3w1BfzM30/a/DXbrpJpPUldLSqSDSHqu+bZb14+/FRmhcgQKBgA3kkmD4DLxbNNNn0CRKcS9fafE1+RBLxwtkjKiWBT6ducN5eCry9SpowTFm8NMjUy/648ZFTro/jgVR/iNGlPYp4akC/Zt9opdD4NqtKBOOqpAcGF2T+r7sPni1ZNQs9EwDq6GlYxNTbkXB3025Fm+P4p4kEj5bu9IydUmLFwnpAoGABD61fvT9QJiLMicuOaRQqXibC+P6M/2diwBbOZz6K9vf0tAmeJMitZrL84NlwZX2TQRxRoqbqBfndBikyU7H68pk7kcCENhVtWEOAOdTtvggNPVuwzYxPzEGtlU5hF4K/xi1I9sv/z6Q6s5qT7wPOUsvC8qvTZk83Hn2AlTLJE4=";
    public $publicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoCi4BqWuF2XP2xRuOkdVf6zKwVaa9qo/G4s2+RCVg25y7ohcWjaJnwt9n/HDdm0LsWE2obQhy8XWb6b9BtsblZKNwthA/I8mHeIyhkr472JjrYDnWLncxrBLiuoheSAT7omKTvfs2LVywmJPb8vPrZdLCHlUFT1I70DHKlRbmOUCtpwxH/4rTZKeBJQI6QMdDKcAUGVS9UGhzwhkrbweWxGN1vdvD29o1T00BL2VJf/FjD+wc1sJrMGFM3CuSJtje7SmvTB+tlwBZBIMZ3aEr4ITBX8QYRn65CyJPM8ZXLxnvmckAMB8e7EuAHAd3WLQsDIpN0kfPXjCxAoAnkfXmQIDAQAB";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'requestNo' => $order_id, //交易请求流水号
            'version' => "1.0",
            'productId' => "0105",
            'transType' => 'SALES',
            'subTransType' => "01",
            'bankCode' => 'GCASH_ONLINE',
            'agentId' => $channel_info['busi_code'],
            'merNo' => $channel_info['merchantid'],
            'orderNo' => $order_id,
            'custIp' => get_real_ip(),
            'phoneNo' => "9639639639",
            'cardHolderName' => 'alao',
            'email' => 'alao@gmail.com',
            'notifyUrl' => $this->notify_pay,
            'returnUrl' => $this->callback_url . $order_id,
            'transAmt' => number_format($price * 100, 0, '.', ''),
            'orderDate' => date("Y-m-d", time()),
            'desc' => 'alao',
            'currencyCode' => "608",
        ];
        $sign = $this->getSign($param, $this->privateKey);
        $param['signature'] = $sign;
        Log::mylog("提交参数", $param, "Cloudsafepays");
        $return_json = Http::get($this->pay_url, $param);
        Log::mylog("返回参数", $return_json, "Cloudsafepays");
        $return_array = json_decode($return_json, true);
        if ($return_array['respCode'] == "P000") {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['payUrl'])) ? urlencode($return_array['payUrl']) : '',
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
        if ($params['tradeState'] == "PAIED") {
            $sign = $params['signature'];
            unset($params['signature']);
            $check = $this->verify($params, $sign, $this->publicKey);
            if (!$check) {
                Log::mylog('验签失败', $params, 'Cloudsafepayshd');
                return false;
            }
            $order_id = $params['orderNo']; //商户订单号
            $order_num = $params['orderId']; //平台订单号
            $amount = $params['transAmt'] / 100; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Cloudsafepayshd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['orderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['orderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Cloudsafepayshd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = array(
            'requestNo' => $data['order_id'], //交易请求流水号
            'version' => "1.0",
            'productId' => "0201",
            'transType' => 'PROXY_PAY',
            'bankCode' => 'GCASH',
            'agentId' => $channel['busi_code'],
            'merNo' => $channel['merchantid'],
            'orderNo' => $data['order_id'],
            // 'bankName' => "",
            'orderDate' => date("Y-m-d", time()),
            'notifyUrl' => $this->notify_dai,
            'transAmt' => number_format($data['trueprice'] * 100, 0, '.', ''),
            'acctNo' => $data['bankcard'], //收款账号
            'phoneNo' => "9639639639",
            'email' => 'alao@gmail.com',
            'currencyCode' => "608",
            'cardHolderName' => $data['username'],
        );
        $sign = $this->getSign($param, $this->privateKey);
        $param['signature'] = $sign;
        Log::mylog("提交参数", $param, "Cloudsafepaysdf");
        $return_json = Http::get($this->dai_url, $param);
        Log::mylog($return_json, 'Cloudsafepaysdf', 'Cloudsafepaysdf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['signature'];
        unset($params['signature']);
        $check = $this->verify($params, $sign, $this->publicKey);
        if (!$check) {
            Log::mylog('验签失败', $params, 'Cloudsafepaysdfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['tradeState'] != "PAIED") {
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
                Log::mylog('代付失败,订单号:' . $params['orderNo'], 'Cloudsafepaysdfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderNo'], $e, 'Cloudsafepaysdfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['orderNo'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['orderId'],
                    'updatetime'  => time(),
                    'status' => 3, //新增状态 '代付成功'
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
                Log::mylog('提现成功', $params, 'Cloudsafepaysdfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['orderNo'], $e, 'Cloudsafepaysdfhd');
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

    //参数1：访问的URL，参数2：post数据
    public static function curl_request($url, $post = "")
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        return $data;
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
            //     Log::mylog('验签失败', $params, 'Cloudsafepayshd');
            //     return false;
            // }
            $order_id = $params['merchantOrderId']; //商户订单号
            $order_num = $params['orderId']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Cloudsafepayshd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Cloudsafepayshd');
        }
    }

    # 加签
    private static function getSign($params, $privateKey)
    {
        // $params['charset'] = "utf-8";
        ksort($params);
        $privateKeyHeader = "-----BEGIN RSA PRIVATE KEY-----\n";
        # TODO 私钥
        $privateKeyContent = $privateKey;
        $privateKeyContent = wordwrap($privateKeyContent, 64, "\n", true);
        $privateKeyEnd = "\n-----END RSA PRIVATE KEY-----";

        $privateKey = $privateKeyHeader . $privateKeyContent . $privateKeyEnd;


        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }
        Log::mylog('签名串', $keyStr, 'Cloudsafepays');
        $key = openssl_get_privatekey($privateKey);
        openssl_sign($keyStr, $signature, $key);
        openssl_free_key($key);
        $sign = base64_encode($signature);
        return $sign ? $sign : false;
    }

    //验签
    private static function verify($params, $returnSign, $publicKey)
    {
        ksort($params);
        $publicKeyHeader = "-----BEGIN PUBLIC KEY-----\n";
        # TODO 公钥
        $publicKeyContent = $publicKey;
        $publicKeyContent = wordwrap($publicKeyContent, 64, "\n", true);
        $publicKeyEnd = "\n-----END PUBLIC KEY-----";

        $publicKey = $publicKeyHeader . $publicKeyContent . $publicKeyEnd;

        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }
        $key = openssl_get_publickey($publicKey);
        $ok = openssl_verify($keyStr, base64_decode($returnSign), $key);
        openssl_free_key($key);
        return $ok;
    }

    function getUrlStr($data)
    {
        ksort($data);
        $urlStr = [];
        foreach ($data as $k => $v) {
            if (!empty($v) && $k != 'sign') {
                $urlStr[] = $k . '=' . rawurlencode($v);
            }
            if ($k == 'paymentType') {
                $urlStr[] = $k . '=' . rawurlencode($v);
            }
        }
        return join('&', $urlStr);
    }
}
