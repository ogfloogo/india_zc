<?php

namespace app\api\controller;

use app\admin\model\CacheModel;
use app\api\model\User;
use app\common\model\forumsys\Forum as ForumsysForum;
use app\common\model\forumsys\Forumcomment;
use think\cache\driver\Redis;
use think\Log;
use think\Exception;
use think\Db;

/**
 * 论坛
 */
class Forum extends Controller
{

    /**
     * 频道列表
     */
    public function channellist()
    {
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:forumchannel:set:0', '-inf', '+inf', ['withscores' => true]);
        $left = [];
        foreach ($categorylist as $k => $v) {
            $reward = $redis->handler()->hMget("zclc:forumchannel:" . intval($k), ['id', 'name', 'type', 'weigh', 'status']);
            if ($reward['status'] == 1) {
                $left[$k]['id'] = intval($k);
                $left[$k]['name'] = $reward['name'];
                $left[$k]['weigh'] = $reward['weigh'];
            }
        }
        $edit = array_column($left, 'weigh');
        array_multisort($edit, SORT_DESC, $left);
        array_splice($left, 1, 0, [['id' => 'new', 'name' => "Latest"]]); //最新
        $this->success("The request is successful", $left ?? []);
    }

    /**
     * 用户频道选择
     */
    public function userchannellist()
    {
        $redis = new Redis();
        $categorylist = $redis->handler()->ZRANGEBYSCORE('zclc:forumchannel:set:0', '-inf', '+inf', ['withscores' => true]);
        $left = [];
        foreach ($categorylist as $k => $v) {
            $reward = $redis->handler()->hMget("zclc:forumchannel:" . intval($k), ['id', 'name', 'type', 'weigh', 'status']);
            if ($reward['status'] == 1 && $reward['type'] == 0) {
                $left[$k]['id'] = intval($k);
                $left[$k]['name'] = $reward['name'];
                $left[$k]['weigh'] = $reward['weigh'];
            }
        }
        $edit = array_column($left, 'weigh');
        array_multisort($edit, SORT_DESC, $left);
        $this->success("The request is successful", $left ?? []);
    }
    /**
     * 帖子列表
     */
    public function list()
    {
        $user = $this->getCacheUser();
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $pid = $this->request->param('pid', "new");
        $where = [];
        if ($pid != "new") {
            $where['pid'] = $pid;
        }
        $list = (new ForumsysForum())
            ->where($where)
            ->where(['status' => 1, 'deletetime' => null])
            ->field('id')
            ->order('is_top desc')
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        $field1 = ['content', 'image', 'createtime', 'is_top', 'user_id', 'status'];
        $field2 = ['content', 'createtime', 'user_id'];
        foreach ($list as &$value) {
            $forum_info = $this->forumdetail($value['id'], $field1);
            if ($forum_info['image']) {
                $value['image'] = explode(',', format_image($forum_info['image']));
            } else {
                $value['image'] = [];
            }
            $value['createtime'] = $forum_info['createtime'];
            $value['content'] = $forum_info['content'];
            $value['is_top'] = $forum_info['is_top'];
            $value['user_id'] = $forum_info['user_id'];
            $value['status'] = $forum_info['status'];
            if ($forum_info['user_id'] != 0) {
                $user_info = (new User())->where(['id' => $forum_info['user_id']])->field('nickname,avatar')->find();
                $value['nickname'] = $user_info['nickname'];
                $value['avatar'] = format_image($user_info['avatar']);
            } else {
                $value['nickname'] = "ALPHA";
                $value['avatar'] = format_image("/uploads/20230917/53d1deac105c1d6362fe42d2ce91a9b7.png");
            }
            $commentlist = (new Forumcomment())->where(['fid' => $value['id'], 'status' => 1])->field('id')->order('createtime desc')->limit(6)->select();
            foreach ($commentlist as &$v) {
                $comment_info = $this->commentdetail($v['id'], $field2);
                $v['content'] = $comment_info['content'];
                $v['createtime'] = $comment_info['createtime'];
                $v['nickname'] = (new User())->where(['id' => $comment_info['user_id']])->value('nickname');
                $v['user_id'] = $comment_info['user_id'];
            }
            $value['commentlist'] = $commentlist;
        }
        $total = (new ForumsysForum())->where($where)->where(['status' => 1])->count();
        $result = [
            "total" => $total,
            "rows"  => $list ?? []
        ];
        $this->success("The request is successful", $result ?? []);
    }


