<?php

namespace app\api\model;
use app\api\controller\controller;
use Exception;

use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;
use think\Log;

/**
 * 物理订单表
 */
class Logisticsorder extends Model
{
    protected $name = 'logistics_order';

    public function verifyaddress($post,$user_id){
        $order_info = (new Order())->where('id',$post['order_id'])->find();
        if(!$order_info){
            return false;
        }
        //开启事务
        Db::startTrans();
        try {
            $this->insert([
                "order_id" => $post['order_id'],
                "user_id" => $user_id,
                "wid" => $post['wid'],
                "address_id" => $post["address_id"],
                "createtime" => time(),
                "updatetime" => time(),
            ]);
            //更新仓库状态
            (new Userwarehouse())->where('id',$post['wid'])->update(['status' => 2]);
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            Log::mylog('地址确认', $e, 'verifyaddress');
            return false;
        }
        
    }
}
