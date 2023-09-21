<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\financebuy\FinanceOrder;
use app\admin\model\order\Order;
use app\admin\model\sys\Report;
use app\admin\model\sys\TeamLevel;
use app\admin\model\User;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usertotal;
use app\common\controller\Backend;
use app\common\library\Log;
use app\common\model\Attachment;
use fast\Date;
use think\Db;
use think\Log as ThinkLog;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {
        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        // $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
        //     ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
        //     ->group('join_date')
        //     ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $date = date("Y-m-d", $time);
            $column[] = date("m-d", $time);
            $time_range = ['between', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];
            $reportInfo = (new Report())->where(['date' => $date])->find();

            $rechargeUserList[] = !empty($reportInfo) ? $reportInfo['rechargeuser'] : 0;
            $firstRechargeUserList[] = !empty($reportInfo) ? $reportInfo['first_rechargeuser'] : 0;
            $rechargeList[] = (new UserRecharge())->where(['status' => 1, 'createtime' => $time_range])->sum('price');
            $withdrawList[] = (new UserCash())->where(['status' => 2, 'createtime' => $time_range])->sum('price');
            $userList[] = (new User())->where(['createtime' => $time_range, 'is_robot' => 0])->count();
            $loginList[] = intval((new Usertotal())->getLoginCount(date('ymd', $time)));
            $time += 86400;
        }

        $levelList = (new TeamLevel())->order('level ASC')->column('level');
        foreach ($levelList as $level) {
            $userLevelList[] = (new User())->where(['buy_level' => $level, 'is_robot' => 0, 'status' => 1])->count();
        }
        $nosid_user = User::where(['sid' => 0])->column('id');
        $step = 1000;
        $today_nosid_recharge = 0;
        if (count($nosid_user) > $step) {
            $num = count($nosid_user);
            for ($i = 0; $i < ceil($num / $step); $i++) {
                $step_nosid_user = array_slice($nosid_user, $i * $step, $step);
                $today_nosid_recharge += UserRecharge::where(['status' => 1, 'user_id' => ['IN', $step_nosid_user], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price');
            }
        }
        $total_recharge = (new UserRecharge())->where(['status' => 1])->distinct(true)->field('user_id')->select();
        $total_withdraw = (new UserCash())->where(['id' => ['GT', 0]])->distinct(true)->field('user_id')->select();

        $todayReport = (new Report())->where(['date' => date('Y-m-d')])->find();
        $todayLogin = (new Usertotal())->getLoginCount();

        $this->view->assign([

            'totaluser'         => User::count(),
            'totalorder'        => FinanceOrder::where(['is_robot' => 0])->count(),
            'totalrecharge'        => UserRecharge::where(['status' => 1])->sum('price'),
            'totalwithdraw'     => UserCash::where(['status' => ['IN', [2, 3]]])->sum('price'),

            'todaynewuser'    => isset($todayReport['user']) ? $todayReport['user'] : 0,
            'todaylogin'    => intval($todayLogin),

            'todayorder'        => FinanceOrder::where(['is_robot' => 0, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todayopenorder'    => Order::where(['pay_status' => 1, 'type' => 1, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todayordermoney'        => FinanceOrder::where(['is_robot' => 0, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('amount'),


            'todayrecharge'        => UserRecharge::where(['status' => 1, 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            'todaywithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'todaywithdrawnum'     => UserCash::where(['status' => 0, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaywithdrawmoney'     => UserCash::where(['status' => 0, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'today_nosid_newuser'         => User::where(['sid' => 0, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'today_nosid_recharge'        => $today_nosid_recharge,

            'totaluserrecharge'         => count($total_recharge),
            'totaluserwithdraw'         => count($total_withdraw),
        ]);

        $this->assignconfig('chart_data', ['date' => $column, 'recharge' => $rechargeList, 'withdraw' => $withdrawList, 'user' => $loginList, 'reg' => $userList, 're_user' => $rechargeUserList, 'first' => $firstRechargeUserList]);
        $this->assignconfig('level_data', ['level' => $levelList, 'user' => $userLevelList]);
        $this->getAgentData();
        $this->getAgentDataList();
        return $this->view->fetch();
    }

    protected function getAgentData()
    {
        $total_recharge = (new UserRecharge())->where(['status' => 1, 'agent_id' => ['GT', 0]])->distinct(true)->field('user_id')->select();
        $total_withdraw = (new UserCash())->where(['id' => ['GT', 0], 'agent_id' => ['GT', 0]])->distinct(true)->field('user_id')->select();

        $agent_info = [

            'totaluser'         => User::where(['agent_id' => ['GT', 0]])->count(),
            'totalorder'        => Order::where(['pay_status' => 1, 'agent_id' => ['GT', 0]])->count(),
            'totalrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => ['GT', 0]])->sum('price'),
            'totalwithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => ['GT', 0]])->sum('price'),

            'todaynewuser'    => User::where(['agent_id' => ['GT', 0], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaylogin'    => User::where(['agent_id' => ['GT', 0], 'logintime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),

            'todayorder'        => Order::where(['pay_status' => 1, 'agent_id' => ['GT', 0], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todayopenorder'    => Order::where(['pay_status' => 1, 'agent_id' => ['GT', 0], 'type' => 1, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),


            'todayrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => ['GT', 0], 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            'todaywithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => ['GT', 0], 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'todaywithdrawnum'     => UserCash::where(['status' => 0, 'agent_id' => ['GT', 0], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaywithdrawmoney'     => UserCash::where(['status' => 0, 'agent_id' => ['GT', 0], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'today_nosid_newuser'         => User::where(['sid' => 0, 'agent_id' => ['GT', 0], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'today_nosid_recharge'        => 0,

            'totaluserrecharge'         => count($total_recharge),
            'totaluserwithdraw'         => count($total_withdraw),

        ];
        $this->view->assign('agent', $agent_info);
    }

    protected function getAgentDataById($agent_id)
    {
        $total_recharge = (new UserRecharge())->where(['status' => 1, 'agent_id' => $agent_id])->distinct(true)->field('user_id')->select();
        $total_withdraw = (new UserCash())->where(['id' => ['GT', 0], 'agent_id' => $agent_id])->distinct(true)->field('user_id')->select();

        $agent_info = [

            'totaluser'         => User::where(['agent_id' => $agent_id])->count(),
            'totalorder'        => Order::where(['pay_status' => 1, 'agent_id' => $agent_id])->count(),
            'totalrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => $agent_id])->sum('price'),
            'totalwithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => $agent_id])->sum('price'),

            'todaynewuser'    => User::where(['agent_id' => $agent_id, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaylogin'    => User::where(['agent_id' => $agent_id, 'logintime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),

            'todayorder'        => Order::where(['pay_status' => 1, 'agent_id' => $agent_id, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todayopenorder'    => Order::where(['pay_status' => 1, 'agent_id' => $agent_id, 'type' => 1, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),


            'todayrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => $agent_id, 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            'todaywithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => $agent_id, 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'todaywithdrawnum'     => UserCash::where(['status' => 0, 'agent_id' => $agent_id, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaywithdrawmoney'     => UserCash::where(['status' => 0, 'agent_id' => $agent_id, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),

            'today_nosid_newuser'         => User::where(['sid' => 0, 'agent_id' => $agent_id, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'today_nosid_recharge'        => 0,

            'totaluserrecharge'         => count($total_recharge),
            'totaluserwithdraw'         => count($total_withdraw),

        ];
        return $agent_info;
    }

    protected function getAgentDataList()
    {
        $assignList = [];
        $list = (new Admin())->where(['agent_id' => ['GT', 0], 'status' => 'normal'])->field('username,agent_id')->select();
        if (count($list)) {
            foreach ($list as $item) {
                $assignList[$item['username']] = $this->getAgentDataById($item['agent_id']);
            }
        }
        $this->view->assign('assignList', $assignList);
    }
}
