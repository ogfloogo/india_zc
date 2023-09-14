<?php

namespace app\admin\controller\agent;

use app\admin\model\finance\WithdrawChannel;
use app\api\model\Usercash as ModelUsercash;
use app\api\model\Usermoneylog;
use app\common\controller\Backend;
use app\pay\model\Ppay;
use app\pay\model\Rpay;
use app\pay\model\Wepay;
use app\pay\model\Wowpay;
use Exception;
use think\Db;
use think\Exception as ThinkException;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 提现申请管理
 *
 * @icon fa fa-circle-o
 */
class UserCash extends Base
{

    /**
     * UserCash模型对象
     * @var \app\admin\model\finance\UserCash
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\finance\UserCash;
        $this->view->assign("typeList", $this->model->getTypeList());
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
            ->where(['user.agent_id' => $this->agentInfo['id']])
            ->with('user')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }
}
