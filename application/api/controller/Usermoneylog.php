<?php

namespace app\api\controller;

use app\api\model\Faq as ModelFaq;
use app\api\model\User;
use app\api\model\Usermoneylog as ModelUsermoneylog;
use think\Config;

/**
 * 资金记录
 */
class Usermoneylog extends Controller
{

    /**
     * 资金记录列表
     */
    public function list(){
        $this->verifyUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $date = $this->request->param('date');
        if(!$page||!$pageSize||!$date){
            $this->error(__('parameter error'));
        }
        $list = (new ModelUsermoneylog())->list($page,$pageSize,$this->uid,$this->language,$date);
        //已读未读
        db("user")->where(['id' => $this->uid])->update(['record_read' => 1]);
        (new User())->refresh($this->uid);
        $this->success(__('The request is successful'),$list);
    }

    public function moneytotal(){
        $this->verifyUser();
        $res = (new ModelUsermoneylog())->moneytotal($this->uid);
        $this->success(__('The request is successful'),$res);
    }

    public function listType(){
        $this->verifyUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $type = $this->request->param('type');
        if(!$page||!$pageSize||!$type){
            $this->error(__('parameter error'));
        }
        $list = (new ModelUsermoneylog())->listType($page,$pageSize,$this->uid,$type);

        $this->success(__('The request is successful'),$list);
    }
}
