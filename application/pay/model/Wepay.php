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


class Wepay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://pyabxum.weglobalex.com/pay/transfer';
    //代收提交url(充值)
    public $pay_url = 'https://pyabxum.weglobalex.com/pay/web';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/wepay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/wepay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';
    //代收秘钥
    public $key = "1850acfc21644ba3bdf034ff5ec4f4e6";
    //代付秘钥
    public $daikey = "XLBVCINJHGHAUSHQZ6EAZ8ABGVSUO0R4";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'version' => "1.0",
            'mch_id' => $channel_info['merchantid'],
            'notify_url' => $this->notify_pay,
            'page_url' => $this->callback_url . $order_id,
            'mch_order_no' => $order_id,
            'pay_type' => $channel_info['busi_code'],
            'trade_amount' => $price,
            'order_date' => date('Y-m-d H:i:s', time()),
            'goods_name' => "goodsname",
        ];
        $sign = $this->generateSign($param, $channel_info['secret']);
        $param['sign'] = $sign;
        $param['sign_type'] = "MD5";
        Log::mylog("提交参数", $param, "wepay");
        $return_json = $this->curl($param);
        Log::mylog("返回参数", $return_json, "wepay");
        $return_array = json_decode($return_json, true);
        if ($return_array['respCode'] == 'SUCCESS') {
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
            $check = $this->generateSign($params, $this->key);
            if ($sign != $check) {
                Log::mylog('验签失败', $params, 'wepayhd');
                return false;
            }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'wepayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'wepayhd');
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
            //     Log::mylog('验签失败', $params, 'wepayhd');
            //     return false;
            // }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'wepayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'wepayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $params = array(
            'mch_id' => $channel['merchantid'],
            'mch_transferId' => $data['order_id'],
            'transfer_amount' => $data['trueprice'],
            'apply_date' => date('Y-m-d H:i:s', time()),
            //'bank_code' => $data['bankname'], //银行编码	 
            'bank_code' => "GCASH", //银行编码   
            'receive_account' => $data['bankcard'], //收款账号
            'receive_name' => $data['username'], //收款姓名
            'remark' => $data['ifsc'] ?? "", //urc_ifsc
            'back_url' => $this->notify_dai,
        );
        $sign = $this->generateSign($params, $this->daikey);
        $params['sign'] = $sign;
        $params['sign_type'] = "MD5";
        Log::mylog('提现提交参数', $params, 'wepaydf');
        $return_json = $this->curls($params);
        Log::mylog($return_json, 'wepaydf', 'wepaydf');
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
        $check = $this->generateSign($params, $this->daikey);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'wepaydfhd');
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
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], 'wepaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'wepaydfhd');
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
                $res = $usercash->where('status','lt',3)->where('id', $r['id'])->update($upd);
                if (!$res) {
                    return false;
                }
                //统计当日提现金额
                $report = new Report();
                $report->where('date', date("Y-m-d", time()))->setInc('cash', $r['price']);
                //用户提现金额
                (new Usertotal())->where('user_id', $r['user_id'])->setInc('total_withdrawals', $r['price']);
                Log::mylog('提现成功', $params, 'wepaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'wepaydfhd');
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
        Log::mylog('验签串', $params_str, 'wepay');
        return md5($params_str);
    }

    public function curl($postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://pyabxum.weglobalex.com/pay/web"); //支付请求地址
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
}

