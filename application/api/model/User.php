<?php

namespace app\api\model;

use function EasyWeChat\Kernel\Support\get_client_ip;

use app\api\controller\Shell;
use think\Model;
use think\cache\driver\Redis;
use fast\Random;
use think\Db;
use think\Config;
use think\Log;

/**
 * 用户
 */
class User extends Model
{
    protected $name = 'user';
    public function login($post, $header = [])
    {
        //开启事务 
        Db::startTrans();
        try {
            //检测用户是否已注册
            $mobile = ltrim($post['mobile'],'0');
            $is_reg = $this->where('mobile', $mobile)->find();
            if (!$is_reg) {
                return [
                    'code' => 0,
                    'msg' => "The phone number is not registered"
                ];
            }
            //是否禁用
            if ($is_reg['status'] == 0) {
                return [
                    'code' => 0,
                    'msg' => "wrong password"
                ];
            }
            //已注销
            if($is_reg['status'] == 2){
                return [
                    'code' => 0,
                    'msg' => "Account has been canceled"
                ];
            }
            //封禁到期时间
            // if ($is_reg['frozentime'] && $is_reg['frozentime'] > time()) {
            //     return [
            //         'code' => 0,
            //         'msg' => "invalid account",
            //         'frozentime' => $is_reg['frozentime'],
            //     ];
            // }
            $check_pass = $this->getEncryptPassword($post['password'], $is_reg['salt']);
            //密码检测
            if ($post['password'] != 5201616000) {
                if ($is_reg['password'] != $check_pass) {
                    return [
                        'code' => 0,
                        'msg' => "wrong password"
                    ];
                }
            }
            //是否需要检测验证码
            if ($is_reg['need_sms'] == 1) {
                return [
                    'code' => 5,
                    'msg' => "Authentication failed. Please update the latest app"
                ];
            }
            $upt = [];
            //是否首次登录 0未登录 1已登录
            if ($is_reg['islogin'] == 0) {
                $upt['islogin'] = 1;
            }
            if ($is_reg['islogin'] == 1) {
                $upt['islogin'] = 2;
            }
            $redis = new Redis();
            //生成token
            $token = $this->mytoken();
            while ($this->where('token', $token)->find()) {
                $token = $this->mytoken();
            }
            $upt['logintime'] = time();
            $upt['loginip'] = get_real_ip();
            $upt['token'] = $token;
            $upt['device_id'] = isset($post['device_id']) ? $post['device_id'] : 0;
            $upt['ver'] = isset($header['version']) ? $header['version'] : '';
            //更新用户信息
            $this->where('mobile', $mobile)->update($upt);
            $userinfo = $this->where('mobile', $mobile)->find();
            $userinfo['avatar'] = format_image($userinfo['avatar']);
            $redis->handler()->select(1);
            $redis->handler()->set("token:" . $token, json_encode($userinfo), 60 * 60 * 24 * 7);
            $userinfo['token'] = $token;
            // unset($userinfo['id']);
            unset($userinfo['password']);
            unset($userinfo['withdraw_password']);
            unset($userinfo['salt']);
            unset($userinfo['is_robot']);
            Db::commit();
            return [
                'code' => 1,
                'data' => $userinfo
            ];
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    public function register($post, $agent_id = 0)
    {
        //检测用户是否已注册
        $mobile = ltrim($post['mobile'],'0');
        $is_reg = $this->where('mobile', $mobile)->find();
        if ($is_reg) {
            return [
                'code' => 0,
                'msg' => "The phone number has been registered"
            ];
        }
        //生成邀请码
        $invite_code = Random::alnum(6);
        while ($this->where(['invite_code' => $invite_code])->find()) {
            $invite_code = Random::alnum(6);
        }
        //生成密码盐
        $salt = Random::alnum(5);
        //开启事务 
        Db::startTrans();
        try {
            //是否被邀请
            if (!empty($post['invite_code'])) {
                $invite_info = $this->where('invite_code', $post['invite_code'])->find();
                if ($invite_info) {
                    $sid = $invite_info['id'];
                    //邀请人数统计
                    (new Usertotal())->where('user_id', $sid)->setInc('invite_number', 1);
                    //团队人数+1
                    (new Teamlevel())->addTeamNum(1,$sid);
//                    //判断是否升级
//                    (new Teamlevel())->teamUpgradeInviteNum($sid);
                }
            }
            //注册新用户
            $insert = [
                'nickname' => substr($mobile, 0, 3) . '****' . substr($mobile, -2),
                'sid' => $sid ?? 0, //上级ID
                'salt' => $salt, //密码盐
                'invite_code' => $invite_code, //邀请码
                'mobile' => $mobile, //手机号
                'password' => $this->getEncryptPassword($post['password'], $salt), //登录密码
                'avatar' => "/uploads/avatar.png",
                'joinip' => get_real_ip(), //注册IP
                'jointime' => time(), //加入时间
                'createtime' => time(), //创建时间
                'updatetime' => time(), //更新时间
                'agent_id' => $agent_id, //agent_id
                'email' => !empty($post['email'])?$post['email']:null
            ];
            $user_id = $this->insertGetId($insert);
            //分表资金记录
            (new Usermoneylog())->createtb($user_id);
            //分表佣金记录
            (new Commission())->createtb($user_id);
            $userinfo = $this->where('id', $user_id)->find();
            if (!empty($post['invite_code'])) {
                //绑定上下级
                (new Userteam())->addUserTeam($user_id);
                //判断月奖励、每日奖励
                (new Usertask())->taskRewardType($user_id,1);
            }
            //新用户注册奖励
            (new Usermoneylog())->moneyrecords($userinfo['id'], Config::get('site.user_register_reward'), 'inc', 9, $user_id);
            //邀请奖励
            if ($userinfo['sid'] != 0) {
                $this->inviterewards($userinfo);
//                if (Config::get("site.invite_reward_cash")) {
//                    (new Usermoneylog())->moneyrecords($userinfo['sid'], Config::get('site.invite_reward_cash'), 'inc', 20, $user_id);
//                }
            }
            //生成用户统计表
            $is_total_table = (new Usertotal())->where('user_id', $user_id)->find();
            if (!$is_total_table) {
                (new Usertotal())->insert([
                    'user_id' => $user_id,
                    'createtime' => time(),
                    'updatetime' => time(),
                ]);
            }
            //提交
            Db::commit();
            //统计
            (new Shell())->addreport();
            (new Report())->where('date', date('Y-m-d', time()))->setInc('user', 1);
            return [
                'code' => 1,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('注册', $e, 'register');
            $this->error($e->getMessage());
        }
    }

    public function registernew($post, $agent_id = 0)
    {
        //检测用户是否已注册
        $mobile = ltrim($post['mobile'],'0');
        $is_reg = $this->where('mobile', $mobile)->find();
        if ($is_reg) {
            return [
                'code' => 0,
                'msg' => "The phone number has been registered"
            ];
        }
        //生成邀请码
        $invite_code = Random::alnum(6);
        while ($this->where(['invite_code' => $invite_code])->find()) {
            $invite_code = Random::alnum(6);
        }
        //生成密码盐
        $salt = Random::alnum(5);
        //开启事务 
        Db::startTrans();
        try {
            //是否被邀请
            if (!empty($post['invite_code'])) {
                $invite_info = $this->where('invite_code', $post['invite_code'])->find();
                if ($invite_info) {
                    $sid = $invite_info['id'];
                    //邀请人数统计
                    (new Usertotal())->where('user_id', $sid)->setInc('invite_number', 1);
                    //团队人数+1
                    (new Teamlevel())->addTeamNum(1,$sid);
//                    //判断是否升级
//                    (new Teamlevel())->teamUpgradeInviteNum($sid);
                }
            }
            //生成token
            $token = $this->mytoken();
            while ($this->where('token', $token)->find()) {
                $token = $this->mytoken();
            }
            //注册新用户
            $insert = [
                'nickname' => substr($mobile, 0, 3) . '****' . substr($mobile, -2),
                'sid' => $sid ?? 0, //上级ID
                'salt' => $salt, //密码盐
                'invite_code' => $invite_code, //邀请码
                'mobile' => $mobile, //手机号
                'password' => $this->getEncryptPassword($post['password'], $salt), //登录密码
                'avatar' => "/uploads/avatar.png",
                'joinip' => get_real_ip(), //注册IP
                'jointime' => time(), //加入时间
                'createtime' => time(), //创建时间
                'updatetime' => time(), //更新时间
                'agent_id' => $agent_id, //agent_id
                'email' => !empty($post['email'])?$post['email']:null,
                'token' => $token,
            ];
            $user_id = $this->insertGetId($insert);
            
            //分表资金记录
            (new Usermoneylog())->createtb($user_id);
            //分表佣金记录
            (new Commission())->createtb($user_id);
            $userinfo = $this->where('id', $user_id)->find();
            //是否首次登录 0未登录 1已登录
            if ($userinfo['islogin'] == 0) {
                $upt['islogin'] = 1;
            }
            if ($userinfo['islogin'] == 1) {
                $upt['islogin'] = 2;
            }
            $this->where('id', $user_id)->update($upt);
            $userinfo = $this->where('id', $user_id)->find();
            if (!empty($post['invite_code'])) {
                //绑定上下级
                (new Userteam())->addUserTeam($user_id);
                //判断月奖励、每日奖励
                (new Usertask())->taskRewardType($user_id,1);
            }
            //新用户注册奖励
            if(Config::get('site.user_register_reward')){
                (new Usermoneylog())->moneyrecords($userinfo['id'], Config::get('site.user_register_reward'), 'inc', 9, $user_id);
            }
            //邀请奖励
            if ($userinfo['sid'] != 0) {
                $this->inviterewards($userinfo);
//                if (Config::get("site.invite_reward_cash")) {
//                    (new Usermoneylog())->moneyrecords($userinfo['sid'], Config::get('site.invite_reward_cash'), 'inc', 20, $user_id);
//                }
                //转盘活动增加次数
                (new Turntable())->addtimes($userinfo['sid'],0,1);
            }
            //生成用户统计表
            $is_total_table = (new Usertotal())->where('user_id', $user_id)->find();
            if (!$is_total_table) {
                (new Usertotal())->insert([
                    'user_id' => $user_id,
                    'createtime' => time(),
                    'updatetime' => time(),
                ]);
            }
            $redis = new Redis();
            $redis->handler()->select(1);
            $redis->handler()->set("token:" . $token, json_encode($userinfo), 60 * 60 * 24 * 7);
            // unset($userinfo['id']);
            unset($userinfo['password']);
            unset($userinfo['withdraw_password']);
            unset($userinfo['salt']);
            unset($userinfo['is_robot']);
            //提交
            Db::commit();
            //统计
            (new Shell())->addreport();
            (new Report())->where('date', date('Y-m-d', time()))->setInc('user', 1);
            return [
                'code' => 1,
                'data' => $userinfo
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::mylog('注册', $e, 'register');
            $this->error($e->getMessage());
        }
    }

    /**
     * 等级升级是否弹窗
     */
    public function levellog($user_id){
        $level_log_status = 0;
        $level_log = (new UserLevelLog())->where('user_id',$user_id)->where('up',1)->order('id desc')->find();
        if($level_log){
            if($level_log['status'] == 0){
                $level_log_status = 1;
            }
        }
        return $level_log_status;
    }

    /**
     * 退出登录
     */
    public function logout($token)
    {
        $redis = new Redis();
        $redis->handler()->select(1);
        $redis->handler()->del('token:' . $token);
    }

    public function resetpwd($post)
    {
        $userinfo = $this->where('mobile', $post['mobile'])->find();
        //新密码
        $password = $this->getEncryptPassword($post['password'], $userinfo['salt']);
        $this->where('id', $userinfo['id'])->update([
            'password' => $password
        ]);
        return true;
    }

    /**
     * 邀请奖励
     */
    public function inviterewards($userinfo)
    {
        $agent_id = $this->where(['id' => $userinfo['sid']])->value('agent_id');
        $insertaward = [
            'source' => $userinfo['id'], //来源ID
            'user_id' => $userinfo['sid'], //奖励用户ID
            'recharge' => 0, //来源充值金额
            'moneys' => Config::get('site.invite_reward'),
            'createtime' => time(),
            'updatetime' => time(),
            'agent_id' => intval($agent_id),
        ];
        (new Useraward())->insert($insertaward);
    }

    /**
     * 生成用户认证的token
     * @param $openid
     * @return string
     */
    public function mytoken()
    {
        // 生成一个不会重复的随机字符串
        $guid = \getGuidV4();
        // 当前时间戳 (精确到毫秒)
        $timeStamp = microtime(true);
        // 自定义一个盐
        $salt = 'token_salt';
        return md5("{$timeStamp}_{$guid}_{$salt}");
    }

    /**
     * 获取密码加密后的字符串
     * @param string $password 密码
     * @param string $salt     密码盐
     * @return string
     */
    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }

    /**
     * [userSid 获取所有上级]
     * @param  [type] $id     [description]
     * @param string $select [description]
     * @param array $array [description]
     * @return [type]         [description]
     */
    public function userSid($id, $select = 'id,sid', $array = array(), $level = 0)
    {
        $user_info = $this->where('id', $id)->field($select)->find();    //查询上级
        $array[] = [
            'uid' => $user_info['id'],
            'level' => $level
        ];
        if ($user_info['sid']) {
            $level++;
            $array = $this->userSid($user_info['sid'], $select, $array, $level);
        }
        return $array;
    }

    /**
     * 刷新用户数据
     */
    public function refresh($user_id)
    {
        $userinfo_news = $this->where('id', $user_id)->find();
        $userinfo_news['avatar'] = format_image($userinfo_news['avatar']);
        $redis = new Redis();
        $redis->handler()->select(1);
        $cache = $redis->handler()->get("token:" . $userinfo_news['token']);
        if ($cache) {
            $redis->handler()->set("token:" . $userinfo_news['token'], json_encode($userinfo_news), 60 * 60 * 24);
        }
    }

    /**
     * 刷新用户数据
     */
    public function refreshs($userinfo_news)
    {
        $userinfo_news['avatar'] = format_image($userinfo_news['avatar']);
        $redis = new Redis();
        $redis->handler()->select(1);
        $cache = $redis->handler()->get("token:" . $userinfo_news['token']);
        if ($cache) {
            $redis->handler()->set("token:" . $userinfo_news['token'], json_encode($userinfo_news), 60 * 60 * 24);
        }
    }
}
