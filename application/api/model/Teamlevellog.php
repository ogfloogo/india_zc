<?php

namespace app\api\model;
use think\Model;

/**
 * FAQ
 */
class Teamlevellog extends Model
{
    protected $name = 'team_level_log';

    public function addLog($user_id,$level,$old_level){
        $create = [
            'user_id' => $user_id,
            'createtime' => time(),
            'level' => $level,
            'old_level' => $old_level,
            'status' => 0
        ];
        self::create($create);
    }
}
