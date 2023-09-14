<?php

namespace app\api\model;
use app\api\controller\controller;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Cache;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

class Usermerchandise extends Model
{
    protected $name = 'user_merchandise';

    public function addlog($user_id,$category_id){
        $res = $this->where('user_id',$user_id)->where('category_id',$category_id)->find();
        if(!$res){
            $this->insert([
                'user_id' => $user_id,
                'category_id' => $category_id,
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
    }
}
