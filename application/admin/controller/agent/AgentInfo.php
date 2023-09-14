<?php

namespace app\admin\controller\agent;

use app\admin\controller\general\Config;
use app\admin\model\sys\Agent;
use app\common\controller\Backend;
use think\Config as ThinkConfig;

/**
 * 代理管理
 *
 * @icon fa fa-circle-o
 */
class AgentInfo extends Base
{

    /**
     * AgentInfo模型对象
     * @var \app\admin\model\agent\AgentInfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\agent\AgentInfo;
        $this->view->assign("statusList", $this->model->getStatusList());
    }


    public function info()
    {        
        $row['windows'] = empty($this->agentInfo['windows']) ? ThinkConfig::get("site.windows") : $this->agentInfo['windows'];
        $row['service_url'] = empty($this->agentInfo['service_url']) ? ThinkConfig::get("site.service_url") : $this->agentInfo['service_url'];
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 更新个人信息
     */
    public function update()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $params = array_filter(array_intersect_key(
                $params,
                array_flip(array('windows', 'service_url'))
            ));
            unset($v);

            $exist = Agent::where(['id' => $this->agentInfo['id']])->find();
            if (!$exist) {
                $this->error(__("Agent no exists"));
            }
            if ($params) {
                $admin = Agent::get($this->agentInfo['id']);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                $this->success();
            }
            $this->error();
        }
        return;
    }
}
