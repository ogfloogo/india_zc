<?php

namespace app\admin\controller\financebuy;

use app\api\model\Userrobot;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\admin\model\CacheModel;

/**
 * 基金活动管理
 *
 * @icon fa fa-circle-o
 */
class Finance extends Backend
{

    /**
     * Finance模型对象
     * @var \app\admin\model\financebuy\Finance
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\financebuy\Finance;
        $this->view->assign("popularizeList", $this->model->getPopularizeList());
        $this->view->assign("robotStatusList", $this->model->getRobotStatusList());
        $this->view->assign("autoOpenList", $this->model->getAutoOpenList());
        $this->view->assign("statusList", $this->model->getStatusList());
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
            ->field('id,name,zc_day,money,popularize,rotation_images,username,userimage,status,createtime,endtime,weigh')
            ->where($where)
            ->order('status desc,weigh asc')
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $params['endtime'] = time() + $params['zc_day'] * 60 * 60 * 24;
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
            //redis
            (new CacheModel())->setLevelCache("finance",$this->model->id, $params);
            (new CacheModel())->setSortedSetCache("finance",$this->model->id, $params, 0, $params['weigh']);
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $datainfo = $this->model->get($ids);
        $params['endtime'] = $datainfo['createtime'] + $params['zc_day'] * 60 * 60 * 24;
        $params = $this->preExcludeFields($params);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
            $datainfo = $this->model->get($ids)->toArray();
            (new CacheModel())->setLevelCache("finance",$ids, $datainfo);
            (new CacheModel())->setSortedSetCache("finance",$ids, $datainfo, 0, $datainfo['weigh']);
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 项目、方案一同下架
     * @param $ids
     * @return void
     * @throws \think\exception\DbException
     */
    public function ban($ids = null){
        $row = $this->model->get($ids);
        if(!$row){
            $this->error('记录不存在');
        }
        $row->status = 0;
        $rs = $row->save();
        $datainfo = $this->model->get($ids)->toArray();
        (new CacheModel())->setLevelCache("finance",$ids, $datainfo);
        (new CacheModel())->setSortedSetCache("finance",$ids, $datainfo, 0, $datainfo['weigh']);
        (new \app\admin\model\financebuy\FinanceProject())->where(['f_id'=>$ids])->update(['status'=>0,'robot_status'=>0]);
        $list =  Db::table('fa_finance_project')->where(['f_id' => $ids])->select();
        foreach ($list as $item) {
            (new CacheModel())->setLevelCacheIncludeDel("financeproject",$item['id'], $item);
            (new CacheModel())->setSortedSetCache("financeproject",$item['id'], $item, $item['f_id'], $item['weigh']);
            (new CacheModel())->setRecommendSortedSetCache("financeproject",$item['id'], $item,  $item['f_id'], $item['weigh']);
            (new CacheModel())->setSortedSetCache("financeproject",$item['id'], $item, 0, $item['weigh']);
        }
        $this->success();
    }
}
