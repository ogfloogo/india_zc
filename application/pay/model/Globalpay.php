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


class Globalpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://sdfmq.jypayx.com/withdraw/singleOrder';
    //代收提交url(充值)
    public $pay_url = 'https://cdsop.jypayx.com/ty/orderPay';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/globalpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/globalpay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';

    public $key = 'DA09816BF55F8F93B9E41E95C605DA61';
    private $privateKey = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAMFKoTpJMeDftJuzcQea7b7EYKRnycaH1OIsmQRgohQ7cb3cg0uz8jWQeXdqdDBDApxgXdnKkMNLQR9tpcf+h+P/g9AdR9hddAJR4jaDB/511eG0Tv0pjr/yLRqJ34LcJl9yFiSjHIFrTyiScExfQ37j4twfR8CAB7OsKGLTrO8fAgMBAAECgYAAxE8HcCrmfXy18WxNX2v6CcVoYZEHXJqXmx2wSsHZ2rKE0k8jCb+4Pq58rstvNZTZEfjNWGRiofXMLh8uP/BCEYXbnzzJQrb3ZniXXMwEeNG6XZOhyIxwcNAQ9rDo1D/SQdXyZJHyeQmaOP2OFSvWBTKwb72r1SGLgn8pCRd14QJBAPzzCFnjFLqNu8W26w/zIf7C+jb7fLzYrEHsu4gb75zwrGq1lCd+QvksNOZV5rnmqISKi6RKEr2fFCM3Hgd8tLsCQQDDn2gpZEYY1HB0i/NvwOd06GJEOJAALzH2pQWlVcVOUr02cBfZznKlQzAiedUtUdFqdjZ0+ikH8dah4fm4AfrtAkEAidzP1WXdm1u6VqU+cl/6TEbqZuMkdizFndyvzRwkuW23GyXCDNBVIkdEwbFZ4kJj4HE+cJlqo2y7ZgIZXM1MFwJBAJQmiHs4fsHVOal6SA3tmRz31Vv3Pk91brBtf94cHAZaMQQmVlnUuQyh2rVxcVoWKbQn/MwQnLxwke0dt8J8dW0CQHAd+Con4DfGAvLvRrq45ziXLFjuHmepVUGqdLaLcJPLEAsZSC0LNq2yKJZYcX4p8jJkWWWVyUmtalGoAEO4K2s=";
    private $pt = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDBSqE6STHg37Sbs3EHmu2+xGCkZ8nGh9TiLJkEYKIUO3G93INLs/I1kHl3anQwQwKcYF3ZypDDS0EfbaXH/ofj/4PQHUfYXXQCUeI2gwf+ddXhtE79KY6/8i0aid+C3CZfchYkoxyBa08oknBMX0N+4+LcH0fAgAezrChi06zvHwIDAQAB";

    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'mer_no' => $channel_info['merchantid'],
            'mer_order_no' => $order_id,
            'pname' => 'zhangsan',
            'pemail' => 'zhangsan@gmail.com',
            'phone' => '13122336688',
            'order_amount' => number_format($price,2,'.',''),
            'ccy_no' => 'PHP',
            'busi_code' => $channel_info['busi_code'],
            'notifyUrl' => $this->notify_pay,
            'pageUrl' => $this->callback_url,
        ];
        $sign = $this->encrypt($param);
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "Globalpay");
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_Post($this->pay_url, $header,json_encode($param,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog("返回参数", $return_json, "Globalpay");
        $return_array = json_decode($return_json, true);
        if ($return_array['status'] == 'SUCCESS') {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(($return_array['order_data'])) ? urlencode($return_array['order_data']) : '',
            ];
        } else {
            $return_array = [
                'code' => 0,
                'msg' => $return_array['err_msg'],
            ];
        }
        return $return_array;
    }

    /**
     * 代收回调
     */
    public function paynotify($params)
    {
        if ($params['status'] == 'SUCCESS') {
            $sign = $params['sign'];
            unset($params['sign']);
            $check = $this->sendSign($params,$this->key);
            if ($check == $sign) {
                $order_id = $params['mer_order_no']; //商户订单号
                $order_num = $params['order_no']; //平台订单号
                $amount = $params['pay_amount']; //支付金额
                (new Paycommon())->paynotify($order_id, $order_num, $amount, 'Globalpayhd');
                echo 'SUCCESS';exit;
            }else{
                Log::mylog('验签失败', $sign.'---'.$check, 'Globalpayhd');
                return false;
            }
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mer_order_no'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mer_order_no'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'Globalpayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $params = array(
            'mer_no' => $channel['merchantid'],
            'mer_order_no' => $data['order_id'],
            'acc_no' => $data['bankcard'], //收款账号
            'acc_name' => $data['username'], //收款姓名
            'ccy_no' => 'PHP',
            'order_amount' => number_format($data['trueprice'],2,'.',''),
            'bank_code' => $channel['busi_code'],
            "mobile_no"=>"423524127",
            'notifyUrl' => $this->notify_dai,
            'summary' => 'summary',
        );
        $sign = $this->encrypt($params);
        $params['sign'] = $sign;
        Log::mylog('提现提交参数', $params, 'Globaldf');
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = $this->http_Post($this->dai_url, $header,json_encode($params,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        Log::mylog($return_json, 'Globaldf', 'Globaldf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = $this->sendSign($params,$this->key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'Globaldfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['status'] != 'SUCCESS') {
            try {
                $r = $usercash->where('order_id', $params['mer_order_no'])->find()->toArray();;
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
                Log::mylog('代付失败,订单号:' . $params, 'Globaldfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['mer_order_no'], $e, 'Globaldfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['mer_order_no'])->find()->toArray();
                $upd = [
                    'order_no'  => $params['order_no'],
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
                Log::mylog('提现成功', $params, 'Globaldfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['mer_order_no'], $e, 'Globaldfhd');
            }
        }
    }

    /**
     * 生成签名   sign = Md5(key1=vaIue1&key2=vaIue2…商户密钥);
     *  @$params 请求参数
     *  @$secretkey   密钥
     */
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

    function encrypt($data){
        $mch_private_key = $this->privateKey;
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if(!empty($v)){
                $str .=(string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str,'&');
        $encrypted = '';
        //替换成自己的私钥
        $pem = chunk_split($mch_private_key, 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $private_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach (str_split($str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        $encrypted = str_replace(array('+','/','='),array('-','_',''),$encrypted);

        return $encrypted;
    }

    //解密
    function decrypt($data){
        $mch_public_key = $this->pt;
        ksort($data);
        $toSign ='';
        foreach($data as $key=>$value){
            if(strcmp($key, 'sign')!= 0  && $value!=''){
                $toSign .= $key.'='.$value.'&';
            }
        }

        $str = rtrim($toSign,'&');

        $encrypted = '';
        //替换自己的公钥
        $pem = chunk_split( $mch_public_key,64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $publickey = openssl_pkey_get_public($pem);

        $base64=str_replace(array('-', '_'), array('+', '/'), $data['sign']);

        $crypto = '';
        foreach(str_split(base64_decode($base64), 128) as $chunk) {
            openssl_public_decrypt($chunk,$decrypted,$publickey);
            $crypto .= $decrypted;
        }
        if($str != $crypto){
            return false;
        }else{
            return true;
        }
    }
}
