<?php

namespace app\api\command;

use app\api\model\Financeissue;
use app\api\model\Financeorder;
use think\cache\driver\Redis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\api\model\User;
use app\api\model\Usermoneylog;
use think\Config;
use think\Db;
use think\Log;
use think\Exception;


class Robot extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Robot')
            ->setDescription('理财机器人');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Robot');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9513);
        //守护进程模式
        $ws->set([
            'daemonize' => true,
            'worker_num' => 1,
            // 'task_worker_num' => 4,
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
            $user = new User();
            $usermoneylog = new Usermoneylog();
            \Swoole\Timer::tick(1500, function () use ($redis, $user, $usermoneylog, $worker_id) {
                $redis->handler()->select(6);
                $list = $redis->handler()->Hgetall("zclc:financeproject:robot");
                foreach ($list as $k => $v) {
                    try {
                        $v_arr = explode('-', $v);
                        if ($v_arr[0] < time()) { //到期时间
                            $robot_user_id = db('user_robot')->order('buytime asc')->value('id');
                            $data = [
                                'project_id' => $k, //众筹方案ID
                                'robot_user_id' => $robot_user_id, //指定机器人
                                'amount' => $v_arr[1] ?? 0, //机器人投资金额
                            ];
                            $return = (new Financeorder())->financeRobotBuy($data);
                            if(!$return){
                                continue;
                            }
                        }
                    } catch (Exception $e) {
                        Db::rollback();
                        Log::mylog('理财机器人', $e, 'Robot');
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
