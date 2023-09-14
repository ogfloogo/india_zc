<?php

namespace app\api\command;

use app\admin\model\AuthRule;
use app\admin\model\sys\TeamLevel;
use app\api\model\Level;
use app\api\model\Userteam;
use app\common\model\User;
use ReflectionClass;
use ReflectionMethod;
use think\Cache;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Loader;
use app\api\model\Commission;
use app\api\model\Order;
use app\api\model\Usermoneylog;
use app\api\model\Usertotal;
use think\Db;
use think\Log;

class Commissionprojectty extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Commissionprojectty')
            ->setDescription('体验项目-发放');
    }

    protected function execute(Input $input, Output $output)
    {
//        // 指令输出
        $output->writeln('Commissionprojectty');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9507);
        //守护进程模式
        $ws->set([
            'daemonize' => true,
            'worker_num' => 1,
            //'task_worker_num' => 4,
        ]);
        //监听WebSocket连接打开事件
        $ws->on('Open', function ($ws, $request) {
            $ws->push($request->fd, "hello, welcome\n");
        });

        //监听WebSocket消息事件
        $ws->on('Message', function ($ws, $frame) {
            echo "Message: {$frame->data}\n";
            $ws->push($frame->fd, "server: {$frame->data}");
        });
        $ws->on('WorkerStart', function ($ws, $worker_id) {
            echo "workerId:{$worker_id}\n";
            $redis = new Redis();
            $redis->handler()->select(6);
            $commission = new Commission();
            $usermoneylog = new Usermoneylog();
            $usertotal = new Usertotal();
            $order = new Order();
            $team_level = (new Level());
            \Swoole\Timer::tick(3000, function () use ($redis, $commission, $usermoneylog, $usertotal, $order, $team_level) {
                //业务start
                //开启事务 
                for ($i = 0; $i < 100; $i++) {
                    Db::startTrans();
                    try {
                        $value = $redis->handler()->lpop('commissionlistty');
                        if ($value) {
                            $list = explode("-", $value);
                            $from_id = $list[0]; //来源ID
                            $order_id = $list[1]; //订单ID
                            $commissions = $list[2]; //佣金
                            $level = $list[3]; //等级
                            $usermoneylog->moneyrecords($from_id, $commissions, 'inc', 23, "理财收益{$order_id}");
                            //上级佣金改成购买时发放
                            //上级佣金发放
//                            $first = (new Userteam())->where(['team'=>$from_id,'level'=>1])->value('user_id');
//                            if($first){
//                                $user_level = (new User())->where(['id'=>$first])->value('level');
////                                $rate1 = (new \app\api\model\Teamlevel())->where(['level'=>$user_level])->value('rate1');
////                                $rate1 = $team_level->detail($user_level)['rate1'];
//                                $rate1 = config('site.first_team');
//                                $commission->setTableName($first);
//                                $insert = [
//                                    'to_id' => $first,
//                                    'from_id' => $from_id,
//                                    'level' => 1,
//                                    'order_id' => $order_id,
//                                    'commission' => bcmul($commissions,$rate1/100,2),
//                                    'commission_fee' => $rate1,
//                                    'createtime' => time(),
//                                    'updatetime' => time(),
//                                ];
//                                //余额变动
//                                if($insert['commission'] > 0){
//                                    $commission->insert($insert);
//                                    $isok = $usermoneylog->moneyrecords($first, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
//                                }
//                                $usertotal->where('user_id', $first)->setInc('first_commission', $insert['commission']);
//                                $second = (new Userteam())->where(['team'=>$from_id,'level'=>2])->value('user_id');
//                                if($second){
//                                    $user_level = (new User())->where(['id'=>$second])->value('level');
////                                    $rate2 = (new \app\api\model\Teamlevel())->where(['level'=>$user_level])->value('rate2');
////                                    $rate2 = $team_level->detail($user_level)['rate2'];
//                                    $rate2 = config('site.second_team');
//                                    $commission->setTableName($second);
//                                    $insert = [
//                                        'to_id' => $second,
//                                        'from_id' => $from_id,
//                                        'level' => 2,
//                                        'order_id' => $order_id,
//                                        'commission' => bcmul($commissions,$rate2/100,2),
//                                        'commission_fee' => $rate2,
//                                        'createtime' => time(),
//                                        'updatetime' => time(),
//                                    ];
//                                    //余额变动
//                                    if($insert['commission'] > 0) {
//                                        $commission->insert($insert);
//                                        $isok = $usermoneylog->moneyrecords($second, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
//                                    }
//                                    $usertotal->where('user_id', $second)->setInc('second_commission', $insert['commission']);
////                                    $third = (new Userteam())->where(['team'=>$from_id,'level'=>3])->value('user_id');
////                                    if($third){
////                                        $user_level = (new User())->where(['id'=>$third])->value('level');
//////                                        $rate3 = (new \app\api\model\Teamlevel())->where(['level'=>$user_level])->value('rate3');
////                                        $rate3 = $team_level->detail($user_level)['rate3'];
////                                        $commission->setTableName($third);
////                                        $insert = [
////                                            'to_id' => $third,
////                                            'from_id' => $from_id,
////                                            'level' => 3,
////                                            'order_id' => $order_id,
////                                            'commission' => bcmul($commissions,$rate3/100,2),
////                                            'commission_fee' => $rate3,
////                                            'createtime' => time(),
////                                            'updatetime' => time(),
////                                        ];
////                                        //余额变动
////                                        if($insert['commission'] > 0) {
////                                            $commission->insert($insert);
////                                            $isok = $usermoneylog->moneyrecords($third, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
////                                        }
////                                        $usertotal->where('user_id', $third)->setInc('third_commission', $insert['commission']);
////                                    }
//                                }
//                            }
                        }
                        Db::commit();
                    } catch (Exception $e) {
//                        $commission->push($to_id, $from_id, $level, $order_id, $commission_fee, $commissions, $agent_id);
                        Db::rollback();
                        Log::mylog('佣金发放', $e, 'commissionerror');
                    }
                }
                //业务end
            }, $ws);
        });
        //监听WebSocket连接关闭事件
        $ws->on('Close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
        });
        $ws->start();
    }
}
