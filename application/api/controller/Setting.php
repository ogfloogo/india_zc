<?php

namespace app\api\controller;

use app\api\model\Agent;
use app\api\model\AgentPopups;
use app\api\model\Appversion;
use app\api\model\Contactus;
use app\api\model\Faq;
use app\api\model\Finance;
use app\api\model\Popups;
use app\api\model\Protocol;
use app\api\model\Recommend;
use app\api\model\Trustbusiness;
use app\api\model\UserPopupMessage;
use app\api\model\Userrecharge;
use think\cache\driver\Redis;
use think\Config;
use think\Log;
use app\api\model\Buildcache;


/**
 * 系统配置
 */
class Setting extends Controller
{

    /**
     * 配置列表
     */
    public function list()
    {
        $list = Config::get('site');
        $fields = ['user_protocol', 'quick_guide', 'private_policy', 'level_rule', 'team_rule', 'invite_rule', 'hiearning_url', 'pingtai_picture', 'protocol', 'privilege', 'finance_rule', 'withdraw_rule'];
        foreach ($fields as $field) {
            $newList[$field] = $list[$field];
        }
        $newList['cash_rule'] =  Config::get("site.cash_rule");

        $this->success(__('The request is successful'), $newList);
    }

    /**
     * 用户协议
     */
    public function userprotocol()
    {
        $list = Config::get("site.user_protocol");
        $this->success(__('The request is successful'), $list);
    }

    /**
     * 充值面额
     */
    public function amountlist()
    {
        $list = explode(',', Config::get("site.pay_amount"));
        $return = [];
        foreach ($list as $key => $value) {
            $res = explode('-', $value);
            $return[] = $res[0];
        }
        $this->success(__('The request is successful'), $return);
    }

    /**
     * 充值面额
     */
    public function amountlistnew()
    {
        $list = explode(',', Config::get("site.pay_amount"));
        $return = [];
        foreach ($list as $key => $value) {
            $res = explode('-', $value);
            $return[$key]['price'] = $res[0];
            $return[$key]['rate'] = $res[1];
            $return[$key]['givemoney'] = bcmul($res[0], $res[1] / 100, 2);
        }
        return $return;
    }


    /**
     * 版本
     */
    public function version()
    {
        $res = (new Appversion())->where('status', 1)->find();
        $res['update_url_and_file'] = format_image($res['update_url_and_file']);
        $res['update_url_wgt_file'] = format_image($res['update_url_wgt_file']);
        $this->success(__('The request is successful'), $res);
    }

    /**
     * af
     */
    public function getaflist()
    {
        $this->verifyUser();
        $list = (new Userrecharge())
            ->where('user_id', $this->uid)
            ->where('is_af', 0)
            ->where('status', 1)
            ->field('id,order_id,order_num,price,paytime')
            ->limit(10)
            ->select();
        if ($list) {
            foreach ($list as $key => $value) {
                (new Userrecharge())->where('id', $value['id'])->update(['is_af' => 1]);
            }
        }
        $this->success(__('The request is successful'), $list);
    }

    /**
     * af
     */
    public function setaf()
    {
        $ids = $this->request->post('ids');
        (new Userrecharge())->where('id', 'in', $ids)->update(['is_af' => 1]);
        $this->success(__('The request is successful'));
    }

    public function userpopup()
    {
        $this->verifyUser();
        $message_info = (new UserPopupMessage())->getLastMessage($this->uid);
        if ($message_info) {
            (new UserPopupMessage())->where(['id' => $message_info['id']])->update(['read_status' => 1, 'updatetime' => time()]);
        }
        $this->success(__('The request is successful'), $message_info);
    }

    /**
     * 各种联系方式
     * @return void
     */
    public function contactInformation()
    {
        $return = [
            'email' => config('site.about_email'),
            'address' => config('address.about_email'),
            'mobile' => config('site.about_mobile'),
            'whatsapp' => config('site.about_whatsapp'),
            'facebook' => config('site.about_facebook'),
            'twitter' => config('site.about_twitter'),
        ];
        $this->success(__('The request is successful'), $return);
    }

