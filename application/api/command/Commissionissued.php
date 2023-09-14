<?php

namespace app\api\command;

use app\admin\model\AuthRule;
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

class Commissionissued extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Commissionissued')
            ->setDescription('佣金发放');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Commissionissued');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9502);
        //守护进程模式
        $ws->set([
            'daemonize' => true,
            'worker_num' => 2,
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
            $commission = new Commission();
            $usermoneylog = new Usermoneylog();
            $usertotal = new Usertotal();
            $order = new Order();
            \Swoole\Timer::tick(3000, function () use ($redis, $commission, $usermoneylog, $usertotal, $order) {
                //业务start
                //开启事务 
                for ($i = 0; $i < 100; $i++) {
                    Db::startTrans();
                    try {
                        $value = $redis->handler()->lpop('commissionlist');
                        if ($value) {
                            $list = explode("-", $value);
                            $to_id = $list[0]; //受益ID
                            $from_id = $list[1]; //来源ID
                            $level = $list[2]; //等级
                            $order_id = $list[3]; //订单ID
                            $commission_fee = $list[4]; //佣金比例
                            $commissions = $list[5]; //佣金
                            $agent_id = isset($list[6]) ? intval($list[6]) : 0;
                            $commission->setTableName($to_id);
                            //佣金log
                            $insert = [
                                'to_id' => $to_id,
                                'from_id' => $from_id,
                                'level' => $level,
                                'order_id' => $order_id,
                                'commission' => $commissions,
                                'commission_fee' => $commission_fee,
                                'createtime' => time(),
                                'updatetime' => time(),
                                'agent_id' => $agent_id,
                            ];
                            $commission->insert($insert);
                            //余额变动
                            $isok = $usermoneylog->moneyrecords($to_id, $commissions, 'inc', 4, "来源ID:" . $from_id);
                            if ($isok == false) {
                                //失败插入
                                $commission->push($to_id, $from_id, $level, $order_id, $commission_fee, $commissions, $agent_id);
                                Db::rollback();
                            }
                            switch ($level) {
                                case 1:
                                    //上级佣金统计
                                    $usertotal->where('user_id', $to_id)->setInc('first_commission', $commissions);
                                    //更新订单佣金
                                    $order->where('id', $order_id)->update(["level1" => $commissions]);
                                    break;
                                case 2:
                                    //上上级佣金统计
                                    $usertotal->where('user_id', $to_id)->setInc('second_commission', $commissions);
                                    //更新订单佣金
                                    $order->where('id', $order_id)->update(["level2" => $commissions]);
                                    break;
                                case 3:
                                    //上上上级佣金统计
                                    $usertotal->where('user_id', $to_id)->setInc('third_commission', $commissions);
                                    //更新订单佣金
                                    $order->where('id', $order_id)->update(["level3" => $commissions]);
                                    break;
                                default:
                                    # code...
                                    break;
                            }
                        }
                        Db::commit();
                    } catch (Exception $e) {
                        $commission->push($to_id, $from_id, $level, $order_id, $commission_fee, $commissions, $agent_id);
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
