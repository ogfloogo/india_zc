<?php

namespace app\api\controller;


use app\admin\model\financebuy\FinanceFaq;
use app\api\model\Teamlevel;

class Financeproject extends Controller
{
    public function confirm(){
        $this->verifyUser();
        $userInfo = $this->userInfo;
        $id = $this->request->param('id');
        if(!$id){
            $this->error(__('parameter error'));
        }
        $field = ['id','rate','type','day','user_min_buy','user_max_buy','fixed_amount','popularize','image','f_id','interest','capital','buy_level','is_new_hand'];
        $field2 = ['file1','file2','file1_name','file2_name'];
        $info = (new \app\api\model\Financeproject())->detail($id,$field);
        $info['can_buy'] = 1;
        //推广项目、体验项目都能购买  普通项目判断称号等级
        if($info['popularize'] == 0){
            if($userInfo['buy_level'] < $info['can_buy']){
                $info['can_buy'] = 0;
            }
        }
        $info['total_profit'] = bcmul($info['interest'],$info['day'],2);
        $fixed_amount = $info['popularize']==2?0:$info['fixed_amount'];
        $info['total_revenue'] = bcadd($info['total_profit'],$fixed_amount,2);
        if($info['type'] == 2){
            $info['daily_income'] = $info['interest'];
        }else{
            $info['daily_income'] = bcadd($info['capital'],$info['interest'],2);
        }
        $level = (new Teamlevel())->detail($info['buy_level']);
        $info['buy_level_name'] = $level['name']??'';
        $info['buy_level_image'] = !empty($level['image'])?format_image($level['image']):'';
        $files = (new \app\api\model\Finance())->detail($info['f_id'],$field2);
        $info['user_money'] = $userInfo['money'];
        $info['image'] = format_image($info['image']);
        $info['file'] = [
            'file1' => !empty($files['file1']) ? ishttps($files['file1']) : "",
            'file1_name' => $files['file1_name'],
            'file2' => !empty($files['file2']) ? ishttps($files['file2']) : "",
            'file2_name' => $files['file2_name'],
        ];
        $info['faq'] = (new FinanceFaq())->where(['status'=>1,'language'=>$this->language])->select();
        $this->success(__('The request is successful'),$info);
    }
}
