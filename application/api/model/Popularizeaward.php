<?php

namespace app\api\model;
use app\api\controller\controller;
use think\Log;
use function EasyWeChat\Kernel\Support\get_client_ip;
use think\Model;
use think\cache\driver\Redis;
use think\Db;

/**
 * FAQ
 */
class Popularizeaward extends Model
{
    protected $name = 'popularize_award';

    public function createData($project_info,$user_id){
        $projects = (new Financeproject())->where(['f_id'=>$project_info['f_id'],'buy_level'=>['<=',$project_info['buy_level']]])->select();
        foreach ($projects as $value){
            if($value['buy_level'] == 1){
                continue;
            }
            $exist = (new Popularizeaward())->where(['f_id'=>$value['f_id'],'project_id'=>$value['id'],'user_id'=>$user_id,'buy_level'=>$value['buy_level']])->count();
            if(!$exist){
                $create2 = [
                    'f_id'=>$value['f_id'],
                    'project_id'=>$value['id'],
                    'user_id'=>$user_id,
                    'createtime' => time(),
                    'updatetime' => time(),
                    'money' => $value['fixed_amount'],
                    'num' => 0,
                    'buy_level' => $value['buy_level'],
                ];
                self::create($create2);
            }
        }
    }
    /**
     * 更新上级推广激励信息
     * @param $project_info
     * @param $pid
     * @param $user_id
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function updateData($project_info,$pid,$user_id,$order_id){
        $order = (new Financeorder())->where(['id'=>['<>',$order_id],'user_id'=>$user_id,'project_id'=>$project_info['id'],'is_robot'=>0])->find();
        //已经购买过相同方案，就不再计入推广激励
        if($order){
            Log::mylog('订单已存在', $order.'--'.$order.'--'.$pid.'--'.$user_id.'--'.$order_id, 'Popularizeaward');
            return true;
        }
        $pid_buy_level = User::where(['id'=>$pid])->value('buy_level');
        //判断上级等级称号是否大于等于方案等级称号
        if($pid_buy_level >= $project_info['buy_level']){
            //符合条件创建数据
            $create = [
                'pid' => $pid,
                'user_id' => $user_id,
                'f_id' => $project_info['f_id'],
                'project_id'=>$project_info['id'],
                'buy_level' => $project_info['buy_level'],
                'award' => round($project_info['fixed_amount']/2),
                'createtime' => time(),
                'is_award' => 0
            ];
            Popularizeuser::create($create);
//            $popularizeaward = (new Popularizeaward())->where(['f_id'=>$project_info['f_id'],'project_id'=>$project_info['id'],'user_id'=>$pid])->find();
//            if(!$popularizeaward){
//                $create2 = [
//                    'f_id'=>$project_info['f_id'],
//                    'project_id'=>$project_info['id'],
//                    'user_id'=>$pid,
//                    'createtime' => time(),
//                    'updatetime' => time(),
//                    'money' => $project_info['fixed_amount'],
//                    'num' => 1,
//                    'buy_level' => $project_info['buy_level'],
//                ];
//                self::create($create2);
//            }else{
                (new Popularizeaward())->where(['f_id'=>$project_info['f_id'],'project_id'=>$project_info['id'],'user_id'=>$pid])->setInc('num',1);
//            }
            //统计未拼成团的人数
            $count = (new Popularizeuser())->where(['pid'=>$pid,'f_id'=>$project_info['f_id'],'project_id'=>$project_info['id'],'is_award'=>0,'is_condition'=>1])->count();
            //余数为0代表拼成团
            if($count%2==0){
                (new Popularizeaward())->where(['f_id'=>$project_info['f_id'],'project_id'=>$project_info['id'],'user_id'=>$pid])->setInc('not_claimed',$project_info['fixed_amount']);
                (new Popularizeuser())->where(['pid'=>$pid,'f_id'=>$project_info['f_id'],'project_id'=>$project_info['id'],'is_condition'=>1])->update(['is_award'=>1]);
            }
        }else{
            $create = [
                'pid' => $pid,
                'user_id' => $user_id,
                'f_id' => $project_info['f_id'],
                'project_id'=>$project_info['id'],
                'buy_level' => $project_info['buy_level'],
                'award' => round($project_info['fixed_amount']/2),
                'createtime' => time(),
                'is_award' => 0,
                'is_condition' => 0
            ];
            Popularizeuser::create($create);
        }
    }
}
