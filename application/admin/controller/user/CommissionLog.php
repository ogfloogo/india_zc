<?php

namespace app\admin\controller\user;

use app\admin\model\sys\TeamLevel;
use app\admin\model\User;
use app\admin\model\user\UserCategory;
use app\admin\model\user\UserTeam;
use app\admin\model\userlevel\UserTotal;
use app\api\model\Level;
use app\common\controller\Backend;

/**
 * 团队佣金记录管理
 *
 * @icon fa fa-circle-o
 */
class CommissionLog extends Backend
{

    /**
     * CommissionLog模型对象
     * @var \app\admin\model\user\CommissionLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\CommissionLog;
        $this->view->assign("levelList", $this->model->getLevelList());
    }

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        $user_id = $this->request->param('user_id', 0);
        if ($user_id) {
            $this->model->setTableName($user_id);
        }
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            $this->getUserTeamInfo($user_id);
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model->setTableName($user_id)
            ->with('fromUser,toUser,order')
            ->where($where)
            ->where(['to_id' => $user_id])
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    protected function getUserTeamInfo($user_id)
    {
        if (!$user_id) {
            return [];
        }

        $userTotalInfo = (new UserTotal())->where(['user_id' => $user_id])->find();

        $row['group_invite_number'] = (new UserTeam())->where(['user_id' => $user_id, 'level' => ['GT', 0]])->count();

        $row['level_1_number'] = (new UserTeam())->where(['user_id' => $user_id, 'level' => 1])->count();
        $row['level_2_number'] = (new UserTeam())->where(['user_id' => $user_id, 'level' => 2])->count();
        $row['level_3_number'] = (new UserTeam())->where(['user_id' => $user_id, 'level' => 3])->count();
        $row['total_commission'] = $userTotalInfo['total_commission'];
        $row['today_commission'] = (new UserCategory())->where(['date'=>date('Y-m-d'),'user_id' => $user_id])->sum('total_commission');
        $row['level'] = (new User())->where(['id' => $user_id])->value('level');
//        $level_info = (new TeamLevel())->where(['level'=>$row['level']])->find();
        $level_info = (new Level())->detail($row['level']);
        $row['level_1_rate'] = $level_info['rate1'];
        $row['level_2_rate'] = $level_info['rate2'];
        $row['level_3_rate'] = $level_info['rate3'];
//        $userIds = (new UserTeam())->where(['user_id' => $user_id])->column('team');
//        $row['higher_level_number'] = (new User())->where(['id' => ['IN', $userIds], 'level' => ['GT', $row['level']]])->count();
        $this->assign('row', $row);
    }
}
