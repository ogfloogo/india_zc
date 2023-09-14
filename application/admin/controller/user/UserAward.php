<?php

namespace app\admin\controller\user;

use app\admin\model\financebuy\PopularizeAward;
use app\admin\model\User;
use app\admin\model\user\UserAward as UserAwardModel;
use app\admin\model\userlevel\UserTotal;
use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class UserAward extends Backend
{

    /**
     * UserAward模型对象
     * @var \app\admin\model\user\UserAward
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\UserAward;
        $this->view->assign("statusList", $this->model->getStatusList());
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
        $this->relationSearch = true;

        $user_id = $this->request->param('user_id', 0);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            $this->getUserAwardInfo($user_id);
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with('user,userTotal')
            ->where($where)
            ->where(['user_award.user_id' => $user_id])
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    protected function getUserAwardInfo($user_id)
    {
        if (!$user_id) {
            return [];
        }
        $userTotalInfo = (new UserTotal())->where(['user_id' => $user_id])->find();
        $row['invite_number'] = $userTotalInfo['invite_number'];

        $row['recharge_number'] = (new UserAwardModel())->where(['user_id' => $user_id, 'status' => ['GT', 0]])->count();
        $row['recharge_amount'] = (new UserAwardModel())->where(['user_id' => $user_id])->sum('recharge');
        $row['reward_amount_received'] = (new UserTotal())->where(['user_id'=>$user_id])->value('promotion_award');
        $row['reward_amount'] = (new UserAwardModel())->where(['user_id' => $user_id, 'status' => ['GT', 0]])->count();
        $row['reward_amount_unreceived'] = (new PopularizeAward())->where(['user_id'=>$user_id])->sum('not_claimed');
        $row['total_commission'] = (new UserTotal())->where(['user_id'=>$user_id])->value('total_commission');
        $row['level'] = (new User())->where(['id' => $user_id])->value('level');
        $userIds = (new UserAwardModel())->where(['user_id' => $user_id])->column('source');
        $row['higher_level_number'] = (new User())->where(['id' => ['IN', $userIds], 'level' => ['GT', $row['level']]])->count();
        $this->assign('row', $row);
    }
}
