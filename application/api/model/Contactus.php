<?php

namespace app\api\model;

use app\admin\model\User as ModelUser;
use think\Model;
use app\api\model\User;
use think\Log;
use think\cache\driver\Redis;
use app\api\controller\Controller as base;

/**
 * 首页联系我们
 */
class Contactus extends Model
{
    protected $name = 'contact_us';

    public function list()
    {
        $redis = new Redis();
        $keys = $redis->handler()->keys("zclc:contactus:" . "*");
        $info = [];
        foreach ($keys as $key => $value) {
            $info[] = $redis->handler()->Hgetall($value);
        }
        $return = array_column($info, 'sort');
        array_multisort($return, SORT_ASC, $info);
        foreach($info as $key=>$value){
            $info[$key]['image'] = format_image($value['image']);
        }
        return $info;
    }
}
