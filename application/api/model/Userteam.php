<?php

namespace app\api\model;

use think\Model;
use app\api\controller\Controller as base;
use think\cache\driver\Redis;
use think\Config;
use think\helper\Time;
use think\Log;

/**
 * 团队关系绑定
 */
class Userteam extends Model
{
    protected $name = 'user_team';

    //添加至团队表
    public function addUserTeam($id)
    {
        $array = (new User())->userSid($id);
        $insertArray = array();
        foreach ($array as $key => $value) {
            if ($value['level'] <= 2) {
                $insertArray[] = ['user_id' => $value['uid'], 'team' => $id, 'level' => $value['level'], 'createtime' => time()];
            }
        }
        $res = $this->insertAll($insertArray);
        if (!$res) return false;

        return true;
    }

    /**
     * 团队统计
     */
    public function myteamtotal($userid, $userinfo)
    {
        $returrn = [
            'total' => [
                'member' => 0,
                'commission' => 0,
                'today_commission' => 0,
                'today_not_get_commission' => 0
            ],
            'myteam' => [
                [
                    'level' => 1,
                    'member' => 0,
                    'commission' => 0,
                    'today_commission' => 0,
                    'surpass_member' => 0,
                    'surpass_money' => 0,
                    'commission_rate' => 0,
                ],
                [
                    'level' => 2,
                    'member' => 0,
                    'commission' => 0,
                    'today_commission' => 0,
                    'surpass_member' => 0,
                    'surpass_money' => 0,
                    'commission_rate' => 0,
                ],
                [
                    'level' => 3,
                    'member' => 0,
                    'commission' => 0,
                    'today_commission' => 0,
                    'surpass_member' => 0,
                    'surpass_money' => 0,
                    'commission_rate' => 0,
                ]
            ],
        ];
        $commission_rate = (new Level())->mylevel_commission_rate($userinfo['level']);
        $commission_rates = (new Level())->mylevel_commission_rates($userinfo['level']);
        $list = [];
        $membertotal = 0;
        $commissiontotal = 0;
        $today_commission_total = 0;
        for ($i = 0; $i < 3; $i++) {
            $time = Time::today();
            $commission = new Commission();
            $commission->setTableName($userid);
            $levelnumber = $i + 1;
            $list[$i]['commission'] = $first_commission = $commission->where('to_id', $userid)->where('level', 'in', $levelnumber)->sum('commission');
            $list[$i]['today_commission'] = $first_today_commission = $commission->where('to_id', $userid)->where('level', $levelnumber)->where('createtime', 'between', [$time[0], $time[1]])->sum('commission');
            $list[$i]['member'] = $first_member = $this->where('user_id', $userid)->where('level', $levelnumber)->count();
            $list[$i]['commission_rate'] = $commission_rate[$i]['fee'];
            $list[$i]['surpass_member'] = $this->alias('a')->join('user b', 'a.team=b.id')->where('a.user_id', $userid)->where('a.level', $levelnumber)->where('b.level', '>', $userinfo['level'])->count();
            $myteam_info = $this->alias('a')->join('user b', 'a.team=b.id')->where('a.user_id', $userid)->where('a.level', $levelnumber)->where('b.level', '>', $userinfo['level'])->column('team');
            $total_level1 = (new Order())->where('user_id', 'in', $myteam_info)->where('level' . $levelnumber, 0)->where('createtime', 'between', [$time[0], $time[1]])->sum('earnings');
            $commission_fee_level = "commission_fee_level_" . $levelnumber;

            //加上robot值
            // $commission1 = bcmul($total_level1, ($commission_rates[$commission_fee_level] / 100), 2);
            // $commission1 = $this->getRobotCommission($myteam_info, $levelnumber, $commission1, $commission_rates[$commission_fee_level]);
            $commission1 = $this->today_not_get_commission_new_child($userid, $userinfo['level'],$levelnumber);
            $list[$i]['surpass_money'] = $commission1;

            $membertotal += $first_member;
            $commissiontotal += $first_commission;
            $today_commission_total += $first_today_commission;
        }
        $returrn['total']['member'] = $membertotal;
        $returrn['total']['commission'] = number_format($commissiontotal, 2);
        $returrn['total']['today_commission'] = number_format($today_commission_total, 2);
        $returrn['total']['today_not_get_commission'] = $this->today_not_get_commission_new($userid, $userinfo['level']);
        $returrn['myteam'] = $list;
        return $returrn;
    }

