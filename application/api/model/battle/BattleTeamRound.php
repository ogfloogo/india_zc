<?php

namespace app\api\model\battle;

use app\admin\model\User;
use think\Model;
use think\cache\driver\Redis;
use think\Db;


class BattleTeamRound extends Model
{
    public function getCurrentInfo($team_id,$round_id){
        $wc['round_id'] = $round_id;
        $wc['team_id'] = $team_id;
        
        return $this->where($wc)->find();
    }
    public function getInfo($id){
        $info =  $this->where(['id'=>$id])->find();
        $newItem['logo_image'] = format_image($info['logo_image']);
        $newItem['name'] = $info['name'];
        $newItem['contribution'] = $info['contribution'];
        $newItem['team_people_num'] = $info['team_people_num'];
        $newItem['team_power'] = $info['team_power'];
        $newItem['head'] = $this->getHeadInfo($info['user_id']);
        return $newItem;
    }

    public function getHeadInfo($user_id){
        return (new User())->where(['id' => $user_id])->field('id,level,nickname')->find();
    }
}
