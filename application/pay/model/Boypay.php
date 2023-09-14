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


class Boypay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'http://www.boypay.cc/PayView/Payment/dfIndex';
    //代收提交url(充值)
    public $pay_url = 'http://www.boypay.cc/PayView/Payment/payIndex';
    //代付回调(提现)
    public $notify_dai = 'https://api.alaoph.org/pay/boypay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alaoph.org/pay/boypay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alaoph.org/topupstatus/?orderid=';

    public $key = '5c52d1f7-2b46-4dc8-9364-a77e5e0c260d';
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'merchant_no' => $channel_info['merchantid'],
            'out_trade_no' => $order_id,
            'pay_type' => 'WOWF',
            'pay_amount' => (int)$price*100,
            'notify_url' => $this->notify_pay,
        ];
        $sign = $this->generateSign($param, $this->key);
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "boypay");
        $return_json = Http::post($this->pay_url,json_encode($param));
        Log::mylog("返回参数", $return_json, "boypay");
        $return_array = json_decode($return_json, true);
        if ($return_array['code'] == '200') {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(urlencode($return_array['data'])) ? urlencode($return_array['data']) : '',
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
            $check = $this->generateSign($params, $this->key);
            if ($sign == $check) {
                $order_id = $params['out_trade_no']; //商户订单号
                $order_num = ''; //平台订单号
                $amount = $params['pay_amount']/100; //支付金额
                (new Paycommon())->paynotify($order_id, $order_num, $amount, 'boypayhd');
                echo 'SUCCESS';exit;
            }else{
                Log::mylog('验签失败', $sign.'---'.$check, 'boypayhd');
                return false;
            }
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['merchant_no'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['merchant_no'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'boypayhd');
        }
    }

    /**
     *提现 
     */
    public function withdraw($data, $channel)
    {
        $params = array(
            'pay_amount' => (int)$data['trueprice']*100,
            'merchant_no' => $channel['merchantid'],
            'notify_url' => $this->notify_dai,
            'out_trade_no' => $data['order_id'],
            'pay_type' => 'WOWF',
            'wowf_bank_code' => 'GCASH',
            'wowf_receive_account' => $data['bankcard'], //收款账号
            'wowf_receive_name' => $data['username'], //收款姓名
        );
        $sign = $this->generateSign($params, $this->key);
        $params['sign'] = $sign;
        Log::mylog('提现提交参数', $params, 'boypaydf');
        $return_json = Http::post($this->dai_url,json_encode($params));
        Log::mylog($return_json, 'boypaydf', 'boypaydf');
        return $return_json;
    }

    /**
     * 提现回调
     */
    public function paydainotify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $check = $this->generateSign($params, $this->key);
        if ($sign != $check) {
            Log::mylog('验签失败', $params, 'boypaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['status'] != 1) {
            try {
                $r = $usercash->where('order_id', $params['out_trade_no'])->find()->toArray();;
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
                Log::mylog('代付失败,订单号:' . $params, 'boypaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['out_trade_no'], $e, 'boypaydfhd');
            }
        } else {
            try {
                $r = $usercash->where('order_id', $params['out_trade_no'])->find()->toArray();
                $upd = [
//                    'order_no'  => $params['tradeNo'],
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
                Log::mylog('提现成功', $params, 'boypaydfhd');
                echo 'SUCCESS';exit;
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['out_trade_no'], $e, 'boypaydfhd');
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
                $params_str = $params_str . $k . '=' . $v . '&';
        }
        $params_str = $params_str . 'key=' . $key;
        Log::mylog('验签串', $params_str, 'boypay');
        $sign = strtoupper(md5($params_str));
        Log::mylog('md5', $sign, 'boypay');
        return $sign;
    }

}
