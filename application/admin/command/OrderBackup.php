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

class OrderBackup extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('OrderBackup')
            ->setDescription('订单备份');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $max_order_id = (new OrderOrderBackup())->where(['id' => ['GT', 0]])->max('id');
        echo $max_order_id . "\n";
        $seven_days_ago = date('Y-m-d 00:00:00', strtotime('-3 days'));
        echo $seven_days_ago . "\n";
        $last_order = (new Order())->where(['createtime' => ['LT', strtotime($seven_days_ago), 'id' => ['GT', $max_order_id]]])->field('id')->order('id DESC')->find();
        if ($last_order && $last_order['id'] > $max_order_id) {
            print_r($last_order->toArray()) . "\n";

            $sql = "INSERT INTO fa_order_backup (SELECT * FROM  fa_order WHERE id>=" . $max_order_id . " AND id<=" . $last_order['id'] . ")";
            echo $sql . "\n";
            Db::execute($sql);
            $this->deleteOld($max_order_id, $last_order['id']);
        }
    }

    protected function deleteOld($max_order_id, $id_end)
    {
        $IdList = (new OrderOrderBackup())->where(['id' => ['BETWEEN', [$max_order_id, $id_end]], 'is_winner' => 0])->column('id');
        $step = 1000;
        if (count($IdList)) {
            $num = count($IdList);
            if ($num > $step) {
                for ($i = 0; $i < ceil($num / $step); $i++) {
                    $orderIds = array_slice($IdList, $i * $step, $step);
                    (new Order())->where(['id' => ['IN', $orderIds]])->delete();
                    echo (new Order())->getLastSql() . "\n";
                }
            } else {
                echo 'here:';
                (new Order())->where(['id' => ['IN', $IdList]])->delete();
                echo (new Order())->getLastSql() . "\n";
            }
        }
    }
}
