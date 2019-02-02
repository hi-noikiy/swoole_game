<?php
/**
 * 发送验证码
 * User: sishen007
 * Date: 19/02/02
 * Time: 上午11:15
 */

namespace app\index\controller;

use app\common\lib\ali\Sms;
use app\common\lib\Util;
use app\common\lib\Redis;
use think\Exception;

class Send
{
    /**
     * 发送验证码
     */
    public function index()
    {
        // tp  input
        //$phoneNum = request()->get('phone_num', 0, 'intval');
        $phoneNum = intval($_GET['phone_num']);
        if (empty($phoneNum)) {
            // status 0 1  message data
            return Util::show(config('code.error'), 'error');
        }

        //tood
        // 生成一个随机数
        $code = rand(1000, 9999);

        $taskData = [
            'method' => 'sendSms',
            'data' => [
                'phone' => $phoneNum,
                'code' => $code,
            ]
        ];
        // 为了用户体验,防止阿里大于挂掉后阻塞s
        $_POST['http_server']->task($taskData);
        return Util::show(config('code.success'), 'ok',['code'=>$code]);
        /*try {
            $response = Sms::sendSms($phoneNum, $code);
        }catch (\Exception $e) {
            // todo
            return Util::show(config('code.error'), '阿里大于内部异常');
        }*/
        //if($response->Code === "OK") {
        // redis
        /*$redis = new \Swoole\Coroutine\Redis();
        $redis->connect(config('redis.host'), config('redis.port'));
        $redis->set(Redis::smsKey($phoneNum), $code, config('redis.out_time'));*/

        // 异步redis

        //return Util::show(config('code.success'), 'success');
        //} else {
        // return Util::show(config('code.error'), '验证码发送失败');
        //}

    }

    public function sendTest()
    {
        try {
            $response = Sms::sendSms(18330218238, '11111');
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if ($response->Code === "OK") {
            echo "发送成功";
        } else {
            echo "发送失败";
        }
    }
}
