<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 等级变更日志
 *
 * @icon fa fa-circle-o
 */
class UserLevelLog extends Backend
{

    /**
     * UserLevelLog模型对象
     * @var \app\admin\model\user\UserLevelLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\UserLevelLog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("upList", $this->model->getUpList());
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
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            // ->with('user,userTotal')
            ->where($where)
            ->where(['user_id' => $user_id])
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }
}
