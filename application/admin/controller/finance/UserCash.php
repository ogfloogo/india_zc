<?php

namespace app\admin\controller\finance;

use app\admin\model\finance\WithdrawChannel;
use app\api\model\Usercash as ModelUsercash;
use app\api\model\Usermoneylog;
use app\common\controller\Backend;
use app\pay\model\Boypay;
use app\pay\model\Cecopay;
use app\pay\model\Cloudpay;
use app\pay\model\Cloudsafepay;
use app\pay\model\Cloudsafepays;
use app\pay\model\Globalpay;
use app\pay\model\Gtrpay;
use app\pay\model\Gtrpays;
use app\pay\model\Metapay;
use app\pay\model\Mpay;
use app\pay\model\Nicepay;
use app\pay\model\Ppay;
use app\pay\model\Rpay;
use app\pay\model\Savepay;
use app\pay\model\Shpay;
use app\pay\model\Solpay;
use app\pay\model\Startpay;
use app\pay\model\Uzpay;
use app\pay\model\Wepay;
use app\pay\model\Wowpay;
use app\pay\model\Wowpays;
use app\pay\model\Xdpay;
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
class UserCash extends Backend
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
            ->with('user')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    public function pass()
    {
        $id = $this->request->param('id', 0);

        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($row['status'] != 0) {
            $this->error(__('状态不是待审核，无法操作'));
        }

        $params['status'] = 1;

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
        $this->success('通过(不提交)成功');
    }

    public function doPay()
    {
        $id = $this->request->param('id', 0);
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($row['status'] != 1) {
            $this->error(__('状态不是通过（未提交），无法操作'));
        }
        $withdrawChannel = (new WithdrawChannel())->where(['status' => 1, 'deletetime' => null])->order('weigh DESC')->find();
        if (empty($withdrawChannel)) {
            $this->error(__('通道未开启'));
        }
        if ($withdrawChannel['model'] == 'wepay') {
            $order = (new Wepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'wowpay'){
            $order = (new Wowpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'rpay'){
            $order = (new Rpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 0){
                $this->error($order['error']);
            }
            if ($order['data']['status'] != 1) {
                $this->error($order['data']['msg']);
            }
            $params['order_no'] = $order['data']['payoutId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'shpay'){
            $order = (new Shpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['success'] != true){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['result']['transNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'ppay'){
            $order = (new Ppay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != "SUCCESS"){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['ptOrderNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'startpay'){
            $order = (new Startpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            $params_info = json_decode($order['params'], true);
            if (empty($params_info)) {
                $this->error("提现失败");
            }
            if($params_info['status'] != 1){
                $this->error($order['message']);
            }
            $params['order_no'] = $params_info['system_ref'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'metapay'){
            $order = (new Metapay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['platRespCode'] != 0){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'boypay'){
            $order = (new Boypay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'savepay'){
            $order = (new Savepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['status'] != 'success'){
                $this->error($order['status_mes']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'mpay'){
            $order = (new Mpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != "SUCCESS"){
                $this->error($order['message']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'xdpay'){
            $order = (new Xdpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['success'] != true){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['platOrderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'gtrpay'){
            $order = (new Gtrpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'uzpay'){
            $order = (new Uzpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['respCode'] != "SUCCESS"){
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'gtrpays'){
            $order = (new Gtrpays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'wowpays'){
            $order = (new Wowpays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudsafepay'){
            $order = (new Cloudsafepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "P000") {
                $this->error($order['respMsg']);
            }
            $params['order_no'] = $order['orderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudsafepays'){
            $order = (new Cloudsafepays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "P000") {
                $this->error($order['respMsg']);
            }
            $params['order_no'] = $order['orderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'globalpay'){
            $order = (new Globalpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['status'] != "SUCCESS") {
                $this->error($order['err_msg']);
            }
            $params['order_no'] = $order['order_no'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudpay'){
            $order = (new Cloudpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['status'] != "1") {
                $this->error($order['message']);
            }
//            $params['order_no'] = $order['order_no'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'solpay'){
            $order = (new Solpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['platRespCode'] != "SUCCESS") {
                $this->error($order['platRespMessage']);
            }
            $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'nicepay'){
            $order = (new Nicepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['err'] != 0) {
                $this->error($order['platRespMessage']);
            }
            // $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cecopay'){
            $order = (new Cecopay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['errCode'] != 0) {
                $this->error($order['errMsg']);
            }
            // $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }

        $params['status'] = 2;

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
        $this->success('已提交成功');
    }

    public function passAndPay()
    {
        $id = $this->request->param('id', 0);
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($row['status'] != 0) {
            $this->error(__('状态不是待审核，无法操作'));
        }
        $withdrawChannel = (new WithdrawChannel())->where(['status' => 1, 'deletetime' => null])->order('weigh DESC')->find();
        if (empty($withdrawChannel)) {
            $this->error(__('通道未开启'));
        }
        if ($withdrawChannel['model'] == 'wepay') {
            $order = (new Wepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'wowpay'){
            $order = (new Wowpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'rpay'){
            $order = (new Rpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 0){
                $this->error($order['error']);
            }
            if ($order['data']['status'] != 1) {
                $this->error($order['data']['msg']);
            }
            $params['order_no'] = $order['data']['payoutId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'shpay'){
            $order = (new Shpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['success'] != true){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['result']['transNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'ppay'){
            $order = (new Ppay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != "SUCCESS"){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['ptOrderNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'startpay'){
            $order = (new Startpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            $params_info = json_decode($order['params'], true);
            if (empty($params_info)) {
                $this->error("提现失败");
            }
            if($params_info['status'] != 1){
                $this->error($order['message']);
            }
            $params['order_no'] = $params_info['system_ref'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'metapay'){
            $order = (new Metapay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['platRespCode'] != 0){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'boypay'){
            $order = (new Boypay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'savepay'){
            $order = (new Savepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['status'] != 'success'){
                $this->error($order['status_mes']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'mpay'){
            $order = (new Mpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != "SUCCESS"){
                $this->error($order['message']);
            }
            // $params['order_no'] = $order['transId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'xdpay'){
            $order = (new Xdpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['success'] != true){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['platOrderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'gtrpay'){
            $order = (new Gtrpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'uzpay'){
            $order = (new Uzpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['respCode'] != "SUCCESS"){
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'gtrpays'){
            $order = (new Gtrpays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if($order['code'] != 200){
                $this->error($order['msg']);
            }
            $params['order_no'] = $order['data']['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'wowpays'){
            $order = (new Wowpays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "SUCCESS") {
                $this->error($order['errorMsg']);
            }
            $params['order_no'] = $order['tradeNo'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudsafepay'){
            $order = (new Cloudsafepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "P000") {
                $this->error($order['respMsg']);
            }
            $params['order_no'] = $order['orderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudsafepays'){
            $order = (new Cloudsafepays())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['respCode'] != "P000") {
                $this->error($order['respMsg']);
            }
            $params['order_no'] = $order['orderId'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'globalpay'){
            $order = (new Globalpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['status'] != "SUCCESS") {
                $this->error($order['err_msg']);
            }
            $params['order_no'] = $order['order_no'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cloudpay'){
            $order = (new Cloudpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['status'] != "1") {
                $this->error($order['message']);
            }
//            $params['order_no'] = $order['order_no'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'solpay'){
            $order = (new Solpay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['platRespCode'] != "SUCCESS") {
                $this->error($order['platRespMessage']);
            }
            $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'nicepay'){
            $order = (new Nicepay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['err'] != 0) {
                $this->error($order['platRespMessage']);
            }
            // $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }elseif($withdrawChannel['model'] == 'cecopay'){
            $order = (new Cecopay())->withdraw($row,$withdrawChannel);
            $order = json_decode($order, true);
            if (empty($order)) {
                $this->error("提现失败");
            }
            if ($order['errCode'] != 0) {
                $this->error($order['errMsg']);
            }
            // $params['order_no'] = $order['platOrderNum'] ?? '';
            $params['channel'] = $withdrawChannel['name'];
        }

        $params['status'] = 2;

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
        $this->success('通过已提交成功');
    }

    public function reject()
    {
        $id = $this->request->param('id', 0);
        $row = $this->model->get($id);
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

        if ($row['status'] != 0 && $row['status'] != 1 && $row['status']!=4) {
            $this->error(__('状态不是待审核或者代付失败，无法操作'));
        }

        $params['status'] = 5;
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
            $money_result = (new Usermoneylog())->moneyrecords($row['user_id'], $row['price'], 'inc', 6);
            if(!$money_result){
                throw Exception("退款失败");
            }
            Db::commit();
        } catch (ValidateException | PDOException | Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success('已拒绝');
    }

    public function refreshOrderNo()
    {
        $id = $this->request->param('id', 0);
        $row = $this->model->get($id);
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
        // $params = $this->request->post('row/a');
        // if (empty($params)) {
        //     $this->error(__('Parameter %s can not be empty', ''));
        // }
        // $params = $this->preExcludeFields($params);

        if ($row['status'] != 4 ) {
            $this->error(__('状态不是代付失败，无法操作'));
        }

        $params['order_id'] = (new ModelUsercash())->createorder();
        $params['status'] = 0;
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
        $this->success('订单号已刷新成功');
    }

    public function mockpass()
    {
        $id = $this->request->param('id', 0);

        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($row['status'] != 0) {
            $this->error(__('状态不是待审核，无法操作'));
        }

        $params['status'] = 1;
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
        $this->success('模拟通过成功');
    }

}
