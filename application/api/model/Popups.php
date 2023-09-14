<?php

namespace app\api\model;

use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;

/**
 * 活动弹窗
 */
class Popups extends Model
{
    // protected $name = 'popups';

    /**
     * 活动弹窗列表
     */
    public function getList($is_login = 0)
    {

        $cache_key = "zclc:popup:set:" . $is_login;

        $redis = new Redis();
        $list = $redis->handler()->zRange($cache_key, 0, -1);
        $res = [];
        foreach ($list as $k => $id) {
            $info = $redis->handler()->hMget('zclc:popup:' . intval($id), ['name', 'is_login', 'image', 'url']);
            //  $info = $redis->handler()->hGetAll('zclc:popup:' . intval($id));
            //  unset($info['id']);
            $info['image'] = format_image($info['image']);
            $res[] = $info;
        }

        return $res;
    }
}
