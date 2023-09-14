<?php

namespace app\admin\controller\agent;

use app\common\controller\Backend;

/**
 * 代理每日统计管理
 *
 * @icon fa fa-circle-o
 */
class AgentDailyReport extends Base
{

    /**
     * AgentDailyReport模型对象
     * @var \app\admin\model\report\AgentDailyReport
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\report\AgentDailyReport;

    }

    public function index()
    {
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
            ->where(['agent_id' => $this->agentInfo['id']])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

}
