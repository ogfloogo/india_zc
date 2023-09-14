<?php

namespace app\admin\controller\forumsys;

use app\common\controller\Backend;
use app\admin\model\CacheModel;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 帖子评论管理
 *
 * @icon fa fa-circle-o
 */
class Forumcomment extends Backend
{

    /**
     * Forumcomment模型对象
     * @var \app\common\model\forumsys\Forumcomment
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\forumsys\Forumcomment;
        $this->view->assign("statusList", $this->model->getStatusList());
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
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('user')->visible(['mobile']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
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
            $result = $row->allowField(true)->save($params);
            Db::commit();
            $datainfo = $this->model->get($ids)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("commentlist", $datainfo['id'], $datainfo);
            // (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $datainfo['id'], $datainfo,  $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, 0, $datainfo['createtime']);
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
                $pid = $this->model->where(['id'=>$item['id']])->value('fid'); 
                $count += $item->delete();
                (new CacheModel())->delkeys('commentlist',$item->id);
                (new CacheModel())->delsetkeys('commentlist',$item->id, [], 0, 0, true);
                (new CacheModel())->delreckeys('commentlist',$item->id,[], $pid, 0, true);
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
            (new CacheModel())->setLevelCacheIncludeDel("commentlist", $datainfo['id'], $datainfo);
            // (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $datainfo['id'], $datainfo,  $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, 0, $datainfo['createtime']);
        }
        $this->success();
    }
    public function refuse($ids = null){
        $ids = explode(',',$ids);
        foreach ($ids as $v){
            $this->model->where(['id'=>$v])->update(['status'=>2]);
            $datainfo = $this->model->get($v)->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("commentlist", $datainfo['id'], $datainfo);
            // (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $datainfo['id'], $datainfo,  $datainfo['fid'], $datainfo['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $datainfo['id'], $datainfo, 0, $datainfo['createtime']);
        }
        $this->success();
    }
}
