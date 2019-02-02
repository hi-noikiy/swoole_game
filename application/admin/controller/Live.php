<?php
/**
 * 这个push是操作到赛事详情
 * User: sishen007
 * Date: 19/02/02
 * Time: 上午11:15
 */

namespace app\admin\controller;

use app\common\lib\Util;

class Live
{

    public function index_view()
    {
        require '/srv/swoole/SwooleDemo/thinkphp/public/static/admin/live.html';
    }

    public function push()
    {
        if (empty($_GET)) {
            return Util::show(config('code.error'), 'error');
        }  // admin
        // token    md5(content)
        // => mysql
        $teams = [
            1 => [
                'name' => '马刺',
                'logo' => '/live/imgs/team1.png',
            ],
            4 => [
                'name' => '火箭',
                'logo' => '/live/imgs/team2.png',
            ],
        ];
        $typeStr = '';
        switch (intval($_GET['type'])) {
            case 1:
                $typeStr = '第一节';
                break;
            case 2:
                $typeStr = '第二节';
                break;
            case 3:
                $typeStr = '第三节';
                break;
            case 4:
                $typeStr = '第四节';
                break;
        }
        $data = [
            'type' => $typeStr,
            'time' => date("H:i",time()),
            'title' => !empty($teams[$_GET['team_id']]['name']) ? $teams[$_GET['team_id']]['name'] : '直播员',
            'logo_img' => !empty($teams[$_GET['team_id']]['logo']) ? $teams[$_GET['team_id']]['logo'] : '',
            'content' => !empty($_GET['content']) ? $_GET['content'] : '',
            'image' => !empty($_GET['image']) ? $_GET['image'] : '',
        ];
        //print_r($_GET);
        // 获取连接的用户
        // 赛况的基本信息入库   2、数据组织好 push到直播页面
        $taskData = [
            'method' => 'pushLive',
            'data' => $data
        ];
        $_POST['http_server']->task($taskData);
        return Util::show(config('code.success'), 'ok');
    }

}
