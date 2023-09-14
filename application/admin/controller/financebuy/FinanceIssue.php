<?php

namespace app\admin\controller\financebuy;

use app\common\controller\Backend;

/**
 * 活动期号管理
 *
 * @icon fa fa-circle-o
 */
class FinanceIssue extends Backend
{

    /**
     * FinanceIssue模型对象
     * @var \app\admin\model\financebuy\FinanceIssue
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\financebuy\FinanceIssue;
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

        $finance_id = $this->request->param('finance_id', 0);
        $issue_id = $this->request->param('ids', 0);

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
            ->with('finance')
            ->where($where)
            // ->where(['finance_id' => $finance_id])
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

}