    /**
     * 帖子列表
     */
    public function mylist()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $type = $this->request->param('type', 1); //类型:1=我的帖子,2=我的回复
        if (!$type) {
            $this->error(__('parameter error'));
        }
        if ($type == 1) {
            $result = $this->myforumlist($page, $pageSize, $user_id);
        } else {
            $result = $this->mycomment($page, $pageSize, $user_id);
        }
        $this->success("The request is successful", $result ?? []);
    }

    /**
     * 帖子列表
     */
    public function myforumlist($page, $pageSize, $user_id)
    {
        $where = [
            'user_id' => $user_id,
            'deletetime' => null,
        ];
        $list = (new ForumsysForum())
            ->where($where)
            ->field('id')
            ->order('is_top desc')
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        $field1 = ['content', 'image', 'createtime', 'is_top', 'user_id', 'status'];
        $field2 = ['content', 'createtime', 'user_id'];
        foreach ($list as &$value) {
            $forum_info = $this->forumdetail($value['id'], $field1);
            if ($forum_info['image']) {
                $value['image'] = explode(',', format_image($forum_info['image']));
            } else {
                $value['image'] = [];
            }
            $value['createtime'] = $forum_info['createtime'];
            $value['content'] = $forum_info['content'];
            $value['is_top'] = $forum_info['is_top'];
            $value['status'] = $forum_info['status'];
            $value['user_id'] = $forum_info['user_id'];
            $user_info = (new User())->where(['id' => $forum_info['user_id']])->field('nickname,avatar')->find();
            $value['nickname'] = $user_info['nickname'];
            $value['avatar'] = format_image($user_info['avatar']);
            //评论列表，默认5条
            $commentlist = (new Forumcomment())->where(['fid' => $value['id'], 'status' => 1, 'deletetime' => null])->field('id')->order('createtime desc')->limit(6)->select();
            foreach ($commentlist as &$v) {
                $comment_info = $this->commentdetail($v['id'], $field2);
                $v['content'] = $comment_info['content'];
                $v['createtime'] = $comment_info['createtime'];
                $v['nickname'] = (new User())->where(['id' => $comment_info['user_id']])->value('nickname');
                $v['user_id'] = $comment_info['user_id'];
            }
            $value['commentlist'] = $commentlist;
        }
        $total = (new ForumsysForum())->where($where)->count();
        $result = [
            "total" => $total,
            "rows"  => $list ?? []
        ];
        return $result;
    }

    /**
     * 帖子删除
     */
    public function forumdel()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $id = $this->request->param('id');
        if (!$id) {
            $this->error(__('parameter error'));
        }
        $res = (new ForumsysForum())->where(['id' => $id, 'user_id' => $user_id])->field('id')->find();
        if (empty($res)) {
            $this->error(__('Can only delete own posts'));
        }
        Db::startTrans();
        try {
            (new ForumsysForum())->where(['id' => $id])->delete();
            $pid = (new ForumsysForum())->where(['id' => $id])->value('pid');
            (new CacheModel())->delkeys('forumlist', $id);
            (new CacheModel())->delsetkeys('forumlist', $id, [], 0, 0, true);
            (new CacheModel())->delreckeys('forumlist', $id, [], $pid, 0, true);
            //提交
            Db::commit();
            $this->success(__('operate successfully'));
        } catch (Exception $e) {
            Log::mylog('删除帖子失败', $e, 'forumdel');
            Db::rollback();
            $this->error(__('operation failure'));
        }
    }

    /**
     * 评论删除
     */
    public function commentdel()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $id = $this->request->param('id');
        if (!$id) {
            $this->error(__('parameter error'));
        }
        $res = (new Forumcomment())->where(['id' => $id, 'user_id' => $user_id])->field('id')->find();
        if (empty($res)) {
            $this->error(__('Can only delete my own replies'));
        }
        Db::startTrans();
        try {
            (new Forumcomment())->where(['id' => $id])->delete();
            $pid = (new Forumcomment())->where(['id' => $id])->value('fid');
            (new CacheModel())->delkeys('commentlist', $id);
            (new CacheModel())->delsetkeys('commentlist', $id, [], 0, 0, true);
            (new CacheModel())->delreckeys('commentlist', $id, [], $pid, 0, true);
            //提交
            Db::commit();
            $this->success(__('operate successfully'));
        } catch (Exception $e) {
            Log::mylog('删除评论失败', $e, 'commentdel');
            Db::rollback();
            $this->error(__('operation failure'));
        }
    }

    /**
     * 我的回复
     */
    public function mycomment($page, $pageSize, $user_id)
    {
        $where = [
            'user_id' => $user_id,
            'deletetime' => null,
        ];
        $list = (new Forumcomment())
            ->where($where)
            ->field('id')
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        $field2 = ['content', 'createtime', 'user_id', 'status', 'fid'];
        foreach ($list as &$v) {
            $comment_info = $this->commentdetail($v['id'], $field2);
            $v['content'] = $comment_info['content'];
            $v['createtime'] = $comment_info['createtime'];
            $v['status'] = $comment_info['status'];
            $forum_content = $this->forumdetail($comment_info['fid'], ['content']);
            $v['forum_content'] = $forum_content['content'];
        }
        $total = (new Forumcomment())->where($where)->count();
        $result = [
            "total" => $total,
            "rows"  => $list ?? []
        ];
        return $result;
    }

    /**
     * 帖子所有回复
     */
    public function commentlist()
    {
        $page = $this->request->param('page', 1);
        $pageSize = $this->request->param('pagesize', 20);
        $fid = $this->request->param('fid'); //帖子ID
        $where['fid'] = $fid;
        $where['deletetime'] = null;
        $where['status'] = 1;
        $list = (new Forumcomment())
            ->where($where)
            ->field('id')
            ->order('createtime desc')
            ->page($page, $pageSize)
            ->select();
        $field2 = ['content', 'createtime', 'user_id', 'status', 'fid'];
        foreach ($list as &$v) {
            $comment_info = $this->commentdetail($v['id'], $field2);
            $v['content'] = $comment_info['content'];
            $v['createtime'] = $comment_info['createtime'];
            $v['status'] = $comment_info['status'];
            $user_info = (new User())->where(['id' => $comment_info['user_id']])->field('nickname,avatar')->find();
            if ($comment_info['user_id'] != 0) {
                $v['nickname'] = $user_info['nickname'];
                $v['avatar'] = format_image($user_info['avatar']);
            } else {
                $value['nickname'] = "ALPHA";
                $value['avatar'] = format_image("/uploads/20230917/53d1deac105c1d6362fe42d2ce91a9b7.png");
            }
        }
        $total = (new Forumcomment())->where($where)->count();
        $result = [
            "total" => $total,
            "rows"  => $list ?? []
        ];
        $this->success("The request is successful", $result ?? []);
    }

    /**
     * 用户发帖
     */
    public function postamessage()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $pid = $this->request->post('pid'); //发帖频道ID
        $content = $this->request->post('content'); //帖子内容
        $image = $this->request->post('image', ''); //帖子图片
        if (!$pid || !$content) {
            $this->error(__('parameter error'));
        }
        // $this->verifyUser();
        //是否有待审核的帖子
        // $info = (new ForumsysForum())->where(['user_id'=>$user_id,'status'=>0])->field('id')->find();
        // if(!empty($info)){
        //     $this->error(__('You have posts under review'));
        // }
        Db::startTrans();
        try {
            $params = [
                'user_id' => $user_id,
                'pid' => $pid,
                'content' => $content,
                'image' => $image,
                'createtime' => time(),
                'updatetime' => time()
            ];
            $id = (new ForumsysForum())->insertGetId($params);
            //提交
            Db::commit();
            //redis
            $res = (new ForumsysForum())->where(['id' => $id])->find()->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("forumlist", $id, $res);
            // (new CacheModel())->setSortedSetCache("forumlist", $id, $res, $res['pid'], $res['is_top']);
            (new CacheModel())->setRecommendSortedSetCache("forumlist", $id, $res,  $res['pid'], $res['is_top']);
            (new CacheModel())->setSortedSetCache("forumlist", $id, $res, 0, $res['is_top']);
            $this->success(__('operate successfully'));
        } catch (Exception $e) {
            Log::mylog('发帖失败', $e, 'forum');
            Db::rollback();
            $this->error(__('operation failure'));
        }
    }

    /**
     * 发表评论
     */
    public function comment()
    {
        $this->verifyUser();
        $user_id = $this->uid;
        $fid = $this->request->post('fid'); //帖子ID
        $content = $this->request->post('content'); //评论内容
        if (!$fid || !$content) {
            $this->error(__('parameter error'));
        }
        Db::startTrans();
        try {
            $params = [
                'user_id' => $user_id,
                'fid' => $fid,
                'content' => $content,
                'createtime' => time(),
                'updatetime' => time()
            ];
            $id = (new Forumcomment())->insertGetId($params);
            //提交
            Db::commit();
            //redis
            $res = (new Forumcomment())->where(['id' => $id])->find()->toArray();
            (new CacheModel())->setLevelCacheIncludeDel("commentlist", $id, $res);
            // (new CacheModel())->setSortedSetCache("commentlist", $id, $res, $res['fid'], $res['createtime']);
            (new CacheModel())->setRecommendSortedSetCache("commentlist", $id, $res,  $res['fid'], $res['createtime']);
            (new CacheModel())->setSortedSetCache("commentlist", $id, $res, 0, $res['createtime']);
            $this->success(__('operate successfully'));
        } catch (Exception $e) {
            Log::mylog('评论失败', $e, 'comment');
            Db::rollback();
            $this->error(__('operation failure'));
        }
    }

    //帖子详情
    protected function forumdetail($id, $field = null)
    {
        $redis = new Redis();
        $redis->handler()->select(0);
        if ($field) {
            return $redis->handler()->hMget("zclc:forumlist:" . $id, $field);
        } else {
            return $redis->handler()->Hgetall("zclc:forumlist:" . $id);
        }
    }

    //评论详情
    protected function commentdetail($id, $field = null)
    {
        $redis = new Redis();
        $redis->handler()->select(0);
        if ($field) {
            return $redis->handler()->hMget("zclc:commentlist:" . $id, $field);
        } else {
            return $redis->handler()->Hgetall("zclc:commentlist:" . $id);
        }
    }
}
