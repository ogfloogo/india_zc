<?php

namespace app\admin\controller\forumsys;

use app\common\controller\Backend;
use app\admin\model\CacheModel;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 帖子管理
 *
 * @icon fa fa-circle-o
 */
class Forum extends Backend
{

    /**
     * Forum模型对象
     * @var \app\common\model\forumsys\Forum
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\forumsys\Forum;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isTopList", $this->model->getIsTopList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->with(['user', 'channel'])
                ->where($where)
                ->order('id desc')
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('user')->visible(['mobile']);
                $row->getRelation('channel')->visible(['name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
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
            $params['createtime'] = time();
            $params['updatetime'] = time();
            $result = $this->model->insertGetId($params);
            Db::commit();
            $res = $this->model->get($result)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $result, $res);
            // (new CacheModel())->setSortedSetCache("forumlist", $result, $res, $res['pid'], $res['is_top']);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $result, $res,  $res['pid'], $res['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $result, $res, 0, $res['is_top']);
        } catch (ValidateException | PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
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
            $params['createtime'] = strtotime($params['createtime']);
            $result = $row->allowField(true)->save($params);
            Db::commit();
            $datainfo = $this->model->get($ids)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $datainfo['id'], $datainfo);
            // (new CacheModel())->setSortedSetCache("forumlist", $datainfo['id'], $datainfo, $datainfo['pid'], $datainfo['is_top']);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $datainfo['id'], $datainfo,  $datainfo['pid'], $datainfo['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $datainfo['id'], $datainfo, 0, $datainfo['is_top']);
        } catch (ValidateException | PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 删除
     *
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = $this->model->where($pk, 'in', $ids)->select();

        $count = 0;
        Db::startTrans();
        try {
            foreach ($list as $item) {
                $pid = $this->model->where(['id'=>$item['id']])->value('pid'); 
                $count += $item->delete();
                (new CacheModel())->delkeys('forumlist',$item->id);
                (new CacheModel())->delsetkeys('forumlist',$item->id, [], 0, 0, true);
                (new CacheModel())->delreckeys('forumlist',$item->id,[], $pid, 0, true);
            }
            Db::commit();
        } catch (PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    public function pass($ids = null){
        $ids = explode(',',$ids);
        foreach ($ids as $v){
            $this->model->where(['id'=>$v])->update(['status'=>1]);
            $datainfo = $this->model->get($v)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $datainfo['id'], $datainfo);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $datainfo['id'], $datainfo,  $datainfo['pid'], $datainfo['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $datainfo['id'], $datainfo, 0, $datainfo['is_top']);
        }
        $this->success();
    }
    public function refuse($ids = null){
        $ids = explode(',',$ids);
        foreach ($ids as $v){
            $this->model->where(['id'=>$v])->update(['status'=>2]);
            $datainfo = $this->model->get($v)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $datainfo['id'], $datainfo);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $datainfo['id'], $datainfo,  $datainfo['pid'], $datainfo['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $datainfo['id'], $datainfo, 0, $datainfo['is_top']);
        }
        $this->success();
    }
}
