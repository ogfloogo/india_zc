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


class Uzpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://payment.dzxum.com/pay/transfer';
    //代收提交url(充值)
    public $pay_url = 'https://payment.dzxum.com/pay/web';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/uzpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/uzpay/paynotify';
    //代收秘钥
    public $key = "c244a43fe3014e2f94c5d54bdbe7823d";
    //代付秘钥
    public $dai_key = "PYJARQVHARHCJAOWJNWFNXK4SIDM11VO";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'version' => '1.0',
            'mch_id' => $channel_info['merchantid'],
            'mch_order_no' => $order_id,
            'pay_type' => $channel_info['busi_code'],
            'trade_amount' => $price,
            'order_date' => date("Y-m-d H:i:s",time()),
            'notify_url' => $this->notify_pay,
            'page_url' => "https://www.alaoph.org",
            'goods_name' => "alaoph",
        ];
        $sign = $this->sendSign($param, $this->key);
        $param['sign'] = $sign;
        $param['sign_type'] = "MD5";
        Log::mylog("提交参数", $param, "uzpay");
        $return_json = $this->curl($this->pay_url,$param);
        Log::mylog("返回参数", $return_json, "uzpay");
        $return_array = json_decode($return_json, true);
        if ($return_array['tradeResult'] == 1) {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['payInfo'])) ? urlencode($return_array['payInfo']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['tradeMsg'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['tradeResult'] == 1) {
            $sign = $params['sign'];
            unset($params['sign']);
            unset($params['signType']);
            $check = $this->sendSign($params, $this->key);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'uzpayhd');
                return false;
            }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'uzpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'uzpayhdhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $param = array(
            'mch_id' => $channel['merchantid'],
            'mch_transferId' => $data['order_id'],
            'transfer_amount' => $data['trueprice'],
            'apply_date' => date("Y-m-d H:i:s",time()),
            'bank_code' => "GCASH",
            'receive_name' => $data['username'], //收款姓名
            'receive_account' => $data['bankcard'], //收款账号
            'back_url' => $this->notify_dai,
        );
        $sign = $this->sendSign($param, $this->dai_key);
        $param['sign'] = $sign;
        $param['sign_type'] = "MD5";
        Log::mylog('提现提交参数', $param, 'uzpaydf');
        $return_json = $this->curl($this->dai_url,$param);
        Log::mylog($return_json, 'uzpaydf', 'uzpaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        unset($params['signType']);
        $check = $this->sendSign($params, $this->dai_key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'uzpayhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['tradeResult'] != 1) {
            try {
                $r = $usercash->where('order_id', $params['merTransferId'])->find()->toArray();
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
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], 'uzpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'uzpaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['merTransferId'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['tradeNo'],
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
                Log::mylog('提现成功', $params, 'uzpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'uzpaydfhd');
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

    public function curl($url,$postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //支付请求地址
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
                'Content-Type: application/x-www-form-urlencoded',
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
