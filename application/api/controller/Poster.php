<?php

namespace app\api\controller;

use app\api\model\Financeorder;
use app\api\model\Usertotal;
use Grafika\Color;
use Grafika\Grafika;
use think\Config;

include '../vendor/kosinix/grafika/src/Grafika/Grafika.php';
/**
 * 游戏控制器
 * Class User
 * @package app\api
 */
class Poster extends Controller
{
    /**
     * 生成海报
     * @return array
     * @throws \app\common\exception\BaseException
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function createposter()
    {
        $this->verifyUser();
        $userinfo = $this->userInfo;
        //生成邀请二维码
        $qrcode = $this->lineQrCode($userinfo['invite_code'],$userinfo['id']);
        //累计投资金额
        $invest_money_total = (new Financeorder())->where(['user_id'=>$userinfo['id']])->sum('amount');
        $usertotal = (new Usertotal())->where(['user_id'=>$userinfo['id']])->field('crowdfunding_income,invite_number,promotion_award,total_commission')->find();
        $return = [
            'qrcode' => $qrcode,
            'invest_money_total' => $invest_money_total,//累计投资金额
            'crowdfunding_income' => $usertotal['crowdfunding_income'],//累计投资收益
            'invite_number' => $usertotal['invite_number'],//累计邀请人数
            //            'promotion_award' => $usertotal['promotion_award'],//累计邀请激励金收益
            'promotion_award' => $usertotal['total_commission'],//换成了团队佣金
        ];
        $this->success(__('operation successfully'),$return);
    }

    function lineQrCode($invite_code,$user_id)
    {
        $urlstr = format_images('/#/pages/login/register?currentIndex=register&invite_code=' . $invite_code);
        Vendor('phpqrcode.phpqrcode');
        //生成二维码图片
        $errorCorrectionLevel = 'L';    //容错级别
        $matrixPointSize = 5;            //生成图片大小
        $img = $user_id . '.jpg';
        $file_path = 'inviteimg/';
        $path = ROOT_PATH . 'public/uploads/' . $file_path;
        if (!file_exists($path)) {
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($path, 0777, true);
        }
        $filename = $path . $img;
        $Qrcode = new \QRcode;
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
        return Config::get("image_url")."/uploads/inviteimg/" . $img;
    }
}
