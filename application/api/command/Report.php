<?php

namespace app\api\command;

use app\api\model\Report as ModelReport;
use think\Cache;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;


class Report extends Command
{
    protected $model = null;

    protected function configure()
    {
        // 指令配置
        $this->setName('Report')
            ->setDescription('the report command');
    }

    protected function execute(Input $input, Output $output)
    {
        $report = (new ModelReport())->where('date',date('Y-m-d',time()))->find();
        if(empty($report)){
            (new ModelReport())->insert([
                'date' => date('Y-m-d',time()),
                'createtime' => time(),
                'updatetime' => time(),
            ]);
            echo "插入成功"."\n";
        }else{
            echo "插入失败"."\n";
        }
        echo "------------";
        echo "执行成功"."\n";
    }
}
