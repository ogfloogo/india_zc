<?php

namespace app\api\controller;

use app\api\model\Level;
use app\api\model\Teamlevel;
use app\api\model\Usermoneylog;
use app\api\model\Userrobot;
use fast\Http;
use fast\Random;
use think\cache\driver\Redis;
use think\Log;
use think\Exception;
use think\Db;

/**
 * 示例接口
 */
class Demo extends Controller
{
    /**
     *第1个参数$text：二维码包含的内容，可以是链接、文字、json字符串等等；
     *第2个参数$outfile：默认为false，不生成文件，只将二维码图片返回输出；否则需要给出存放生成二维码图片的文件名及路径；
     *第3个参数$level：默认为L，这个参数可传递的值分别是L(QR_ECLEVEL_L，7%)、M(QR_ECLEVEL_M，15%)、Q(QR_ECLEVEL_Q，25%)、H(QR_ECLEVEL_H，30%)，这个参数控制二维码容错率，不同的参数表示二维码可被覆盖的区域百分比，也就是被覆盖的区域还能识别；
     *第4个参数$size：控制生成图片的大小，默认为4；
     *第5个参数$margin：控制生成二维码的空白区域大小；
     *
     */
    public function lineQrCode()
    {

        $urlstr = "https://www.baidu.com/";
        Vendor('PHPQrcode.phpqrcode');
        //生成二维码图片
        $errorCorrectionLevel = 'L';    //容错级别
        $matrixPointSize = 5;            //生成图片大小
        $img = mt_rand(0, 9999) . uniqid() . mt_rand(0, 9999) . mt_rand(0, 9999) . '.png';
        $file_path = 'qrcode/' . date('Ymd') . '/';
        $path = ROOT_PATH . 'public/storage/' . $file_path;
        if (!file_exists($path)) {
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($path, 0777, true);
        }
        $filename = $path . $img;
        $Qrcode = new \Qrcode;
        $Qrcode->png($urlstr, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
        $QR = $filename;                //已经生成的原始二维码图片文件
        $QR = imagecreatefromstring(file_get_contents($QR));
        //保存图片,销毁图形，释放内存
        if (!file_exists($filename)) {
            imagepng($QR, $filename);
            imagedestroy($QR);
        } else {
            imagedestroy($QR);
        }
        $this->success('线路邀请码', $file_path . $img);
    }


    public function ajax_add()
    {
        $num = 5;
        $data['createtime'] = time();
        $data['status'] = 0;
        $image_path = 'uploads/qrcode/';
        $file_path = ROOT_PATH . "public/" . $image_path;
        for ($i = 1; $i <= $num; $i++) {
            $id = db('qrcode')->insertGetId($data);
            $url = "https://www.baidu.com";
            $filename = $image_path . $id . '.png';
            $this->qrcode($url, $file_path, 3, 6);
            $image['qrcode'] = $image_path . $id . '.png';
            db('qrcode')->where(['id' => $id])->update($image);
        }

        return json(['code' => 1, 'msg' => '生成成功,请稍后']);
    }

    function qrcode($url, $filename, $level, $size)
    {
        Vendor('PHPQrcode.phpqrcode');
        //容错级别
        $errorCorrectionLevel = intval($level);
        //生成图片大小
        $matrixPointSize = intval($size);
        //生成二维码图片
        $Qrcode = new \Qrcode;
        //第二个参数false的意思是不生成图片文件，如果你写上‘picture.png’则会在根目录下生成一个png格式的图片文件
        $Qrcode->png($url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    /**
     * 机器人头像
     */
    public function robotimage()
    {
        $res = robotimage();
        $this->success('成功', $res);
    }

    public function namedata()
    {
        $list = (new Userrobot())->field('name')->select();
        $res = [];
        foreach ($list as $key => $value) {
            $res[] = $value['name'];
        }
        $this->success('成功', $res);
    }

    public function times()
    {
        echo date("Y-m-d H:i:s", time());
    }

    public function push()
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $redis->handler()->zAdd("zclc:sendlist", time() + 60 * 60 * 24 * 3, 61);
    }

    public function test2($user_id = 1, $commission = 100)
    {
        $redis = new Redis();
        $value = $user_id . "-" . $commission;
        $push = $redis->handler()->rpush("commissionlist", $value);
        if ($push !== false) {
            $this->success('入列成功');
        }
    }

    public function getbanklist()
    {
        $param = [
            'mchtId' => "6221730032029616",
            'appId' => "6221800729401520",
            'requestTime' => date("Y-m-d H:i:s", time()),
            'signType' => "MD5",
        ];
        $sign = $this->generateSign($param, "f589cde519a448aa8c1ff8c01c17375c");
        $param['sign'] = $sign;
        Log::mylog("提交参数", $param, "Shpay");
        $header[] = "Content-Type: application/json;charset=utf-8";
        $return_json = Http::get("https://openapi.shpays.com/v1/nigeria/trans/payBanks", $param);
        Log::mylog("提交参数", $return_json, "banklist");
        $this->success('入列成功', json_decode($return_json, true));
    }

    public function generateSign(array $params, $key)
    {
        ksort($params);
        $params_str = '';
        foreach ($params as $k => $v) {
            if ($v) {
                $params_str = $params_str . $k . '=' . $v . '&';
            }
        }
        $params_str = substr($params_str, 0, -1) . $key;
        Log::mylog('验签串', $params_str, 'Shpay');
        return strtoupper(md5($params_str));
    }

    /**
     * 添加机器人
     */
    public function addrobot()
    {
        $cpMer =  new Userrobot();
        $insert['field'] = ['name', 'avatar', 'buytime'];
        $return = [];
        for ($i = 0; $i < 500; $i++) {
            //随机电话号段
            $my_array = array("6", "7", "8", "9");
            $length = count($my_array) - 1;
            $hd = rand(0, $length);
            $begin = $my_array[$hd];
            $head = rand(6, 7);
            $a = rand(10, 99);
            $b = rand(100, 999);
            $f = mt_rand(1, 6);
            $hand = '/uploads/avatar.png';
            $return[$i]['name'] = $begin . $a . '****' . $b;
            $return[$i]['avatar'] = $hand;
            $return[$i]['buytime'] = $i;
        }
        $cpMer->saveAll($return);
        $this->success(__('The request is successful'), $return);

        $r = $cpMer->multiInsert($insert);
        echo 'ok';
    }

    /**
     * 打乱机器人下单顺序
     */
    public function updrobot()
    {
        $cpMer =  new Userrobot();
        $list = $cpMer->select();
        foreach ($list as $key => $value) {
            $buytime = Random::numeric(9);
            $cpMer->where('id', $value['id'])->update(['buytime' => intval($buytime)]);
        }
    }

    public function test()
    {
        (new Teamlevel())->teamUpgradeRechargeNum(5);
    }

    public function test3()
    {
        $redis = new Redis();
        $redis->handler()->select(6);

        $already_buy = $redis->handler()->zScore("zclc:financeordermoney", 6);
        var_dump($already_buy);
        exit;
    }

    public function test4()
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $redis->handler()->SADD("zclc:recharge200member", 14);
    }

    public function test5()
    {
        $redis = new Redis();
        $redis->handler()->select(6);
        $rs = $redis->handler()->SISMEMBER("zclc:recharge200member", 114);
        var_dump($rs);
    }

    public function test6()
    {
        $str = '{"amount":"100.00","referenceNo":"202304282124351896999450","transId":"C23688275010590005","appId":"9541898008529609","transactionDate":"2023-04-28 21:25:39","status":0}';
        $params = json_decode($str, true);
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }
        Log::mylog("签名串", $params_str, 'test6');
        $key = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCuFJaHZn8RdKiJhztiuKX3dCkImsDSKJizHBWdGTvKG8AWqgKa9A5dI0G2L7betXz7il2Y/AvaizgYU2a1Zr5jhdZqmw2Jan5OnQislQUAOczqu8vTbjWAprTDvbRGpkV+WJl+r7uI3GLmboR6UgxLfwWl6XbYaAcAtsE6bYZQ7QIDAQAB";
        $sign = "YhJH/ocRWzlLSE/TOheFNPXvm69a8bobhOiLGomWWbVtDhXIhOMdmnnxNKjQAFk5ANb4kkBC8XPeeMXyJA4PvlHV8SGUFWiCfBDfqFfUly6HBNPqj0brD8jMMkIwTiEADWyQaYEsnLVLbsRTr+up1w8U2MbM7H76Y7Xl018Gf+0=";
        $res = $this->public_key_decrypt($sign, $key);
        Log::mylog("解密", $res, 'test6');
        if ($params_str != $res) {
            $this->error("验证失败");
        }
        $this->success("验证成功");
    }

