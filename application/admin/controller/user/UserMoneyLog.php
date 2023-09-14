<?php

namespace app\admin\controller\user;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog as UserUserMoneyLog;
use app\admin\model\userlevel\UserTotal;
use app\common\controller\Backend;

/**
 * 会员余额变动管理
 *
 * @icon fa fa-circle-o
 */
class UserMoneyLog extends Backend
{

    /**
     * UserMoneyLog模型对象
     * @var \app\admin\model\user\UserMoneyLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\UserMoneyLog;
        $this->view->assign("typeList", $this->model->getTypeList());
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
        if ($user_id) {
            $this->model->setTableName($user_id);
        }
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            $this->getUserTotalInfo($user_id);

            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $query = $this->model->where($where);
        if ($user_id) {
            $query->where(['user_id' => $user_id]);
        }
        $list = $query->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    protected function getUserTotalInfo($user_id)
    {
        if (!$user_id) {
            return [];
        }
        $userInfo = (new User())->where(['id' => $user_id])->field('id,nickname,mobile,money')->find();
        $row = (new UserTotal())->where(['user_id' => $user_id])->find();
        $row['id'] = $userInfo['id'];
        $row['nickname'] = $userInfo['nickname'];
        $row['mobile'] = $userInfo['mobile'];
        $row['money'] = $userInfo['money'];
        $money_model = (new UserUserMoneyLog());
        $money_model->setTableName($user_id);
        $inc_total = $money_model->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'inc'])->sum('money');
        $dec_total = $money_model->where(['user_id' => $user_id, 'type' => 10, 'mold' => 'dec'])->sum('money');

        $row['group_buying_commission'] = $money_model->where(['user_id' => $user_id, 'type' => 7])->sum('money');
        $row['head_of_the_reward'] = $money_model->where(['user_id' => $user_id, 'type' => 8])->sum('money');
        $row['total_commission'] = $money_model->where(['user_id' => $user_id, 'type' => 4])->sum('money');
        $row['user_award'] = (new UserAward())->where(['user_id' => $user_id, 'status' => 1])->sum('moneys');
        $row['admin_amount'] = bcsub($inc_total, $dec_total, 2);
        $row['new_user_award'] = $money_model->where(['user_id' => $user_id, 'type' => 9])->value('money');
        $row['waiting_withdraw'] = (new UserCash())->where(['user_id' => $user_id, 'status' => ['IN', [0, 1, 2]]])->sum('price');
        $row['total_withdrawals'] = (new UserCash())->where(['user_id' => $user_id, 'status' => 3])->sum('price');
        $row['total_recharge'] = (new UserRecharge())->where(['user_id' => $user_id, 'status' => 1])->sum('price');

        $this->assign('row', $row);
    }
}
