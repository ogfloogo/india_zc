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
use app\api\model\Sendemaillog;
use app\api\model\Usermoneylog;
use app\api\model\Usertotal;
use think\Db;
use think\Log;

class Sendemail extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Sendemail')
            ->setDescription('邮件群发');
    }

    protected function execute(Input $input, Output $output)
    {
//        // 指令输出
        $output->writeln('Commissionproject');
        $ws = new \Swoole\WebSocket\Server('0.0.0.0', 9519);
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
            $redis->handler()->select(7);
            \Swoole\Timer::tick(3000, function () use ($redis) {
                //业务start
                //开启事务 
                for ($i = 0; $i < 100; $i++) {
                    Db::startTrans();
                    try {
                        $value = $redis->handler()->lpop('sendemail');
                        if ($value) {
                            $list = explode("-", $value);
                            $return = (new Sendemaillog())->sendeamil($list[1]);
                            if(!$return){

                            }
                        }
                        Db::commit();
                    } catch (Exception $e) {
                        Db::rollback();
                        Log::mylog('发送失败', $e, 'sendemail');
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
