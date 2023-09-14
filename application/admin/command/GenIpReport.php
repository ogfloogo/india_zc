<?php

namespace app\admin\command;

use app\admin\model\activity\Popups;
use app\admin\model\AuthRule;
use app\admin\model\groupbuy\Goods;
use app\admin\model\groupbuy\GoodsCategory;
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

class GenIpReport extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('GenIpReport')
            ->setDescription('生成黑名单报表');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        (new IpReport())->where(['type'=>['IN',[0,1,2]]])->delete();
        $this->genReport(0);
        $this->genReport(1);
        $this->genReport(2);
    }

    protected function genReport($type = 0)
    {
        if ($type == 0) {
            $list = (new User())->where(['status' => 1])->field('joinip as content,count(1) as num')->group('joinip')->order('num')->having('num > 10')->select();
        } elseif ($type == 1) {
            $list = (new User())->where(['status' => 1])->field('loginip as content,count(1) as num')->group('loginip')->order('num')->having('num > 10')->select();
        } else {
            $list = (new User())->where(['status' => 1])->field('device_id as content,count(1) as num')->group('device_id')->order('num')->having('num > 10')->select();
        }
        $allData = [];
        foreach($list as $item){
            $newItem['type'] = $type;
            $newItem['content'] = $item['content'];
            $newItem['num'] = $item['num'];
            $newItem['createtime'] = time();
            $allData[] = $newItem;
        }
        (new IpReport())->insertAll($allData);
    }
}