    function public_key_decrypt($data, $public_key)
    {
        $public_key = '-----BEGIN PUBLIC KEY-----' . "\n" . $public_key . "\n" . '-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key =  openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }

        return $crypto;
    }

    public function statistics()
    {
        $a = db()->query('select sum(amount) a from fa_finance_order where is_robot = 0 and f_id != 14 and status = 1 and user_id >28');
        $b = db()->query('select sum(amount) a from fa_finance_order where is_robot = 0 and f_id != 14 and status = 1');
        echo "28之后:{$a[0]['a']}   进行中:{$b[0]['a']}";
    }

    public function addmoney()
    {
        $array = db('testtest')->select();
        foreach ($array as $key => $value) {
            $res = db('user')->where(['mobile' => $value['mobile']])->field('id')->find();
            if (empty($res)) {
                continue;
            }
            Db::startTrans();
            try {
                $send = (new Usermoneylog())->moneyrecords($res['id'], 20, 'inc', 10, "活动系统赠送");
                if (!$send) {
                    Db::rollback();
                }
                //提交
                Db::commit();
                Log::mylog('赠送成功9.11', $value['mobile'], 'sendmoney');
            } catch (Exception $e) {
                Log::mylog('赠送失9.11', $value['mobile'], 'sendmoney');
                Db::rollback();
                $this->error(__('operation failure'));
            }
        }
    }
}