    public function myteamlist($post, $userid)
    {
        if ($post['level'] == 0) {
            $where['a.user_id'] = $userid;
            $where['a.level'] = ['gt', 0];
            $where2['to_id'] = $userid;
        } else {
            $where['a.user_id'] = $userid;
            $where['a.level'] = $post['level'];
            $where2['to_id'] = $userid;
            $where2['level'] = $post['level'];
        }
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        $time = Time::today();
        $teamlist = $this
            ->alias('a')
            ->join('user b', 'a.team=b.id')
            ->where($where)
            ->field('a.team,b.nickname,b.avatar,a.level,b.level as userlevel,b.createtime')
            ->limit($startNum, $pageCount)
            ->select();
        $commission = new Commission();
        $commission->setTableName($userid);
        foreach ($teamlist as $key => $value) {
            $teamlist[$key]['commission'] = $commission->where($where2)->where('from_id', $value['team'])->sum('commission');
            $teamlist[$key]['today_commission'] = $commission->where($where2)->where('from_id', $value['team'])->where('createtime', 'between', [$time[0], $time[1]])->sum('commission');
            $teamlist[$key]['createtime'] = format_time($value['createtime']);
            $teamlist[$key]['avatar'] = format_image($value['avatar']);
        }
        return $teamlist;
    }

    public function myteamsizelist($post, $userid)
    {
        if ($post['level'] == 0) {
            $where['a.user_id'] = $userid;
        } else {
            $where['a.user_id'] = $userid;
            $where['a.level'] = $post['level'];
        }
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        $teamlist = $this
            ->alias('a')
            ->join('user b', 'a.team=b.id')
            ->where($where)
            ->field('a.team,a.level,b.nickname,b.avatar,b.level as userlevel,b.createtime')
            ->limit($startNum, $pageCount)
            ->select();
        foreach ($teamlist as $key => $value) {
            $teamlist[$key]['createtime'] = format_time($value['createtime']);
            $teamlist[$key]['avatar'] = format_image($value['avatar']);
        }
        return $teamlist;
    }

    public function commissionlist($post, $userid)
    {
        $time = Time::today();
        $pageCount = 10;
        $startNum = ($post['page'] - 1) * $pageCount;
        $list = $this
            ->alias('a')
            ->join('user b', 'a.team=b.id')
            ->where('user_id', $userid)
            ->where('a.level', 'gt', 0)
            ->field('a.level,b.nickname,b.avatar,b.level as userlevel,a.team')
            ->limit($startNum, $pageCount)
            ->select();
        $commission = new Commission();
        $commission->setTableName($userid);
        foreach ($list as $key => $value) {
            if ($post['status'] == 1) {
                $where['to_id'] = $userid;
                $where['from_id'] = $value['team'];
                $where['createtime'] = ['between', [$time[0], $time[1]]];
            } else {
                $where['to_id'] = $userid;
                $where['from_id'] = $value['team'];
            }
            $list[$key]['commission'] = $commission->where($where)->sum('commission');
            $list[$key]['avatar'] = format_image($value['avatar']);
        }
        return $list;
    }
    /////各等级
    public function childlevel($level, $userid)
    {
        $user = new User();
        $myteam_info = (new Userteam())->where('user_id', $userid)->where('level', $level)->column('team');
        $res = [];
        for ($i = 0; $i < 12; $i++) {
            $levels = $i + 1;
            $res[$i]['level'] = $levels;
            $res[$i]['value'] = $user->where('id', 'in', $myteam_info)->where('level', $levels)->count();
        }
        return $res;
    }

    public function childlevelsurpass($level, $userinfo)
    {
        $level_list = [];
        for ($i = 1; $i < 13; $i++) {
            if ($userinfo['level'] < $i) {
                $level_list[]['level'] = $i;
            }
        }
        $all_commission = 0;
        if ($level_list) {
            // $time = Time::today();
            $commissiontotal = 0;
            foreach ($level_list as $key => $value) {
                // for ($i = 0; $i < 3; $i++) {
                //     $levelnumber = $i + 1;
                //     $myteam_info = (new Userteam())->where('user_id', $userinfo['id'])->where("level", $levelnumber)->column('team');
                //     $level_info = (new Level())->mylevel_commission_rates($userinfo['level']);
                //     $total = (new Order())->where('user_id', 'in', $myteam_info)->where('level', $value['level'])->where('createtime', 'between', [$time[0], $time[1]])->where('level' . $levelnumber, 0)->sum('earnings');
                //     $commission_fee_level = "commission_fee_level_" . $levelnumber;
                //     // $commission = bcmul($total, ($level_info[$commission_fee_level] / 100), 2);
                //     //加上robot值
                //     $commission = bcmul($total, ($level_info[$commission_fee_level] / 100), 2);
                //     $commission = $this->getRobotCommission($myteam_info, $levelnumber, $commission, $level_info[$commission_fee_level]);

                //     $commissiontotal += $commission;
                // }
                $commission = $this->today_not_get_commission_alone_child($userinfo['id'],$value['level'],$level);
                $level_list[$key]['commission'] = $commission;
                $all_commission +=$commission;
            }
        }   
        $return = [
            'list' => $level_list,
            'total' => bcmul($all_commission,1,2),
        ];
        return $return;
    }