    /**
     * 公共接口
     */
    public function system()
    {
        $redis = new Redis();
        $f=1;
        $version = $this->request->header('version');
        //服务端版本号
        if (isset($version) && !empty($version)) { //审核版本
            $max_version = $redis->handler()->get('zclc:max_version');
            if($version != "-"){
                if ($max_version && $max_version < $version) {
                    $f=2;
                }
            }
        }
        //用户是否登录
        $checklogin = $this->getCacheUser();
        //底部Tab导航 英文
        if ($this->language == "english") {
            $list['tablist'][] = [
                "pagePath" => "/pages/home/home",
                "iconPath" => format_images("/static/image/tabbars/home-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/home-active.png"),
                "text" => "Home",
            ];
            // if($checklogin){
            //     if($checklogin['mobile'] == "968968968" || $checklogin['mobile'] == "111123456" || $checklogin['mobile'] == "333123456" || $checklogin['mobile'] == "15555555551" || $checklogin['mobile'] == "121212"){
            //         $list['tablist'][] = [
            //             "pagePath" => "/pages/home/plan",
            //             "iconPath" => format_images("/static/image/tabbars/plan-inactive.png"),
            //             "selectedIconPath" => format_images("/static/image/tabbars/plan-active.png"),
            //             "text" => "Plan",
            //         ];
            //     }
            // }
            if($f == 1){
                $list['tablist'][] = [
                    "pagePath" => "/pages/home/plan",
                    "iconPath" => format_images("/static/image/tabbars/plan-inactive.png"),
                    "selectedIconPath" => format_images("/static/image/tabbars/plan-active.png"),
                    "text" => "Plan",
                ];
            }
            // $list['tablist'][] = [
            //     "pagePath" => "/pages/project/list",
            //     "iconPath" => format_images("/static/image/tabbars/project-inactive.png"),
            //     "selectedIconPath" => format_images("/static/image/tabbars/project-active.png"),
            //     "text" => "Project",
            // ];
            $list['tablist'][] = [
                "pagePath" => "/pages/invite/reward",
                "iconPath" => format_images("/static/image/tabbars/bonus-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/bonus-active.png"),
                "text" => "Bonus",
            ];
            $list['tablist'][] = [
                "pagePath" => "/pages/team/team",
                "iconPath" => format_images("/static/image/tabbars/team-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/team-active.png"),
                "text" => "Team",
            ];
            $list['tablist'][] = [
                "pagePath" => "/pages/crowdfunding/my",
                "iconPath" => format_images("/static/image/tabbars/my-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/my-active.png"),
                "text" => "Mine",
            ];
        } elseif ($this->language == "india") {
            $list['tablist'][] = [
                "pagePath" => "/pages/home/home",
                "iconPath" => format_images("/static/image/tabbars/home-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/home-active.png"),
                "text" => "होमपेज",
            ];
            // $list['tablist'][] = [
            //     "pagePath" => "/pages/project/list",
            //     "iconPath" => format_images("/static/image/tabbars/project-inactive.png"),
            //     "selectedIconPath" => format_images("/static/image/tabbars/project-active.png"),
            //     "text" => "परियोजना",
            // ];
            if($f == 1){
                $list['tablist'][] = [
                    "pagePath" => "/pages/home/plan",
                    "iconPath" => format_images("/static/image/tabbars/plan-inactive.png"),
                    "selectedIconPath" => format_images("/static/image/tabbars/plan-active.png"),
                    "text" => "योजना",
                ];
            }
            $list['tablist'][] = [
                "pagePath" => "/pages/invite/reward",
                "iconPath" => format_images("/static/image/tabbars/bonus-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/bonus-active.png"),
                "text" => "बक्शीश",
            ];
            // $list['tablist'][] = [
            //     "pagePath" => "/pages/team/popular",
            //     "iconPath" => format_images("/static/image/tabbars/bonus-inactive.png"),
            //     "selectedIconPath" => format_images("/static/image/tabbars/bonus-active.png"),
            //     "text" => "बोनस",
            // ];
            $list['tablist'][] = [
                "pagePath" => "/pages/team/team",
                "iconPath" => format_images("/static/image/tabbars/team-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/team-active.png"),
                "text" => "बढ़ावा देना",
            ];
            $list['tablist'][] = [
                "pagePath" => "/pages/crowdfunding/my",
                "iconPath" => format_images("/static/image/tabbars/my-inactive.png"),
                "selectedIconPath" => format_images("/static/image/tabbars/my-active.png"),
                "text" => "व्यक्तिगत केंद्र",
            ];
        }

        //首页底部联系方式
        $list['footer'] = [
            'email' => config('site.about_email'),
            'address' => config('address.about_email'),
            'mobile' => config('site.about_mobile'),
            'whatsapp' => config('site.about_whatsapp'),
            'facebook' => config('site.about_facebook'),
            'twitter' => config('site.about_twitter'),
        ];
        $windows = json_decode(Config::get("site.windows"), true);
        foreach ($windows as $key => $value) {
            $windows[$key]['image'] = format_image($value['image']);
        }
        $list['windows'] = $windows;
        // if($version == "-"){
        //     $list['windows'] = [];
        // }
        //邀请奖励
        $list['invite_reward'] = Config::get("site.invite_reward");
        //邀请奖励比例
        $list['invite_rate'] = Config::get("site.invite_rate");
        if ($checklogin) {
            //邀请注册链接 http://localhost:8080/#/pages/login/logincurrentIndex == 'register'
            $list['invite_url'] = format_images('/#/pages/login/register?currentIndex=register&invite_code=' . $checklogin['invite_code']);
            //推广链接
            $list['promotion_url']  = format_images('/#/pages/login/register?currentIndex=register&invite_code=' . $checklogin['invite_code']);
        }
        //APP版本
        $app_version = $redis->handler()->Hgetall('zclc:max_version_info');
        if ($app_version) {
            $app_version['update_url_and_file'] = strstr($app_version['update_url_and_file'], 'http') ? $app_version['update_url_and_file'] : format_image($app_version['update_url_and_file']);
            $app_version['update_url_wgt_file'] = strstr($app_version['update_url_wgt_file'], 'http') ? $app_version['update_url_wgt_file'] : format_image($app_version['update_url_wgt_file']);
            if($version == "1.0.9"){
                $app_version['number'] = "1.0.9";
            }
        }

        // $list['app_version_1'] = $app_version;

        $count_key = 'update_count';
        $num = $redis->handler()->get($count_key);
        if (intval($num) >= 3) {
            // $app_version = [];
        } else {
            $redis->handler()->incr($count_key);
            $redis->handler()->expire($count_key, 30);
        }
        // $app_version['update_url_wgt_file'] = "https://vgroup.oss-ap-southeast-6.aliyuncs.com/vgroup115.wgt";
        $list['app_version'] = $app_version;
        // $list['experience_day'] = 3;
        $list['user_register_reward'] = Config::get('site.user_register_reward');
        //提现系统参数
        //提现手续费
        $list["withdraw_fee"] = Config::get("site.withdraw_fee");
        //最低提现金额
        $list["min_withdraw"] = Config::get("site.min_withdraw");
        //每日提现次数
        $list["daily_withdraw_number"] = Config::get("site.daily_withdraw_number");
        //充值面额
        $list['recharge_amount_list'] = $this->amountlistnew();
        //充值页底部文字描述
        $list['top_up_reamrk'] = Config::get("site.top_up_remark");
        //规则，协议
        $list['protocol_list'] = (new Protocol())->list($this->language);
        //首页合伙人
        $homefooter = json_decode(Config::get('site.homefooter'), true);
        foreach ($homefooter as $key => $value) {
            $homefooter[$key]['image'] = format_image($value['image']);
        }
        $list['homefooter'] = $homefooter;
        //客服链接地址
        $list['service_url'] = Config::get('site.service_url');
        //首页推广项目tab标签
        $tablist = explode('---', Config::get('site.hometab'));
        //首页联系我们
        $type1 = $type2 = $type3 = [];
        $contactus = (new Contactus())->list();
        foreach ($contactus as $value){
            if($value['type'] == 1){
                $type1[] = $value;
            }elseif($value['type'] == 2){
                $type2[] = $value;
            }else{
                $type3[] = $value;
            }
        }
        $list['contactus'] = $contactus;
        $list['contact_us'] = [
            'Contact us' => $type2,
        ];
        
        $list['homepage_contact'] = $type3;
        //首页弹窗
        $popup_windows = json_decode(Config::get('site.popup_windows'), true);
        foreach ($popup_windows as $key => $value) {
            $popup_windows[$key]['image'] = format_image($value['image']);
        }
        $list['popup_windows'] = $popup_windows;
        $list['contact_us_service'] = Config::get('site.contact_us_service');
        $list['hometab'] = (new Finance())->gettab($tablist);
        $list['turntable_rule'] = Config::get('site.turntable_rule');
        $list['flop_rule'] = Config::get('site.flop_rule');
        $list['navpicurl'] = Config::get('site.navpicurl');
        $this->success('', $list);
    }


