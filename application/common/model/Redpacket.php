<?php

namespace app\common\model;

use app\api\model\Usermoneylog;
use think\Model;
use traits\model\SoftDelete;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

class Redpacket extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'red_packet';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];
    
    /**
     * 开红包
     */
    public function openredpacket($user_id,$price){
        //开启事务
        Db::startTrans();
        try {
            $this->insert([
                "user_id" => $user_id,
                "amount" => $price,
                "createtime" => time(),
                "updatetime" => time()
            ]);
            //金额变动
            $usermoneylog = (new Usermoneylog())->moneyrecords($user_id, $price, 'inc', 30, "领取奖金金额".$price);
            if(!$usermoneylog){
                Db::rollback();
                return false;
            }
            //提交
            Db::commit();
            return true;
        }catch(Exception $e){
            Log::mylog('领取失败', $e, 'bonus');
            Db::rollback();
            return false;
        }
    }

    







    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
