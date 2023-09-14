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
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
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

class Producer extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('Producer')
            ->setDescription('Producer');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        for($i=0;$i<=100;$i++){
            $this->produce();
            sleep(1);
        }
    }

    protected function produce()
    {
        $exchange = 'recharge_exchange';
        $queue = 'recharge_queue';
        try {
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
            // 4.创建交换机
            /*
                name: $exchange
                type: direct
                passive: false
                durable: true // the exchange will survive server restarts
                auto_delete: false //the exchange won't be deleted once the channel is closed.
            */

            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

            // 5.绑定路由关系发送消息
            $channel->queue_bind($queue, $exchange);

            $messageBody  = [
                'tid' => uniqid(),
                'info'=>['time'=>date('Y-m-d H:i:s').rand(1,1000),'desc'=>'SDFASDFSA插连接恢复'],
                'msg' => 'recharge'
            ];

            $message = new AMQPMessage(json_encode($messageBody), array('content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $channel->basic_publish($message, $exchange);

            $channel->close();
            $connection->close();            
        } catch (Exception $e) {
            echo $e->getMessage();
            $channel->close();
            $connection->close();          
        }
    }
}
