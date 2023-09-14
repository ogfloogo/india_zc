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

class BuildTable extends Command
{
    protected $model = null;
    protected $tableList = [];
    protected $redisInstance = null;
    protected $key = 'last_commission_id';
    protected function configure()
    {
        $this->setName('BuildTable')
            ->setDescription('构建分表');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $this->redisInstance = ((new Redis())->handler());
        set_time_limit(0);
        // $this->tableList = ['fa_user_money_log'];
        // $this->tableNo = [91];
        $this->tableList = ['fa_commission_log'];
        $this->tableNo = [130];
        $this->buildTable();
    }

    protected function buildTable()
    {
        $field = 'to_id';
        $max_id = 7000000;
        $mod = 1000; //用户1000一个表
        $step = 1000000; //一次迁移的数据条数
        $last_id = $this->redisInstance->get($this->key);
        $last_id = intval($last_id);

        $idx = 0;
        $total = 0;
        foreach ($this->tableList as $tablename) {
            $base_name = $tablename . '_base';
            for ($i = 1; $i <= $this->tableNo[$idx]; $i++) {
                $tbname = $tablename . '_' . intval($i);
                // $count_sql = "SELECT COUNT(*) as num FROM " . $tbname." LIMIT 1";
                // $num = Db::query($count_sql);
                // $total +=$num[0]['num'];
                // echo $tbname.'===='.$num[0]['num'] .'===='.$total."\n";

                // $sql = 'CREATE TABLE IF NOT EXISTS ' . $tbname . ' LIKE ' . $base_name;
                // Db::execute($sql);
                // echo $sql . "\n";

                // $sql1 = 'ALTER TABLE ' . $tbname . ' auto_increment=' . $max_id;
                // Db::execute($sql1);

                // echo $sql1 . "\n";
                $from = $mod * ($i - 1) + 1;
                $to = $mod * $i;
                $user_ids = range($from, $to);
                // print_r($user_ids);
                $from = $last_id + 1;
                $end = $last_id + $step;
                $sql2 = 'INSERT INTO ' . $tbname . ' (SELECT * FROM ' . $tablename . ' WHERE id>=' . $from . ' AND id<=' . $end . ' AND '.$field.' IN (' . implode(',', $user_ids) . '))';
                Db::execute($sql2);
                echo $sql2 . "\n";
                // break;
            }
            $idx++;
        }
        $this->redisInstance->set($this->key, $end);
    }
}
