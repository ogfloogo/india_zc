<?php

namespace app\admin\command;

use app\admin\model\activity\Popups;
use app\admin\model\AuthRule;
use app\admin\model\groupbuy\Goods;
use app\admin\model\groupbuy\GoodsCategory;
use app\admin\model\order\Order;
use app\admin\model\order\OrderBackup as OrderOrderBackup;
use app\admin\model\sys\IpReport;
use app\admin\model\User;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usermerchandise;
use app\api\model\Usertotal;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use ReflectionClass;
use ReflectionMethod;
use think\Cache;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;

class Consumer extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('Consumer')
            ->setDescription('Consumer');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $this->consume();
    }

    protected function consume()
    {

        $workerNum = 4; // 一般为CPU核数的4倍
        echo $workerNum;
        // 进程池
        $pool = new \Swoole\Process\Pool($workerNum);
        // 多进程，共享一个连接
        $pool->on('WorkerStart', function ($pool, $workerId) {
            // 子进程空间

            echo "WorkerId {$workerId} is started \n";

            try {
                $exchange = 'recharge_exchange';
                $queue = 'recharge_queue';
                $consumerTag = 'recharge_consumer';
                // 1.建立连接
                $config = [
                    'host' => '127.0.0.1',
                    'port' => 5672,
                    'vhost' => '/',
                    'user' => 'guest',
                    'password' => 'guest'
                ];
                // 1.建立连接
                $connection = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password'], $config['vhost']);
                // 2.建立通道
                $channel = $connection->channel();
                // 3.创建队列
                /*
    name: $queue
    passive: false
    durable: true // the queue will survive server restarts
    exclusive: false // the queue can be accessed in other channels
    auto_delete: false //the queue won't be deleted once the channel is closed.
*/
                $channel->queue_declare($queue, false, true, false, false);
                //4. 创建交换机
                /*
    name: $exchange
    type: direct
    passive: false
    durable: true // the exchange will survive server restarts
    auto_delete: false //the exchange won't be deleted once the channel is closed.
*/

                $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
                $data = [
                    'tid' => uniqid(),
                    'msg' => 'trade'
                ];

                $routingKey = '/trade';

                // 4.绑定路由监听
                $channel->queue_bind($queue, $exchange);

                // 消费
                /**
                 * $queue = '',         被消费队列名称
                 * $consumer_tag = '',  消费者客户端标识，用于区分客户端
                 * $no_local = false,   这个功能属于amqp的标准，但是rabbitmq未实现
                 * $no_ack = false,     收到消息后，是否要ack应答才算被消费
                 * $exclusive = false,  是否排他，即为这个队列只能由一个消费者消费，适用于任务不允许并发处理
                 * $nowait = false,     不返回直接结果，但是如果排他开启的话，则必须需要等待结果的，如果二个都开启会报错
                 * $callback = null,    回调函数处理逻辑
                 */
                // 回调
                $callback = function ($msg) use ($workerId) {
                    var_dump($workerId . "-----" . $msg->body);
                    $body = (json_decode($msg->body,true));
                    // var_dump($msg->delivery_info);
                    // 响应ack
                    $data['tid'] = $body['tid'];
                    $data['info'] = json_encode($body['info'],JSON_UNESCAPED_UNICODE);
                    $data['msg'] = $body['msg'];
                    
                    $res = Db::table('fa_mq_log')->insertGetId($data);
                    if($res){
                        $msg->delivery_info["channel"]->basic_ack($msg->delivery_info["delivery_tag"]);
                    }
                };

                $channel->basic_consume($queue, "", false, false, false, false, $callback);


                // 监听
                while ($channel->is_consuming()) {
                    $channel->wait();
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        });

        // 进程关闭
        $pool->on('WorkerStop', function ($pool, $workerId) {
            echo "WorkerId {$workerId} is stoped\n";
        });

        $pool->start();
    }
}