    protected function getRobotCommission($teamUserIds, $level, $old_commission, $commission_fee)
    {
        $robot_num = (new User())->where(['id' => ['IN', $teamUserIds], 'is_robot' => 1, 'status' => 1, 'level' => $level])->count();
        if ($robot_num) {
            $category_info = (new Goodscategory())->detail($level);
            Log::mylog('robot:', $level . '===' . $robot_num . '===' . $old_commission . '=====' . Config::get('site.daily_buy_num') . '==' . $category_info['reward'] . '===' . $commission_fee . '====' . Config::get('site.daily_buy_num') * $robot_num . '====' . bcmul($category_info['reward'], ($commission_fee / 100), 2) . '===' . bcmul(Config::get('site.daily_buy_num') * $robot_num, bcmul($category_info['reward'], ($commission_fee / 100), 2), 2), 'robot');

            $commission = bcadd($old_commission, bcmul(Config::get('site.daily_buy_num') * $robot_num, bcmul($category_info['reward'], ($commission_fee / 100), 2), 2), 2);
        } else {
            $commission = $old_commission;
        }
        return $commission;
    }

    public function childlevelsurpasss($level, $userinfo)
    {
        $level_list = [];
        for ($i = 1; $i < 13; $i++) {
            if ($userinfo['level'] < $i) {
                $level_list[]['level'] = $i;
            }
        }
        if ($level_list) {
            $time = Time::today();
            $commissiontotal = 0;
            foreach ($level_list as $key => $value) {
                $commission = $this->today_not_get_commission_new_upgrade_child($userinfo['id'],$value['level'],$level);
                $my_award = $this->Imtoget($value['level']);
                $level_list[$key]['commission'] = bcadd($commission,$my_award,2);
            }
        }
        return $level_list;
    }
    /////总
    public function childleveltotal($userid)
    {
        $user = new User();
        $where['level'] = ['in', [1, 2, 3]];
        $myteam_info = (new Userteam())->where('user_id', $userid)->where($where)->column('team');
        $res = [];
        for ($i = 0; $i < 12; $i++) {
            $level = $i + 1;
            $res[$i]['level'] = $level;
            $res[$i]['value'] = $user->where('id', 'in', $myteam_info)->where('level', $level)->count();
        }
        return $res;
    }

    public function childlevelsurpasstotal($userinfo)
    {
        $level_list = [];
        for ($i = 1; $i < 13; $i++) {
            if ($userinfo['level'] < $i) {
                $level_list[]['level'] = $i;
            }
        }
        $all_commission = 0;
        if ($level_list) {
            // $time = Time::today();
            $commissiontotal = 0;
            foreach ($level_list as $key => $value) {
                // for ($i = 0; $i < 3; $i++) {
                //     $levelnumber = $i + 1;
                //     $myteam_info = (new Userteam())->where('user_id', $userinfo['id'])->where("level", $levelnumber)->column('team');
                //     $level_info = (new Level())->mylevel_commission_rates($userinfo['level']);
                //     $total = (new Order())->where('user_id', 'in', $myteam_info)->where('level', $value['level'])->where('createtime', 'between', [$time[0], $time[1]])->where('level' . $levelnumber, 0)->sum('earnings');
                //     $commission_fee_level = "commission_fee_level_" . $levelnumber;
                //     // $commission = bcmul($total, ($level_info[$commission_fee_level] / 100), 2);
                //     //加上robot值
                //     $commission = bcmul($total, ($level_info[$commission_fee_level] / 100), 2);
                //     $commission = $this->getRobotCommission($myteam_info, $levelnumber, $commission, $level_info[$commission_fee_level]);

                //     $commissiontotal += $commission;
                // }
                $commission = $this->today_not_get_commission_alone($userinfo['id'],$value['level']);
                $level_list[$key]['commission'] = $commission;
                $all_commission +=$commission;
            }
        }
        $return = [
            'list' => $level_list,
            'total' => bcmul($all_commission,1,2),
        ];
        return $return;
    }

