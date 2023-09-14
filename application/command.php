<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

use app\api\model\Finance;

return [
    'app\admin\command\Crud',
    'app\admin\command\Menu',
    'app\admin\command\Install',
    'app\admin\command\Min',
    'app\admin\command\Addon',
    'app\admin\command\Api',
    'app\api\command\Report',
    'app\admin\command\BuildCache',
    'app\admin\command\BuildPool',
    'app\admin\command\BuildTable',
    'app\api\command\Commissionissued',
    'app\admin\command\GenIpReport',
    'app\admin\command\CheckUserMoney',
    'app\admin\command\BuildTableTwice',
    'app\admin\command\OrderBackup',
    'app\api\command\Yj',
    'app\api\command\Rtmoney',
    'app\admin\command\RecalUserMoney',
    'app\admin\command\KickDeadUser',
    'app\admin\command\GenTargetRobot',
    'app\admin\command\GenRobotOrder',
    'app\admin\command\Consumer',
    'app\admin\command\Producer',
    'app\admin\command\GenDailyReport',
    'app\api\command\Sendlist',
    'app\admin\command\GenAgentDailyReport',
    'app\admin\command\BuildLang',
    'app\api\command\Sendmoney',
    'app\admin\command\BuildFinanceIssue',
    'app\api\command\Givemoney',
    'app\api\command\Givemoneytg',
    'app\api\command\Givemoneyty',
    'app\api\command\Robot',
    'app\api\command\Financestatus',
    'app\api\command\Commissionproject',
    'app\api\command\Commissionprojecttg',
    'app\api\command\Commissionprojectty',
    'app\admin\command\Sendemail',
    'app\admin\command\ForumCache',
    'app\api\command\Monthrank',
    'app\api\command\Historyrank',
    'app\api\command\Refundmoney'
];
