<?php

namespace app\api\controller;

use app\api\model\Usercategory;
use app\api\model\Usertotal;
use think\cache\driver\Redis;

class Rank extends Controller
{

    public function list()
    {
        $this->verifyUser();
        $userinfo = $this->userInfo;
        $fund_type = $this->request->post("fund_type",1); //资金类型
        $type = $this->request->post("type",1); //1月排行 2总排行
        $redis = new Redis();
        $redis->handler()->select(6);
        switch ($fund_type){
            case 1:
                if($type == 1){
                    $key = 'zclc:rank:month:crowdfunding_income_'.date('Y-m');
                }else{
                    $key = 'zclc:rank:history:crowdfunding_income_'.date('Y-m-d');
                }
                $field = 'crowdfunding_income';
                break;
            case 2:
                if($type == 1){
                    $key = 'zclc:rank:month:promotion_award_'.date('Y-m');
                }else{
                    $key = 'zclc:rank:history:promotion_award_'.date('Y-m-d');
                }
                $field = 'promotion_award';
                break;
            case 3:
                if($type == 1){
                    $key = 'zclc:rank:month:total_commission_'.date('Y-m');
                }else{
                    $key = 'zclc:rank:history:total_commission_'.date('Y-m-d');
                }
                $field = 'total_commission';
                break;
        }
        $return['rank'] = [];
        $rank = $redis->handler()->ZREVRANGE($key,0,19,['withscores' => true]);
        foreach ($rank as $keys => &$value){
            $user_info = explode('-',$keys);
            $return['rank'][] = [
                'nickname' => substr($user_info[0], 0, 3) . '****' . substr($user_info[0], -3),
                'avatar' => format_image('/uploads/avatar.png'),
                'amount' => $value
            ];
        }
        $myrank = $redis->handler()->ZREVRANK($key,$userinfo['mobile']);

        if($myrank){
            $myrank = [
                'rank'=> $myrank + 1,
                'amount'=> $redis->handler()->ZSCORE($key,$userinfo['mobile'])
            ];
        }else{
            if($type == 1){
                $amount = (new Usercategory())->where(['user_id'=>$userinfo['id']])->whereTime('createtime','last month')->sum($field);
            }else{
                $amount = (new Usertotal())->where(['user_id'=>$userinfo['id']])->value($field);
            }
            $myrank = [
                'rank'=> '1000+',
                'amount'=> $amount
            ];
        }
        $myrank['nickname'] = substr($userinfo['mobile'], 0, 3) . '****' . substr($userinfo['mobile'], -3);
        $myrank['avatar'] = format_image('/uploads/avatar.png');
        $return['myrank'] = $myrank;
        $this->success('The request is successful',$return);
    }

    public function fundtype()
    {
        $fund_type1 = json_decode(config('site.fund_type1'),true);
        $fund_type2 = json_decode(config('site.fund_type2'),true);
        $fund_type3 = json_decode(config('site.fund_type3'),true);
        if($fund_type1['status'] == 1){
            $return[] = $fund_type1;
        }
        if($fund_type2['status'] == 1){
            $return[] = $fund_type2;
        }
        if($fund_type3['status'] == 1){
            $return[] = $fund_type3;
        }
        $this->success('The request is successful',$return);
    }
}