    public function childlevelsurpassstotal($userinfo)
    {
        $level_list = [];
        for ($i = 1; $i < 13; $i++) {
            if ($userinfo['level'] < $i) {
                $level_list[]['level'] = $i;
            }
        }
        if ($level_list) {
            $time = Time::today();
            $commissiontotal = 0;
            foreach ($level_list as $key => $value) {
                $commission = $this->today_not_get_commission_new_upgrade($userinfo['id'],$value['level']);
                $my_award = $this->Imtoget($value['level']);
                $level_list[$key]['commission'] = bcadd($commission,$my_award,2);
            }
        }
        return $level_list;
    }

    public function today_not_get_commission($userid, $mylevel)
    {
        $time = Time::today();
        $where['a.level'] = ['in', [1, 2, 3]];
        $level_info = (new Level())->mylevel_commission_rates($mylevel);
        $commission_total = 0;
        for ($i = 0; $i < 3; $i++) {
            $level = $i + 1;
            $myteam_info = $this->alias('a')->join('user b', 'a.team=b.id')->where('a.user_id', $userid)->where('a.level', $level)->where('b.level', '>', $mylevel)->column('team');
            $total_level = (new Order())->where('user_id', 'in', $myteam_info)->where('level' . $level, 0)->where('createtime', 'between', [$time[0], $time[1]])->sum('earnings');
            $commission_fee_level = "commission_fee_level_" . $level;
            // $commission = bcmul($total_level, ($level_info[$commission_fee_level] / 100), 2);

            //加上robot值
            $commission = bcmul($total_level, ($level_info[$commission_fee_level] / 100), 2);
            $commission = $this->getRobotCommission($myteam_info, $level, $commission, $level_info[$commission_fee_level]);


            $commission_total += $commission;
        }

        return number_format($commission_total, 2);
    }
    
    public function today_not_get_commission_new_upgrade($userid, $mylevel)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('b.level','elt',$mylevel)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select();    
        $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                $goodscategory = (new Goodscategory())->where('level_id',$value['level'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                //等级佣金
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        } 
        return $income;
    }

    public function today_not_get_commission_new_upgrade_child($userid, $mylevel,$level)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('b.level','elt',$mylevel)
            ->where('a.level',$level)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select();    
        $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        } 
        return $income;
    }

    public function today_not_get_commission_new($userid, $mylevel)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('b.level','gt',$mylevel)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select();    
        $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                //等级佣金
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        } 
        return $income;
    }
    
     public function today_not_get_commission_new_child($userid, $mylevel,$level)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('b.level','gt',$mylevel)
            ->where('a.level',$level)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select();    
        $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        } 
        return $income;
    }
    
    public function today_not_get_commission_alone($userid,$level)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('b.level',$level)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select();  
            $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        }
        return $income;
    }
    
    public function today_not_get_commission_alone_child($userid,$level,$lower_level)
    {
        $list = $this
            ->alias('a')
            ->join('user b','a.team=b.id')
            ->where('a.user_id',$userid)
            ->where('a.level',$lower_level)
            ->where('b.level',$level)
            ->field('b.nickname,b.avatar,b.level,a.level as levels')
            ->select(); 
             $income = 0;
        if($list){
            $redis = new Redis();
            foreach($list as $key=>$value){
                $commission_fee_level = 'commission_fee_level_'.$value['levels'];
                $levelinfo = $redis->handler()->hMget("zclc:level:" . $value['level'], [$commission_fee_level]);
                $levelid = $redis->handler()->hMget("zclc:level:" . $value['level'], ['id']);
                $goodscategory = (new Goodscategory())->where('level_id',$levelid['id'])->find();
                //团购奖励
                $commission_tg = bcmul($goodscategory['reward'],12,2);
                $commission = bcmul($commission_tg, $levelinfo[$commission_fee_level] / 100, 2);
                $income+=$commission;
            }   
            $income = bcmul($income,1,2);
        } 
        return $income;
    }
    
    public function Imtoget($level){
        $level_info = (new Level())->mylevel_commission_rates($level);
        $goodscategory = (new Goodscategory())->where('level_id',$level_info['id'])->where('deletetime',null)->find();
        //团购奖励
        $commission_tg = bcmul($goodscategory['reward'],12,2);
        return $commission_tg;
    }
}
