<?php

namespace app\admin\controller\groupbuy;

use app\admin\model\User;
use app\admin\model\user\UserMoneyLog;
use app\api\model\Level;
use app\api\model\Usercategory;
use app\api\model\Usertotal;
use app\common\controller\Backend;
use app\common\library\Auth;
use Exception;
use think\cache\driver\Redis;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 用户弹窗设置管理
 *
 * @icon fa fa-circle-o
 */
class UserPopupMessage extends Backend
{

    /**
     * UserPopupMessage模型对象
     * @var \app\admin\model\groupbuy\UserPopupMessage
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\groupbuy\UserPopupMessage;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("readStatusList", $this->model->getReadStatusList());
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
            ->with('user')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 扣除余额
     */
    public function updatemoney($ids = null)
    {
        $user_id = $this->request->param('user_id', 0);

        $row = (new User())->where(['id' => $user_id])->find();
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
        $amount = $this->request->post('amount');
        if (empty($amount)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }

        if ($amount <= 0) {
            $this->error(__('Parameter %s can not be <=0', ''));
        }
        if (bccomp($row['money'], $amount) == (-1)) {
            $this->error(__('user money less than ' . $amount, ''));
        }
        $result = false;
        Db::startTrans();
        try {
            $data['money'] = Db::raw('money-' . $amount);
            $wc['id'] = $user_id;
            $wc['money'] = ['egt', $amount];
            $res = (new User())->where($wc)->update($data);
            if ($res) {
                $mold = 'dec';
                if (isset($_REQUEST['remark'])) {
                    $remark =  $_REQUEST['remark'] ?? "商品发货";
                } else {
                    $remark =  "商品发货";
                }
                $type = 22; //变动类型:1=充值,2=提现,3=邀请奖励,4=佣金收入,5=团购下单,6=拒绝提现,7=团购奖励,8=团长奖励,9=新用户注册奖励,10=管理员操作                

                $obj = (new UserMoneyLog());
                $obj->setTableName($user_id);
                $obj->create(['user_id' => $user_id, 'money' => abs($amount), 'before' => $row['money'], 'after' => bcsub($row['money'], $amount, 2), 'remark' => $remark, 'mold' => $mold, 'type' => $type]);

                $userinfo_new = (new User())->where('id', $row['id'])->find();

                $extra = [];
                $extra['old_user_info'] = $row;
                $extra['type'] = $type;
                $extra['time'] = time();
                $extra['user_id'] = $row['id'];
                //更新用户等级
                (new Level())->updatelevel($userinfo_new, $extra);
                //统计当日报表
                (new Usercategory())->addlog($type, $row['id'], abs($amount));
                //统计用户总报表
                (new Usertotal())->addlog($type, $row['id'], abs($amount));
                //刷新用户信息
                $userinfo_new = (new User())->where('id', $row['id'])->find();
                $redis = new Redis();
                $redis->handler()->select(1);
                $cache = $redis->handler()->get("token:" . $userinfo_new['token']);
                if ($cache) {
                    $redis->handler()->set("token:" . $userinfo_new['token'], json_encode($userinfo_new->toArray()), 60 * 60 * 24);
                }
                Db::commit();
                $result = true;
            } else {
                Db::rollback();
            }
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
        $params['read_status'] = 0;
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
        } catch (ValidateException | PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }
}
