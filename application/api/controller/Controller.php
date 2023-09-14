<?php

namespace app\api\controller;

use app\api\model\User as UserModel;
use app\common\exception\BaseException;
use think\exception\HttpResponseException;
use think\Cache;
use think\cache\driver\Redis;
use think\Log;
use think\Lang;
use think\Loader;
use think\Request;
use think\Response;

/**
 * API控制器基类
 * Class BaseController
 * @package app\store\controller
 */
class Controller extends \think\Controller
{
    const JSON_SUCCESS_STATUS = 1;
    const JSON_ERROR_STATUS = 0;
    const API_TOKEN = "applet:api:token";
    protected $key = "QXvV0PetXXt716wqWNxFJo3z4gPGuS0fAcaT";
    //当前用户ID
    protected $uid;
    //当前用户信息
    protected $userInfo;
    //当前客户端语言
    protected $language;
    //token
    protected $token;
    /**
     * 默认响应输出类型,支持json/xml
     * @var string
     */
    protected $responseType = 'json';

    /**
     * API基类初始化
     * @throws BaseException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        //token
        $this->token = $this->request->header('token');
        //加载语言包配置
        $language = $this->request->header('language');
        $this->language = $this->request->header('language','english');
        $this->loadlang($language);
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name)
    {
        Lang::load(APP_PATH . $this->request->module() . '/lang/zh-cn/' . $name . '.php');
    }

    protected function verifySign($data)
    {
        // 验证参数中是否有签名
        if (!isset($data['sign']) || !$data['sign']) {
            $this->error('数据签名不存在');
        }
        $sign = $data['sign'];
        unset($data['sign']);
        $sign2 = $this->signMd5($data);
        Log::record('签名' . $sign2, 'info');

        if ($sign != $sign2) {
            $this->error('sign验证失败');
        }
    }

    /**
     * @param $mer_no
     * @param $mer_order_no
     * @param $mkey
     * @return string
     */
    protected function signMd5($params)
    {
        ksort($params);
        $params_str = '';
        foreach ($params as $k => $v) {
            if ($v) {
                $params_str = $params_str . $k . '=' . $v . '&';
            }
        }
        $params_str = $params_str . 'key=' . $this->key;
        Log::record('签名字符串' . $params_str, 'info');

        return md5($params_str);
    }



    /**
     * Notes:验证token
     * Date: 2021-01-30
     * Time: 15:51
     * @param $user_id
     * @param $token
     */
    public function verifyUser()
    {
        $redis = new Redis();
        $redis->handler()->select(1);
        if (!$this->token) {
            $this->errors(__('Please log in again'));
        }
        $tokens = $redis->handler()->get("token:" . $this->token);
        $tokenss = (new UserModel())->where('token', $this->token)->find();
        if (!$tokens || !$tokenss || empty($tokens)) {
            $this->errors(__('Please log in again'));
        }

        //用户信息
        $this->userInfo = $this->getCacheUser();
        $this->uid = ($this->getCacheUser())['id'];
    }

    public function gettoken()
    {
        return $this->token;
    }

    /**
     * Notes:获取缓存用户信息
     * Date: 2021-01-27
     * Time: 17:15
     * @param $user_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCacheUser()
    {
        if (!$this->token) {
            return false;
        }
        $redis = new Redis();
        $redis->handler()->select(1);
        $user_info = $redis->handler()->get("token:" . $this->token);
        if (!$user_info) {
            return false;
        }
        return json_decode($user_info, true);
    }

    /**
     * Notes:获取缓存用户信息2
     * Date: 2021-01-27
     * Time: 17:15
     * @param $user_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getCacheUser2($token = '')
    {
        if (!$token) {
            return false;
        }
        $redis = new Redis();
        $redis->handler()->select(1);
        $user_info = $redis->handler()->get("token:" . $token);
        if (!$user_info) {
            return false;
        }
        return json_decode($user_info, true);
    }

    /**
     * Notes:获取缓存用户信息
     * Date: 2021-01-27
     * Time: 17:15
     * @param $user_id
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userid()
    {
        $redis = new Redis();
        $redis->handler()->select(1);
        $user_info = $redis->handler()->get("token:" . $this->token);
        if (!$user_info) {
            return false;
        }
        $user_info = json_decode($user_info, true);
        return $user_info['id'];
    }

    /**
     * 更新缓存用户信息
     */
    public function updateCacheUser()
    {
        $redis = new Redis();
        $redis->handler()->select(1);
        $userinfo = (new UserModel())->where('token', $this->token)->find();
        $redis->handler()->set("token:" . $this->token, json_encode($userinfo), 60 * 60 * 24);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    protected function renderJson($code = self::JSON_SUCCESS_STATUS, $msg = '', $data = [])
    {
        return compact('code', 'msg', 'data');
    }

    /**
     * 返回operate successfullyjson
     * @param array $data
     * @param string|array $msg
     * @return array
     */
    protected function renderSuccess($data = [], $msg = 'success')
    {
        return $this->renderJson(self::JSON_SUCCESS_STATUS, $msg, $data);
    }

    /**
     * 返回operation failurejson
     * @param string $msg
     * @param array $data
     * @return array
     */
    protected function renderError($msg = 'error', $data = [])
    {
        return $this->renderJson(self::JSON_ERROR_STATUS, $msg, $data);
    }

    /**
     * 获取post数据 (数组)
     * @param $key
     * @return mixed
     */
    protected function postData($key = null)
    {
        return $this->request->post(is_null($key) ? '' : $key . '/a');
    }

    /**
     * operate successfully返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为1
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * operation failure返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function error($msg = '', $data = null, $code = 0, $type = null, array $header = [],$status = 0)
    {
        $this->result($msg, $data, $code, $type, $header,$status);
    }

    /**
     * operation failure返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function errorgroup($msg = '', $data = null, $code = 9, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

     /**
     * operation failure返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function errormoney($msg = '', $data = null, $code = 10, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * operation failure返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为3,token过期或不存在
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function errors($msg = '', $data = null, $code = 3, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed  $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型，支持json/xml/jsonp
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [],$status=0)
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
            'status' => $status
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 前置操作
     * @access protected
     * @param string $method  前置操作方法名
     * @param array  $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }

            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }

            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }
}
