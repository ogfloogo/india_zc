<?php

namespace app\api\command;

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


class Sendlist extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Sendlist')
            ->setDescription('体验到期');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('Sendlist');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9503);
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
            \Swoole\Timer::tick(3000, function () use ($redis, $user, $usermoneylog, $worker_id) {
                $redis->handler()->select(6);
                $list = $redis->handler()->ZRANGEBYSCORE('zclc:sendlist', '-inf', time(), ['withscores' => true]);
                foreach ($list as $key => $value) {
                    $lock = $redis->handler()->setnx("zclc:lock" . $key, $key);
                    // Log::mylog('进程号', $worker_id . "-" . $lock . '==id:' . $key . '===' . $redis->handler()->ttl("zclc:lock" . $key), 'worker');
                    if (!$lock) {
                        continue;
                    }
                    // Log::mylog('进程号11', $worker_id . "-" . $lock . '==id:' . $key . '===' . $redis->handler()->ttl("zclc:lock" . $key), 'worker');
                    $redis->handler()->expireAt("zclc:lock" . $key, time() + 10);
                    // Log::mylog('进程号2', $worker_id . "-" . $lock . '==id:' . $key, 'worker');
                    $usermoneylog->settables($key);
                    $user_register_reward = $usermoneylog->where('type',9)->where('user_id',$key)->find();
                    try {
                        //余额变动
                        Db::startTrans();
                        $isok = $usermoneylog->moneyrecords(intval($key), $user_register_reward['money'] , 'dec', 13, "体验到期");
                        // Log::mylog('进程号3', $worker_id . "-" . $lock . '==id:' . $key . '====isok:' . $isok, 'worker');

                        if ($isok == false) {
                            // Log::mylog('进程号rollback', $worker_id . "-" . $lock . '==id:' . $key . '====isok:' . $isok, 'worker');
                            Db::rollback();
                            $redis->handler()->del("zclc:lock" . $key);
                            continue;
                        } else {
                            Db::commit();
                            // Log::mylog('commit', $worker_id . "-" . $lock . '==id:' . $key . '====isok:' . $isok, 'worker');
                            Log::mylog('体验到期', $list, 'sendlist');
                            // Log::mylog('进程号success1', $worker_id . "-" . $lock . '==id:' . $key . '====value:' . $redis->handler()->get("zclc:lock" . $key), 'worker');
                            $redis->handler()->zRem('zclc:sendlist', $key);
                            $redis->handler()->del("zclc:lock" . $key);
                            // Log::mylog('进程号success2', $worker_id . "-" . $lock . '==id:' . $key . '====value:' . $redis->handler()->get("zclc:lock" . $key), 'worker');
                        }
                    } catch (Exception $e) {
                        // Log::mylog('excpe', $worker_id . "-" . $lock . '==id:' . $key . '====isok:' . $isok, 'worker');
                        Db::rollback();
                        Log::mylog('体验到期', $e, 'sendlist');
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
