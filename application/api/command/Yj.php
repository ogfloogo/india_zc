<?php

namespace app\api\command;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;
use app\api\model\Commission;
use app\api\model\Order;
use app\api\model\Usermoneylog;
use app\api\model\Usertotal;
use think\Db;
use think\Log;

class Yj extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Yj')
            ->setDescription('佣金发放');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Yj');
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
                for ($i = 0; $i < 50; $i++) {
                    //开启事务 
                    Db::startTrans();
                    try {
                        $value = $redis->handler()->lpop('commissionlistnew');
                        if ($value) {
                            $list = json_decode($value, true);
                            Log::mylog('getlist', $list, 'getlist');
                            foreach ($list as $key => $value) {
                                $to_id = $value['to_id']; //受益ID
                                $from_id = $value['user_id']; //来源ID
                                $level = $value['level']; //等级
                                $order_id = $value['order_id']; //订单ID
                                $commission_fee = $value['commission_fee_level']; //佣金比例
                                $commissions = $value['commission']; //佣金
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
                                ];
                                $commission->insert($insert);
                                //余额变动
                                $isok = $usermoneylog->moneyrecords($to_id, $commissions, 'inc', 4, "来源ID:" . $from_id);
                                if ($isok == false) {
                                    //失败插入
                                    Log::mylog('getlisterror', $value, 'getlisterror');
                                    $commission->pushjson(json_encode($value));
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
                        }
                        Db::commit();
                    } catch (Exception $e) {
                        if($value){
                            $commission->pushjson($value);
                        }
                        Db::rollback();
                        Log::mylog('佣金发放', $e, 'commissionerror');
                    }
                }
            }, $ws);
        });
        //监听WebSocket连接关闭事件
        $ws->on('Close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
        });
        $ws->start();
    }
}
