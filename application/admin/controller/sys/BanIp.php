<?php

namespace app\admin\controller\sys;

use app\admin\model\User;
use app\api\model\User as ModelUser;
use app\common\controller\Backend;
use Exception;
use think\cache\driver\Redis;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * ip黑名单
 *
 * @icon fa fa-circle-o
 */
class BanIp extends Backend
{

    /**
     * BanIp模型对象
     * @var \app\admin\model\sys\BanIp
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\sys\BanIp;

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
            $sum = $this->disableUser($params['ip']);
            $params['last_ban_num'] = $sum;
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
            $this->model->setSetCache($params['ip']);
        } catch (ValidateException | PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    protected function disableUser($ip){
        $wc = [];
        $wc['loginip'] = trim($ip);
        $wc['status'] = 1;
        $list = (new User())->where($wc)->field('id,token')->select();
        $userIds = [];
        foreach($list as $userItem){
            $id = $userItem['id'];
            $token = $userItem['token'];
            if ($token) {
                (new ModelUser())->logout($token);
            }
            $userIds[] = $id;
        }
        $wc = [];
        $wc['joinip'] = trim($ip);
        $wc['status'] = 1;
        if(count($userIds)){
            $wc['id'] = ['NOT IN',$userIds];
        }
        $list = (new User())->where($wc)->field('id,token')->select();
        foreach($list as $userItem){
            $id = $userItem['id'];
            $token = $userItem['token'];
            if ($token) {
                (new ModelUser())->logout($token);
            }
            if(!in_array($id,$userIds)){
                $userIds[] = $id;
            }
        }
        if(count($userIds)){
            (new User())->where(['id'=>['IN',$userIds]])->update(['status'=>0]);
        }
        return count($userIds);
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
                $count += $item->delete();
                $this->model->setSetCache($item['ip'],true);
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


}
