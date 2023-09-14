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


class LuckyDrawPrize extends BaseModel
{
    protected function initialize()
    {
        parent::initialize();
        $prefix_config = Config::get('activity.redis_key_prefix')['lucky_draw_prize'];
        $this->info_prefix = $prefix_config['info'];
        $this->set_prefix = $prefix_config['set'];
        $this->union_set_prefix = $prefix_config['set'] . 'union:';
    }

    public function list($id, $is_draw = 0)
    {
        $level_info_key = $this->info_prefix . $id;
        $info = $this->redisInstance->hGetAll($level_info_key);
        $list = [];
        if ($info) {
            foreach (json_decode($info['prize_infos'], true) as $prize_item) {
                $prize_id = $prize_item['prize_id'];
                $rate = $prize_item['rate'];
                if($is_draw){
                    $prize_info = $prize_item;
                }else{
                    $prize_info = (new ActivityPrize())->formatInfo($prize_id);
                }
                $list[] = $prize_info;
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

    public function formatInfo($id)
    {
        $detail_info = $this->info($id);
        $info = [];
        if ($detail_info) {
            $info['name'] = $detail_info['name'];
            $info['id'] = $detail_info['id'];
            $info['image'] = format_image($detail_info['image'], true);
            $info['price'] = $detail_info['price'];
        }
        return $info;
    }
}
