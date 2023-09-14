<?php

namespace app\admin\controller;

use app\admin\controller\agent\Base;
use app\admin\model\Admin;
use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\order\Order;
use app\admin\model\sys\Report;
use app\admin\model\User;
use app\admin\model\userlevel\UserLevel;
use app\api\model\Usertotal;
use app\common\controller\Backend;
use app\common\library\Log;
use app\common\model\Attachment;
use fast\Date;
use think\Config;
use think\Db;
use think\Log as ThinkLog;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class AgentDashboard extends Base
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

        for ($time = $starttime; $time <= $endtime;) {
            $date = date("Y-m-d", $time);
            $column[] = date("m-d", $time);
            $time_range = ['between', [strtotime($date . ' 00:00:00'), strtotime($date . ' 23:59:59')]];

            // $rechargeList[] = (new UserRecharge())->where(['status' => 1, 'createtime' => $time_range, 'agent_id' => $this->agentInfo['id']])->sum('price');
            // $withdrawList[] = (new UserCash())->where(['status' => 2, 'createtime' => $time_range, 'agent_id' => $this->agentInfo['id']])->sum('price');
            $userList[] = (new User())->where(['createtime' => $time_range, 'is_robot' => 0, 'agent_id' => $this->agentInfo['id']])->count();
            $loginList[] = intval((new Usertotal())->getAgentLoginCount($this->agentInfo['id'], date('ymd', $time)));
            $time += 86400;
        }

        $levelList = (new UserLevel())->where(['status' => 1, 'deletetime' => null])->order('level ASC')->column('level');
        foreach ($levelList as $level) {
            $userLevelList[] = (new User())->where(['level' => $level, 'is_robot' => 0, 'status' => 1, 'agent_id' => $this->agentInfo['id']])->count();
        }

        $total_recharge = (new UserRecharge())->where(['status' => 1, 'agent_id' => $this->agentInfo['id']])->distinct(true)->field('user_id')->select();
        $total_withdraw = (new UserCash())->where(['id' => ['GT', 0], 'agent_id' => $this->agentInfo['id']])->distinct(true)->field('user_id')->select();
        $todayLogin = (new Usertotal())->getAgentLoginCount($this->agentInfo['id']);
        $this->view->assign([
            'totaluserrecharge'         => count($total_recharge),
            'totaluserwithdraw'         => count($total_withdraw),
            'totaluser'         => User::where(['agent_id' => $this->agentInfo['id']])->count(),
            'totalrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => $this->agentInfo['id']])->sum('price'),
            'totalwithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => $this->agentInfo['id']])->sum('price'),
            'todayrecharge'        => UserRecharge::where(['status' => 1, 'agent_id' => $this->agentInfo['id'], 'updatetime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            'todaywithdraw'     => UserCash::where(['status' => ['IN', [2, 3]], 'agent_id' => $this->agentInfo['id'], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            // 'todaywithdrawnum'     => UserCash::where(['status' => 0, 'agent_id' => $this->agentInfo['id'], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            // 'todaywithdrawmoney'     => UserCash::where(['status' => 0, 'agent_id' => $this->agentInfo['id'], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->sum('price'),
            'todaynewuser'    => User::where(['agent_id' => $this->agentInfo['id'], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todaylogin'    => intval($todayLogin),
            'totalorder'        => Order::where(['pay_status' => 1, 'agent_id' => $this->agentInfo['id']])->count(),
            'todayorder'        => Order::where(['pay_status' => 1, 'agent_id' => $this->agentInfo['id'], 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            'todayopenorder'    => Order::where(['pay_status' => 1, 'agent_id' => $this->agentInfo['id'], 'type' => 1, 'createtime' => ['between', [strtotime(date('Y-m-d') . ' 00:00:00'), strtotime(date('Y-m-d') . ' 23:59:59')]]])->count(),
            
        ]);

        $this->assignconfig('chart_data', ['date' => $column,  'reg' => $userList, 'user' => $loginList]);
        $this->assignconfig('level_data', ['level' => $levelList, 'user' => $userLevelList]);
        $this->view->assign('url', Config::get('host.h5_url') . '/#/pages/home/home/?agent=' . $this->agentInfo['code']);
        return $this->view->fetch();
    }
}
