<?php

namespace app\api\model\battle;

use app\api\model\User;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

//TODO 分表

class TeamUserContributeLog extends Model
{
    public function getList($team_id, $user_id)
    {
        $wc['team_id'] = $team_id;
        $wc['user_id'] = $user_id;
        $list =  $this->where($wc)->field('createtime,task_id,amount')->select();
        $newList = [];
        foreach($list as $item){
            $newItem['createtime'] = $item['createtime'];
            $newItem['amount'] = $item['amount'];
            //TODO taskname redis
            $newItem['task_name'] = $item['task_id'];
            $newList[] = $newItem;
        }
        return $newList;
    }

    public function getInfo($id)
    {
        $info =  $this->where(['id' => $id])->find();
        $newItem['logo_image'] = format_image($info['logo_image']);
        $newItem['name'] = $info['name'];
        $newItem['team_url'] = $info['team_url'];
        $newItem['announcement'] = $info['announcement'];
        $newItem['contribution'] = $info['contribution'];
        $newItem['team_people_num'] = $info['team_people_num'];
        $newItem['team_power'] = $info['team_power'];
        $newItem['head'] = $this->getHeadInfo($info['user_id']);
        return $newItem;
    }

    public function getHeadInfo($user_id)
    {
        $info =  (new User())->where(['id' => $user_id])->field('id,level,nickname')->find();
        return $info;
    }

    public function getUserInfo($user_id)
    {
        $info =  (new User())->where(['id' => $user_id])->field('id,avatar,level,nickname')->find();
        $info['avatar'] = format_image($info['avatar']);
        return $info;
    }
}
