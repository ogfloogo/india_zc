<?php

namespace app\api\model;

use think\Model;
use app\api\controller\Shell;
use Exception;
use think\Config;
use think\Db;
use think\helper\Time;
use think\Log;
use function EasyWeChat\Kernel\Support\get_client_ip;

/**
 * 用户代收
 */
class Payment extends Model
{
    const RECHARGE_TYPR = [
        "english" => [
            0 => 'Processing', //待支付
            1 => 'Success',//支付成功
            2 => 'Fail',//支付失败
        ],
        "india" => [
            0 => 'Processing',//待支付
            1 => 'Success',//支付成功
            2 => 'Fail',//支付失败
        ]
    ];
    protected $name = 'user_recharge';
    /**
     * 代收
     */
    public function topup($post, $userinfo, $channel_info)
    {
        //代收订单号
        $order_id = $this->createorder();
        while ($this->where(['order_id' => $order_id])->find()) {
            $order_id = $this->createorder();
        }
        if($channel_info["id"] == 14){
            $order_id = "PH".$order_id;
        }
        //赠送金额
        $givemoney = $this->givemoney($post['price']);
        //事务开启
        Db::startTrans();
        try {
            $insert = [
                "user_id" => $userinfo["id"], //用户ID
                "order_id" => $order_id, //代收订单号
                "price" => $post['price'], //充值金额
                "givemoney" => $givemoney ?? 0, //赠送金额
                "channel" => $channel_info["id"], //渠道ID
                "paycode" => $channel_info["busi_code"], //支付编码
                "ip" => get_real_ip(), //提现IP地址
                "createtime" => time(),
                "updatetime" => time(),
                "agent_id" => intval($userinfo['agent_id']),
            ];
            $this->insert($insert);
            //获取支付
            $res = (new Rechargechannel())->findchannel($order_id, $post['price'], $userinfo, $channel_info);
            if (!$res) {
                Log::mylog('用户充值', $insert, 'payment');
                Db::rollback();
            }
            //提交
            Db::commit();
            return $res;
        } catch (Exception $e) {
            Log::mylog('用户充值', $e, 'payment');
            Db::rollback();
            return false;
        }
    }

    /**
     * 充值赠送金额
     */
    public function givemoney($money)
    {
        $list = explode(',', Config::get("site.pay_amount"));
        $return = [];
        foreach ($list as $key => $value) {
            $res = explode('-', $value);
            $return[$key]['price'] = $res[0];
            $return[$key]['rate'] = $res[1];
        }
        $rate = 0;
        foreach ($return as $key => $value) {
            if ($money >= $value['price']) {
                $rate = $value['rate'];
            }
        }
        $givemoney = bcmul($money, $rate / 100, 2);
        return $givemoney;
    }

    /**
     * 生成唯一订单号
     */
    public function createorder()
    {
        $msec = substr(microtime(), 2, 2);        //	毫秒
        $subtle = substr(uniqid('', true), -8);    //	微妙
        return date('YmdHis') . $msec . $subtle;  // 当前日期 + 当前时间 + 当前时间毫秒 + 当前时间微妙
    }

    /**
     * 充值记录
     */
    public function paymentlog($post, $user_id ,$language)
    {
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        if ($post['status'] == 0) { //充值状态:0=待支付,1=成功,2=失败
            $where['status'] = ['in', [0, 1, 2]];
        } else {
            $where['status'] = $post['status'];
        }
        $list = $this->where('user_id', $user_id)
            ->where($where)
            ->field('id,order_id,price,paytime,status,createtime')
            ->order('createtime desc')
            ->limit($startNum, $pageCount)
            ->select();
        foreach ($list as $key => $value) {
            $list[$key]["createtime"] = format_time($value['createtime']);
            $list[$key]["paytime"] = format_time($value['paytime']);
            $list[$key]['type'] = self::RECHARGE_TYPR[$language][$value['status']];
        }
        return $list;
    }
}
