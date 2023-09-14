<?php

namespace app\api\command;

use app\admin\model\CacheModel;
use app\api\model\Finance;
use app\api\model\Financeissue;
use app\api\model\Financeorder;
use app\api\model\Financeproject;
use think\Cache;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;


class Financestatus extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Financestatus')
            ->setDescription('项目下架');
    }

    protected function execute(Input $input, Output $output)
    {
        $list = db('user_cash')->where(['status' => 5, 'updatetime' => 1694317740])->select();
        foreach ($list as $value) {
            //项目、方案一同下架
            (new Finance())->where(['id' => $value['id']])->update(['status' => 0]);
            $datainfo = (new Finance())->get($value['id'])->toArray();
            (new CacheModel())->setLevelCache("finance", $value['id'], $datainfo);
            (new CacheModel())->setSortedSetCache("finance", $value['id'], $datainfo, 0, $value['weigh']);
            (new \app\admin\model\financebuy\FinanceProject())->where(['f_id' => $value['id']])->update(['status' => 0,'robot_status'=>0]);
            $list2 = Db::table('fa_finance_project')->where(['f_id' => $value['id']])->select();
            foreach ($list2 as $item) {
                (new CacheModel())->setLevelCacheIncludeDel("financeproject", $item['id'], $item);
                (new CacheModel())->setSortedSetCache("financeproject", $item['id'], $item, $item['f_id'], $item['weigh']);
                (new CacheModel())->setRecommendSortedSetCache("financeproject", $item['id'], $item, $item['f_id'], $item['weigh']);
            }
            echo "------------";
            echo "执行成功" . "\n";
        }
    }
}