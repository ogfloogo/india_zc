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
 * 弹窗
 */
class UserPopupMessage extends Model
{

    
    public function getLastMessage($user_id = 0)
    {
        $wc['user_id'] = $user_id;
        $wc['sendtime'] = ['ELT',time()];
        $wc['status'] = 1;
        $wc['read_status'] = 0;
        $wc['deletetime'] = null;
        $info = $this->where($wc)->find();
        $res = [];
        if($info){
            $res['id'] = $info['id'];
            $res['content'] = $info['content'];
            $res['read_status'] = $info['read_status'];
        }
        return $res;
    }
    
}
