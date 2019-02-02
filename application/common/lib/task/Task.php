<?php
/**
 * 代表的是  swoole里面 后续 所有  task异步 任务 都放这里来
 * Date: 18/3/27
 * Time: 上午1:20
 */

namespace app\common\lib\task;

use app\common\lib\ali\Sms;
use app\common\lib\redis\Predis;
use app\common\lib\Redis;

class Task
{

    /**
     * 异步发送 验证码
     * @param $data
     * @param $serv swoole server对象
     * @return bool
     */
    public function sendSms($data, $serv)
    {
        /**
         * 这里没有开通发送短信的服务,所以就先把短信验证码直接打出来
         */
//        try {
//            $response = Sms::sendSms($data['phone'], $data['code']);
//        }catch (\Exception $e) {
//            // todo
//            return false;
//        }
        $response = new \stdClass();
        $response->Code = "OK";
        // 如果发送成功 把验证码记录到redis里面
        if ($response->Code === "OK") {
            Predis::getInstance()->set(Redis::smsKey($data['phone']), $data['code'], config('redis.out_time'));
        } else {
            return false;
        }
        return true;
    }

    /**
     * 通过task机制发送赛况实时数据给客户端
     * @param $data
     * @param $serv swoole server对象
     */
    public function pushLive($data, $serv)
    {
        $clients = Predis::getInstance()->sMembers(config("redis.live_game_key"));
//
//        foreach ($clients as $fd) {
//            $serv->push($fd, json_encode($data));
//        }
        // 为了和聊天室区分开,这里采用端口区别
        foreach ($serv->ports[0]->connections as $fd) {
            if (in_array($fd, $clients)) {
                $serv->push($fd, json_encode($data));
            }
        }
    }
}