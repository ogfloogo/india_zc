<?php

namespace app\admin\controller\order;

use app\admin\model\groupbuy\Goods;
use app\admin\model\order\Order;
use app\admin\model\user\UserAddress;
use app\admin\model\user\UserWarehouse;
use app\common\controller\Backend;
use Exception;
use think\cache\driver\Redis;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 物流订单管理
 *
 * @icon fa fa-circle-o
 */
class LogisticsOrder extends Backend
{

    /**
     * LogisticsOrder模型对象
     * @var \app\admin\model\order\LogisticsOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\LogisticsOrder;
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
            ->with('warehouse,address,order,user')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
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
            $ware_info = (new UserWarehouse())->where(['id'=>$row['wid']])->find();
            $address_info = (new UserAddress())->where(['id'=>$row['address_id']])->find();
            $address_info['address_string'] = $address_info['address'].','.$address_info['village'].','.$address_info['county'].','.$address_info['city'].','.$address_info['province'];
            $address_info['good_name'] = (new Goods())->where(['id'=>$ware_info['good_id']])->value('name');
            $address_info['order_price'] = (new Order())->where(['id'=>$ware_info['order_id']])->value('amount');
            $this->view->assign('address_info', $address_info);
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
