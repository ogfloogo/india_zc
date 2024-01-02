<?php

namespace app\api\command;

use app\api\controller\Shell;
use app\api\model\Financeorder;
use app\api\model\Report;
use app\api\model\Report as ModelReport;
use app\api\model\User;
use think\Cache;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;


class Statistics extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Statistics')
            ->setDescription('the report command');
    }

    protected function execute(Input $input, Output $output)
    {
        (new Shell())->addreport();
        $report = new ModelReport();
        //进行中的订单金额
        $total_order_money = (new Financeorder())->where(['status'=>1,'popularize'=>['<>',2],'is_robot'=>0])->sum('amount');
        //预计当天利息总额
        $release_interest = (new Financeorder())->whereTime('collection_time','today')->where(['is_robot'=>0])->sum('interest');
        //等额本息每日返还本金
        $release_capital1 = (new Financeorder())->whereTime('collection_time','today')->where(['type'=>1,'is_robot'=>0])->sum('capital');
        //先息后本返还本金
        $release_capital2 = (new Financeorder())->whereTime('earning_end_time','today')->where(['type'=>2,'is_robot'=>0])->sum('capital');
        //预计当天发放总额
        $release_money = $release_interest + $release_capital1 + $release_capital2;
        $user_balance = (new User())->sum('money');
        $data = [
            'total_order_money' => $total_order_money,
            'release_money' => $release_money,
            'release_interest' => $release_interest,
            'release_capital' => $release_capital1,
            'user_balance' => $user_balance
        ];
        $report->where('date', date("Y-m-d", time()))->update($data);
    }
}
