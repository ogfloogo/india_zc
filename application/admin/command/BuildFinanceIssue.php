<?php

namespace app\admin\command;

use app\admin\model\activity\Activity;
use app\admin\model\activity\ActivityPrize;
use app\admin\model\activity\ActivityTask;
use app\admin\model\activity\CashActivity;
use app\admin\model\activity\Popups;
use app\admin\model\activity\VipVoucher;
use app\admin\model\AuthRule;
use app\admin\model\financebuy\Finance;
use app\admin\model\financebuy\FinanceIssue;
use app\admin\model\financebuy\FinanceRate;
use app\admin\model\groupbuy\Goods;
use app\admin\model\groupbuy\GoodsCategory;
use app\admin\model\sys\AppVersion;
use app\admin\model\sys\HighEarning;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usertotal;
use ReflectionClass;
use ReflectionMethod;
use think\Cache;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;

class BuildFinanceIssue extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('BuildFinanceIssue')
            ->setDescription('生成理财期号');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {

        set_time_limit(0);
        $this->buildFinanceIssue();
    }


    protected function buildFinanceIssue()
    {
        $list = Db::table('fa_finance')->where(['status' => 1])->select();
        if (empty($list)) {
            return false;
        }
        foreach ($list as $item) {
            $wc = [];
            $wc['finance_id'] = $item['id'];
            $wc['presell_end_time'] = ['GT', time()];
            $count = (new FinanceIssue())->where($wc)->count();
            if ($count >= 2) {
                continue;
            }

            for ($i = 0; $i < 2 - $count; $i++) {
                $lastIssue = (new FinanceIssue())->where(['finance_id' => $item['id']])->order('name DESC')->find();
                $startTime = $lastIssue ? ($lastIssue['presell_end_time']) : time();
                $issueNo = $lastIssue ? ($lastIssue['name'] + 1) : 1001;
                $data = [];
                $data['finance_id'] = $item['id'];
                $data['name'] = $issueNo;
                $data['day'] = $item['day'];
                $data['presell_start_time'] = $startTime;
                $data['presell_end_time'] = $startTime + $item['presell_day'] * 86400;
                $data['start_time'] = $startTime + $item['presell_day'] * 86400;
                $data['end_time'] = $startTime + ($item['presell_day'] + $item['day']) * 86400;
                $data['status'] = 0;
                $data['createtime'] = time();
                $data['updatetime'] = time();
                (new FinanceIssue())->insertGetId($data);
            }
        }
    }
}
