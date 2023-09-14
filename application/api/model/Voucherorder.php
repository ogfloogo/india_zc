<?php

namespace app\api\model;
use app\api\controller\controller;
use app\api\controller\Paydemo;
use app\api\model\recharge\Pay;
use app\pay\model\Paydemo as ModelPaydemo;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Exception;
use think\Log;
/**
 * 体验券订单
 */
class Voucherorder extends Model
{
    protected $name = 'vip_voucher_order';

    public function addorder($post,$channel,$userinfo){
        //生成唯一订单号
        $order_id = $this->createorder();
        while($this->where(['order_id'=>$order_id])->find()){
            $order_id = $this->createorder();
        }
        //开启事务 
        Db::startTrans();
        try {
            //创建代收订单
            $insert = [
                'user_id' => $userinfo['id'],
                // 'sid' => $userinfo['sid'],
                // 'amount' => $userinfo['money'],
                'price' => $post['price'],
                'order_id' => $order_id,
                'channel' => $post['channel_id'],
                'paycode' => $post['paycode'] ?? 0,
                'ip' => get_real_ip(),
                'createtime' => time(),
                'updatetime' => time(),
            ];
            $this->insert($insert);
            //代收渠道
            $payinfo = (new Rechargechannel())->findchannel($order_id,$post['price'],$userinfo,$channel);
            Db::commit();
            return $payinfo;
        } catch (Exception $e) {
            Db::rollback();
            Log::mylog('支付',$e->getMessage(),'recharge');
            return false;
        }
        
    }

    /**
     * 生成唯一订单号
     */
    public function createorder()
    {
        $msec = substr(microtime(), 2, 2);        //	毫秒
        $subtle = substr(uniqid('', true), -8);    //	微妙
        $random = mt_rand(100000, 999999);
        return date('YmdHis') . $msec . $subtle.$random;    // 当前日期 + 当前时间 + 当前时间毫秒 + 当前时间微妙
    }

}
