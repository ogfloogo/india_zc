<?php

namespace app\api\model;

use app\admin\model\User as ModelUser;
use think\Model;
use app\api\model\User;
use think\Log;
use think\cache\driver\Redis;
use app\api\controller\Controller as base;

/**
 * 规则协议配置
 */
class Protocol extends Model
{
    protected $name = 'protocol';

    //所有协议
    public function list($language)
    {
        $redis = new Redis();
        $keys = $redis->handler()->keys("zclc:protocol:" . "*");
        $info = [];
        foreach ($keys as $key => $value) {
            $info[] = $redis->handler()->Hgetall($value);
        }
        $list = [];
        foreach($info as $k=>$v){
            if($v['language'] == $language){
                $list[] = $v;
            }
        }
        $return = [];
        foreach($list as $ks=>$vs){
            $return[$vs['name']] = $vs['content'];
        }
        return $return;
    }
}
