<?php

namespace app\admin\controller\financebuy;

use app\admin\model\CacheModel;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\api\model\Financeorder;

/**
 * 众筹项目配置
 *
 * @icon fa fa-circle-o
 */
class FinanceProject extends Backend
{

    /**
     * FinanceProject模型对象
     * @var \app\admin\model\financebuy\FinanceProject
     */
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\financebuy\FinanceProject;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("popularizeList", $this->model->getPopularizeList());
        $this->view->assign("robotStatusList", $this->model->getRobotStatusList());
        $this->view->assign("getProjecttypeList", $this->model->getProjecttypeList());
        $this->view->assign("getRecommendList", $this->model->getRecommendList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function index($f_id = null,$popularize = 0)
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->field('id,name,buy_level,image,fixed_amount,rate,day,type,capital,interest,f_id,status,popularize,createtime,weigh,robot_status,robot_addorder_time_start,robot_addorder_time_end,is_new_hand,label_ids')
                ->with(['level'])
                ->where($where)
                ->order('weigh asc')
                ->paginate($limit);
            foreach ($list as &$value){
                $label_name = (new \app\api\model\Financeproject())->getLabel($value['label_ids']);
                $value['label_name'] = implode(',',$label_name);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        $this->assignconfig('f_id',$f_id);
        $this->assignconfig('popularize',$popularize);
        return $this->view->fetch();
    }

    public function add($f_id = null,$popularize = null)
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
            $params['f_id'] = $f_id;
            $params['popularize'] = $popularize;
//            $exist = $this->model->where(['f_id'=>$f_id,'buy_level'=>$params['buy_level']])->count();
//            if($exist){
//                Db::rollback();
//                $this->error('称号等级已经存在');
//            }
//            if($popularize == 1){
//                if($params['fixed_amount'] == 0){
//                    Db::rollback();
//                    $this->error('推广项目只能是固定金额');
//                }
//                $count = $this->model->where(['f_id'=>$f_id])->count();
//                if($count >= 1){
//                    Db::rollback();
//                    $this->error('推广项目只能有一种方案');
//                }
//            }
            $result = $this->model->insertGetId($params);
            Db::commit();
            (new CacheModel())->setLevelCacheIncludeDel("financeproject",$result, $this->model->get($result)->toArray());
            (new CacheModel())->setSortedSetCache("financeproject",$result, $params, $params['f_id'], $params['weigh']);
            (new CacheModel())->setRecommendSortedSetCache("financeproject",$result, $params,  $params['f_id'], $params['weigh']);
            (new CacheModel())->setSortedSetCache("financeproject",$result, $params, 0, $params['weigh']);
            if ($params['robot_status']) {
                (new Financeorder())->openrobot($result, $params['robot_addorder_time_start'], $params['robot_addorder_time_end'], $params['user_min_buy'], $params['user_max_buy'],$params['fixed_amount']);
            } else {
                (new Financeorder())->closerobot($result);
            }
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
        $old_category = $row['f_id'];
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
//            if($row['popularize'] == 1){
//                if($params['fixed_amount'] == 0){
//                    Db::rollback();
//                    $this->error('推广项目只能是固定金额');
//                }
//            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
            $datainfo = $this->model->get($ids)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("financeproject",$ids, $row->toArray());
            (new CacheModel())->setSortedSetCache("financeproject",$ids, $datainfo, $datainfo['f_id'], $datainfo['weigh']);
            (new CacheModel())->setRecommendSortedSetCache("financeproject",$ids, $datainfo,  $datainfo['f_id'], $datainfo['weigh']);
            if ($old_category != $datainfo['f_id']) {
                (new CacheModel())->setSortedSetCache("financeproject",$ids, $datainfo, $old_category, $datainfo['weigh'], true);
                (new CacheModel())->setRecommendSortedSetCache("financeproject",$ids, $datainfo,  $old_category, $datainfo['weigh'], true);
            }
            (new CacheModel())->setSortedSetCache("financeproject",$ids, $datainfo, 0, $datainfo['weigh']);
            if ($params['robot_status']) {
                (new Financeorder())->openrobot($ids, $datainfo['robot_addorder_time_start'], $datainfo['robot_addorder_time_end'], $datainfo['user_min_buy'], $datainfo['user_max_buy'],$params['fixed_amount']);
            } else {
                (new Financeorder())->closerobot($ids);
            }
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }
}
