<?php

namespace app\api\model\activity;

use app\api\model\activity\BaseModel;
use think\Model;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Time;
use think\Log;


class LuckyDraw extends BaseModel
{
    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['lucky_draw_activity'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
        $this->activity_id = $prefix_config['id'];
    }

    public function config()
    {
        $key = $this->info_prefix;
        $info = $this->redisInstance->hGetAll($key);
        return $info;
    }

    public function formatInfo()
    {
        $detail_info = $this->config();
        $info = [];
        if ($detail_info) {
            // $info['init_num'] = $detail_info['init_num'];
            // $info['invite_num'] = $detail_info['invite_num'];
            $info['daily_num_limit'] = $detail_info['daily_num_limit'];
            // $info['daily_init_num'] = $detail_info['daily_init_num'];      
            if ($detail_info['prize_moneys']) {
                $prize_moneys = explode(',', $detail_info['prize_moneys']);
                sort($prize_moneys);
                $info['prize_moneys'] = $prize_moneys;
            }
        }
        return $info;
    }

    public function list($id)
    {
        $common_set_key = $this->set_prefix . '0';
        $level_set_key = $this->set_prefix . $id;
        $set_num = $this->redisInstance->zunionstore($this->union_set_prefix . $id, [$level_set_key, $common_set_key]);
        $list = [];
        if ($set_num) {
            $level_list = $this->redisInstance->zRevRange($this->union_set_prefix . $id, 0, -1, true);
            foreach ($level_list as $a_id => $seq) {
                $item = $this->formatInfo($a_id);
                $list[] = $item;
            }
        }
        return $list;
    }

    public function info($id)
    {
        $info_key = $this->info_prefix . $id;
        $detail_info = $this->redisInstance->hGetAll($info_key);
        return $detail_info;
    }

}
