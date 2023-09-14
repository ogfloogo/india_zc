<?php

namespace app\api\model;

use app\admin\model\activity\Turntabletimes;
use think\Model;

/**
 * 活动
 */
class Turntable extends Model
{
    protected $name = 'turntable';

    public function addtimes($user_id, $money = 0, $type)
    {
        $turntable = self::where(['starttime' => ['<=', time()], 'endtime' => ['>=', time()]])->find();
        if (!$turntable) {
            return true;
        }
        if ($type == 1) {
            //邀请
            $times = 0;
            $where['sid'] = $user_id;
            $where['createtime'] = ['between', [$turntable['starttime'],$turntable['endtime']]];
            $invite_number = db('user')->where($where)->count();
            if($invite_number > 0 && $turntable['invite'] > 0){
                $mod = $invite_number % $turntable['invite'];
                if ($mod == 0) {
                    $times = 1;
                }
            }
        } else { //2充值
            if ($money >= $turntable['recharge']) {
                $times = 1;
            } else {
                $times = 0;
            }
        }
        if ($times) {
            $exist = (new Turntabletimes())->where(['a_id' => $turntable['id'], 'user_id' => $user_id])->find();
            if (!$exist) {
                $create = [
                    'a_id' => $turntable['id'],
                    'user_id' => $user_id,
                    'times' => 1
                ];
                (new Turntabletimes())->create($create);
            } else {
                if($turntable['type'] == 2){
                    (new Turntabletimes())->where(['a_id' => $turntable['id'], 'user_id' => $user_id])->setInc('times', 1);
                }
            }
        }
    }
}
