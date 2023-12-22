<?php

namespace app\admin\controller\finance;

use app\common\controller\Backend;
use app\pay\model\Paycommon;

/**
 * 充值记录管理
 *
 * @icon fa fa-circle-o
 */
class UserRecharge extends Backend
{

    /**
     * UserRecharge模型对象
     * @var \app\admin\model\finance\UserRecharge
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\finance\UserRecharge;
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

        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with('rechargeChannel,user')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    public function doPay()
    {
        $id = $this->request->param('id', 0);
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($row['status'] != 0) {
            $this->error(__('状态不是待支付，无法操作'));
        }
        (new Paycommon())->paynotify($row['order_id'], '手动通过', $row['price'], '手动通过');
        $this->success();
    }
}
