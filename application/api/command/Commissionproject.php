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
use app\api\model\Financeorder;
use think\Db;
use think\Log;

class Commissionproject extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Commissionproject')
            ->setDescription('普通项目-发放');
    }

    protected function execute(Input $input, Output $output)
    {
//        // 指令输出
        $output->writeln('Commissionproject');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9502);
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
            $order = new Financeorder();
            $team_level = (new Level());
            \Swoole\Timer::tick(3000, function () use ($redis, $commission, $usermoneylog, $usertotal, $order, $team_level) {
                //业务start
                //开启事务 
                for ($i = 0; $i < 100; $i++) {
                    Db::startTrans();
                    try {
                        $value = $redis->handler()->lpop('commissionlist');
                        if ($value) {
                            $list = explode("-", $value);
                            $from_id = $list[0]; //来源ID
                            $order_id = $list[1]; //订单ID
                            $commissions = $list[2]; //佣金
                            $level = $list[3]; //等级
                            $usermoneylog->moneyrecords($from_id, $commissions, 'inc', 23, "理财收益{$order_id}");
                            //上级佣金发放
                            $order_info = $order->where(['id'=>$order_id])->find();
                            if($order_info['createtime'] > 1692881400&&$order_info['popularize'] == 0){
                                //2023-08-24 21点之后购买的,并且是普通项目，采用每日返佣金
                                $first = (new Userteam())->where(['team'=>$from_id,'level'=>1])->value('user_id');
                                if($first){
                                    $rate1 = config('site.first_team');
                                    $commission->setTableName($first);
                                    $insert = [
                                        'to_id' => $first,
                                        'from_id' => $from_id,
                                        'level' => 1,
                                        'order_id' => $order_id,
                                        'commission' => bcmul($commissions,$rate1/100,2),
                                        'commission_fee' => $rate1,
                                        'createtime' => time(),
                                        'updatetime' => time(),
                                    ];
                                    //余额变动
                                    if($insert['commission'] > 0){
                                        $commission->insert($insert);
                                        $isok = $usermoneylog->moneyrecords($first, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
                                    }
                                    $usertotal->where('user_id', $first)->setInc('first_commission', $insert['commission']);
                                    $second = (new Userteam())->where(['team'=>$from_id,'level'=>2])->value('user_id');
                                    if($second){
                                        $rate2 = config('site.second_team');
                                        $commission->setTableName($second);
                                        $insert = [
                                            'to_id' => $second,
                                            'from_id' => $from_id,
                                            'level' => 2,
                                            'order_id' => $order_id,
                                            'commission' => bcmul($commissions,$rate2/100,2),
                                            'commission_fee' => $rate2,
                                            'createtime' => time(),
                                            'updatetime' => time(),
                                        ];
                                        //余额变动
                                        if($insert['commission'] > 0) {
                                            $commission->insert($insert);
                                            $isok = $usermoneylog->moneyrecords($second, $insert['commission'], 'inc', 4, "来源ID:" . $from_id.'项目ID'.$order_id);
                                        }
                                        $usertotal->where('user_id', $second)->setInc('second_commission', $insert['commission']);
                                    }
                                }
                            }
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
