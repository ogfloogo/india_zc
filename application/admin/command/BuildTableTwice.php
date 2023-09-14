<?php

namespace app\admin\command;

use app\admin\model\activity\Popups;
use app\admin\model\AuthRule;
use app\admin\model\groupbuy\Goods;
use app\admin\model\groupbuy\GoodsCategory;
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

class BuildTableTwice extends Command
{
    protected $model = null;
    protected $tableList = [];
    protected $redisInstance = null;
    protected $key = 'last_id';
    protected function configure()
    {
        $this->setName('BuildTableTwice')
            ->setDescription('构建二级分表');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $this->redisInstance = ((new Redis())->handler());
        set_time_limit(0);
        $this->tableList = ['fa_user_money_log'];
        $this->tableNo = [10];
        $this->tableField = ['user_id'];
        // $this->tableList = ['fa_commission_log'];
        // $this->tableNo = [10];
        // $this->tableField = ['to_id'];
        $this->BuildTableTwice();
    }

    protected function BuildTableTwice()
    {
        $user_id_limit = 1000;
        $max_id = 9000000;
        $mod = 100; //用户1000一个表
        $step = 1000000; //一次迁移的数据条数

        $orginal_table_idx = ceil($user_id_limit / 1000);
        $idx = 0;
        $total = 0;
        foreach ($this->tableList as $tablename) {
            $key = $this->key . ':' . $tablename;
            $last_id = $this->redisInstance->get($key);
            $last_id = intval($last_id);

            $field = $this->tableField[$idx];

            $base_name = $tablename . '_base';
            for ($i = 1; $i <= $this->tableNo[$idx]; $i++) {
                $tbname = $tablename . '_' . $orginal_table_idx . '_' . intval($i);
                // $count_sql = "SELECT COUNT(*) as num FROM " . $tbname." LIMIT 1";
                // $num = Db::query($count_sql);
                // $total +=$num[0]['num'];
                // echo $tbname.'===='.$num[0]['num'] .'===='.$total."\n";

                $sql = 'CREATE TABLE IF NOT EXISTS ' . $tbname . ' LIKE ' . $base_name;
                Db::execute($sql);
                echo $sql . "\n";

                $sql1 = 'ALTER TABLE ' . $tbname . ' auto_increment=' . $max_id;
                Db::execute($sql1);

                echo $sql1 . "\n";
                $from = $mod * ($i - 1) + 1;
                $to = $mod * $i;
                $user_ids = range($from, $to);
                // print_r($user_ids);
                $from = $last_id + 1;
                $end = $last_id + $step;
                $sql2 = 'INSERT INTO ' . $tbname . ' (SELECT * FROM ' . $tablename. '_' . $orginal_table_idx . ' WHERE id>=' . $from . ' AND id<=' . $end . ' AND ' . $field . ' IN (' . implode(',', $user_ids) . '))';
                Db::execute($sql2);
                echo $sql2 . "\n";
                // break;
            }
            $idx++;
            $this->redisInstance->set($key, $end);
        }
    }
}
