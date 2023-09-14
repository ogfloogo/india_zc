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
class AgentPopups extends Model
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

    public function getListFromDb($agent_id, $is_login = 0)
    {

        $wc['agent_id'] = $agent_id;
        $wc['status'] = 1;
        $res = [];
        $list = $this->where($wc)->field('name,image,url,is_login')->select();
        foreach ($list as $k => $item) {

            $item['image'] = format_image($item['image']);
            $res[] = $item;
        }

        return $res;
    }
}