    /**
     * 信托业务、合法的
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function trustBusiness()
    {
        $type = $this->request->param('type');
        if (!$type) {
            $this->error(__('parameter error'));
        }
        $return = [];
        if ($type == 1) {
            $list = Trustbusiness::where(['status' => 1, 'type' => ['in', [1, 2]], 'language' => $this->language])->order('weigh asc')->select();
            foreach ($list as $value) {
                if ($value['type'] == 1) {
                    $return['type1'][] = $value;
                } else {
                    $return['type2'][] = $value;
                }
            }
        } else {
            $list = Trustbusiness::where(['status' => 1, 'type' => 3, 'language' => $this->language])->order('weigh asc')->select();
            foreach ($list as $value) {
                $return['type1'][] = $value;
            }
        }

        $this->success(__('The request is successful'), $return);
    }


    /**
     * 合法的
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function legal()
    {
        $list = Trustbusiness::where(['status' => 1, 'type' => 3])->order('weigh asc')->select();
        $this->success(__('The request is successful'), $list);
    }

    public function homePage()
    {
        $field = ['id', 'name', 'content', 'list_content', 'image', 'popularize', 'status', 'money', 'endtime', 'username', 'userimage', 'tab'];
        $list = (new \app\api\model\Finance())->getfinanceyList($field);
        if (!$list) {
            $list = (new Buildcache())->buildFinanceCategoryCache();
            $list = (new \app\api\model\Finance())->getfinanceyList($field);
        }
        $return = [];
        $return['finance1'] = [];
        $return['finance2'] = [];
        $redis = new Redis();
        $redis->handler()->select(6);
        foreach ($list as &$value) {
            if ($value['tab']) {
                $tablist = explode('---', $value['tab']);
                $value['tab'] = !empty((new \app\api\model\Finance())->gettab($tablist)) ? (new \app\api\model\Finance())->gettab($tablist) : [];
            } else {
                $value['tab'] = [];
            }
            $value['userimage'] = format_image($value['userimage']);
            $buy_num = $redis->handler()->zScore("zclc:financeordernum", $value['id']);
            if (!$buy_num) {
                $buy_num = (new Buildcache())->buildFinance();
                $buy_num = $redis->handler()->zScore("zclc:financeordernum", $value['id']);
            }
            $value['buy_num'] = !$buy_num ? 0 : $buy_num; //支持人数
            if ($value['popularize'] == 1) {
                $project_info = (new \app\api\model\Financeproject())->list($value['id']);
                if (!$project_info) {
                    $project_info = (new Buildcache())->buildFinanceCache();
                    $project_info = (new \app\api\model\Financeproject())->list($value['id']);
                }
                $value['money'] = !empty($project_info[0]['fixed_amount']) ? $project_info[0]['fixed_amount'] : 0;
            }
            $value['image'] = format_image($value['image']);
            $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $value['id']); //已认购
            if (!$already_buy) {
                $already_buy = (new Buildcache())->buildFinance();
                $already_buy = $redis->handler()->zScore("zclc:financeordermoney", $value['id']); //已认购
            }
            $value['already_buy'] = !$already_buy ? 0 : $already_buy;
            if (!$value['money']) {
                $value['already_rate'] = 0;
            } else {
                $value['already_rate'] = round($value['already_buy'] / $value['money'] * 100, 2);
            }
            $value['surplus_day'] = ceil(($value['endtime'] - time()) / (60 * 60 * 24)); //剩余天数
            if ($value['popularize'] == 1) {
                $return['finance1'][] = $value;
            }
            if ($value['popularize'] == 0) {
                $return['finance2'][] = $value;
            }
        }
        $return['contactInformation'] = [
            'email' => config('site.about_email'),
            'address' => config('address.about_address'),
            'mobile' => config('site.about_mobile'),
            'whatsapp' => config('site.about_whatsapp'),
            'facebook' => config('site.about_facebook'),
            'twitter' => config('site.about_twitter'),
        ];
        $recommend = (new Recommend())->getRecommendList($this->language);
        $return['recommend'] = $recommend;
        $this->success(__('The request is successful'), $return);
    }

    public function homePageNew(){
        $redis = new Redis();
        $redis->handler()->select(6);
        $field = ['id', 'name', 'image', 'popularize', 'status', 'money', 'endtime', 'username'];
        $list = (new \app\api\model\Finance())->getfinanceyList($field);
        if (!$list) {
            $list = (new Buildcache())->buildFinanceCategoryCache();
            $list = (new \app\api\model\Finance())->getfinanceyList($field);
        }
        $return = [];
        $return['finance'] = [];
        $redis = new Redis();
        $redis->handler()->select(6);
        foreach ($list as &$value) {
            $value['image'] = format_image($value['image']);
            $return['finance'][] = $value;
        }
        $return['homepage_data'] = [
            'total_amount' => config('site.total_amount'),
            'total_number' => config('site.total_number'),
            'average_amount' => config('site.average_amount'),
            'average_income' => config('site.average_income'),
            'platform_bonus' => config('site.platform_bonus'),
            'average_bonus' => config('site.average_bonus'),
        ];
        $return['rank'] = [];
        $rank = $redis->handler()->ZREVRANGE('zclc:rank:history:crowdfunding_income_'.date('Y-m-d'),0,2,['withscores' => true]);
        foreach ($rank as $key => &$value){
            $user_info = explode('-',$key);
            $return['rank'][] = [
                'nickname' => substr($user_info[0], 0, 3) . '****' . substr($user_info[0], -3),
                'avatar' => format_image('/uploads/avatar.png'),
                'amount' => $value
            ];
        }
        for($i=0;$i<=2;$i++){
            if(empty($return['rank'][$i])){
                $return['rank'][$i] = [
                    'nickname' => '',
                    'avatar' => format_image('/uploads/avatar.png'),
                    'amount' => 0
                ];
            }
        }
        $this->success('The request is successful',$return);
    }
}
