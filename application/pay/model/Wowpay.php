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


class Wowpay extends Model
{
    //代付提单url(提现)
    public $dai_url = 'https://pay6de1c7.wowpayglb.com/pay/transfer';
    //代收提交url(充值)
    public $pay_url = 'https://pay6de1c7.wowpayglb.com/pay/web';
    //代付回调(提现)
    public $notify_dai = 'https://api.alphafund.in/pay/wowpay/paydainotify';
    //代收回调(充值)
    public $notify_pay = 'https://api.alphafund.in/pay/wowpay/paynotify';
    //支付成功跳转地址    
    public $callback_url = 'https://www.alphafund.in/topupsuccess.html';
    //代收秘钥
    public $key = "DCD4BRTMEZCLHFH7FFSQDJHJQU0C6XTB";
    //代付秘钥
    public $daikey = "O8YJHAMMQWKSARWAPJP1RAHVVPTVIBUD";
    public function pay($order_id, $price, $userinfo, $channel_info)
    {
        $param = [
            'version' => "1.0",
            'mch_id' => $channel_info['merchantid'],
            'notify_url' => $this->notify_pay,
            'page_url' => $this->callback_url . $order_id,
            'mch_order_no' => $order_id,
            'pay_type' => $channel_info['busi_code'],
            'trade_amount' => (int)$price,
            'order_date' => date('Y-m-d H:i:s', time()),
            'goods_name' => "goodsname",
        ];
        $sign = $this->generateSign($param, $this->key);
        $param['sign'] = $sign;
        $param['sign_type'] = "MD5";
        Log::mylog("提交参数", $param, "wowpay");
        $return_json = $this->curl($param);
        Log::mylog("返回参数", $return_json, "wowpay");
        $return_array = json_decode($return_json, true);
        if ($return_array['respCode'] == 'SUCCESS') {
            $return_array = [
                'code' => 1,
                'payurl' => !empty(($return_array['payInfo'])) ? ($return_array['payInfo']) : '',
                'type' => 1
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
                Log::mylog('验签失败', $params, 'wowpayhd');
                return false;
            }
            $order_id = $params['mchOrderNo']; //商户订单号
            $order_num = $params['orderNo']; //平台订单号
            $amount = $params['amount']; //支付金额
            (new Paycommon())->paynotify($order_id, $order_num, $amount, 'wowpayhd');
        } else {
            //更新订单信息
            $upd = [
                'status' => 2,
                'order_id' => $params['mchOrderNo'],
                'updatetime' => time(),
            ];
            (new Userrecharge())->where('order_id', $params['mchOrderNo'])->where('status', 0)->update($upd);
            Log::mylog('支付回调失败！', $params, 'wowpayhd');
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
            'transfer_amount' => (int)$data['trueprice'],
            'apply_date' => date('Y-m-d H:i:s', time()),
            // 'bank_code' => $data['bankname'], //银行编码
            'bank_code' => $channel['busi_code'], //银行编码
            'receive_account' => $data['bankcard'], //收款账号
            'receive_name' => $data['username'], //收款姓名
            'remark' => $data['ifsc'] ?? "", //urc_ifsc
            'back_url' => $this->notify_dai,
        );
        $sign = $this->generateSign($params, $this->daikey);
        $params['sign'] = $sign;
        $params['sign_type'] = "MD5";
        Log::mylog('提现提交参数', $params, 'wowpaydf');
        $return_json = $this->curls($params);
        Log::mylog($return_json, 'wowpaydf', 'wowpaydf');
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
            Log::mylog('验签失败', $params, 'wowpaydfhd');
            return false;
        }
        $usercash = new Usercash();
        if ($params['tradeResult'] != 1) {
            try {
                $r = $usercash->where('order_id', $params['merTransferId'])->find()->toArray();;
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
                Log::mylog('代付失败,订单号:' . $params, 'wowpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'wowpaydfhd');
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
                Log::mylog('提现成功', $params, 'wowpaydfhd');
            } catch (Exception $e) {
                Log::mylog('代付失败,订单号:' . $params['merTransferId'], $e, 'wowpaydfhd');
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
        Log::mylog('验签串', $params_str, 'wowpay');
        return md5($params_str);
    }

    public function curl($postdata)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://pay6de1c7.wowpayglb.com/pay/web"); //支付请求地址
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
        curl_setopt($ch, CURLOPT_URL, "https://pay6de1c7.wowpayglb.com/pay/transfer"); //支付请求地址
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
