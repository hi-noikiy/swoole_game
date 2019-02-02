<?php

/**
 * websocket 基于http_server的 所以将http服务集成到ws
 * User: sishen007
 * Date: 19/02/02
 * Time: 上午11:15
 */

/**
 * Class Ws
 * 遗留问题: 配置后修改静态文件内容, 浏览器访问发现内容不会改变, 重启进程也一样 有谁遇到过这个问题吗？只有修改文件名后才能使访问内容改变？
 */

class Ws
{
    CONST HOST = "0.0.0.0";
    CONST PORT = 8811;
    CONST CHART_PORT = 8812;

    protected $ws = null;
    protected $wsConfig = [];

    public function __construct()
    {
        /**
         * 初始化加载配置文件
         */
        if (empty($this->wsConfig)) {
            $this->wsConfig = include_once __DIR__ . '/../../config.php';
        }

        /**
         * 实例化websocket server 作用于服务端推送消息->客户端
         * 默认监听的是 SWOOLE_PROCESS 多进程模式 , SWOOLE_SOCK_TCP TCP服务
         */
        $this->ws = new swoole_websocket_server(self::HOST, self::PORT);
        /**
         * 新增聊天室端口 用于客户端推送消息->服务端->所有客户端
         */
        $this->ws->listen(self::HOST, self::CHART_PORT, SWOOLE_SOCK_TCP);

        /**
         * 设置websocket options
         */
        $this->ws->set(
            [
                'enable_static_handler' => $this->wsConfig['enable_static_handler'],
                'document_root' => $this->wsConfig['document_root'],
                'worker_num' => $this->wsConfig['worker_num'],
                'task_worker_num' => $this->wsConfig['task_worker_num'],
            ]
        );

        /**
         * 监听start 事件
         * 启动成功后会创建worker_num+2个进程。Master进程+Manager进程+ worker_num个Worker进程
         */
        $this->ws->on("start", [$this, 'onStart']);
        /**
         * 监听 open
         * 当WebSocket 客户端与服务器 建立连接并完成握手后会回调此函数。
         */
        $this->ws->on("open", [$this, 'onOpen']);
        /**
         * 监听message
         * 当服务器收到来自客户端的数据帧时会回调此函数。
         */
        $this->ws->on("message", [$this, 'onMessage']);
        /**
         * 监听workerStart
         * 此事件在 Worker进程/Task进程 启动时发生。这里创建的对象可以在进程生命周期内使用
         * 注: onWorkerStart/onStart是并发执行的，没有先后顺序
         */
        $this->ws->on('WorkerStart', [$this, 'onWorkerStart']);
        /**
         * 监听request
         */
        $this->ws->on('request', [$this, 'onRequest']);
        /**
         * 监听task
         */
        $this->ws->on('task', [$this, 'onTask']);
        /**
         * 监听finish
         * 和task联合使用
         */
        $this->ws->on('finish', [$this, 'onFinish']);
        /**
         * 监听close
         */
        $this->ws->on('close', [$this, 'onClose']);

        /**
         * 启动服务器
         */
        $this->ws->start();
    }

    /**
     * @param $server
     */
    public function onStart($server)
    {
        swoole_set_process_name("live_master");
    }

    /**
     * @param $server
     * @param $worker_id
     */
    public function onWorkerStart($server, $worker_id)
    {
        // 定义应用目录
        define('APP_PATH', __DIR__ . '/../../../application/');
        // 加载框架里面的文件
        //require __DIR__ . '/../thinkphp/base.php';
        require __DIR__ . '/../../../thinkphp/start.php';
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response)
    {
        echo "oksssss" . PHP_EOL;
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            $response->end();
            return;
        }
        $_SERVER = [];
        if (isset($request->server)) {
            foreach ($request->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        if (isset($request->header)) {
            foreach ($request->header as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }

        $_GET = [];
        if (isset($request->get)) {
            foreach ($request->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
        $_FILES = [];
        if (isset($request->files)) {
            foreach ($request->files as $k => $v) {
                $_FILES[$k] = $v;
            }
        }
        $_POST = [];
        if (isset($request->post)) {
            foreach ($request->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }

        $this->writeLog();
        $_POST['http_server'] = $this->ws;


        ob_start();
        // 执行应用并响应
        try {
            think\Container::get('app', [APP_PATH])
                ->run()
                ->send();
        } catch (\Exception $e) {
            // todo
        }

        $res = ob_get_contents();
        ob_end_clean();
        $response->end($res);
    }

    /**
     * @param $serv
     * @param $taskId
     * @param $workerId
     * @param $data
     * @return mixed
     */
    public function onTask($serv, $taskId, $workerId, $data)
    {

        // 分发 task 任务机制，让不同的任务 走不同的逻辑
        $obj = new app\common\lib\task\Task;

        $method = $data['method'];
        $flag = $obj->$method($data['data'], $serv);
        /*$obj = new app\common\lib\ali\Sms();
        try {
            $response = $obj::sendSms($data['phone'], $data['code']);
        }catch (\Exception $e) {
            // todo
            echo $e->getMessage();
        }*/

        return $flag; // 告诉worker
    }

    /**
     * @param $serv
     * @param $taskId
     * @param $data
     */
    public function onFinish($serv, $taskId, $data)
    {
        echo "taskId:{$taskId}\n";
        echo "finish-data-sucess:{$data}\n";
    }

    /**
     * 监听ws连接事件(这个只有和前端js websoket连接的时候才会触发)
     * @param $ws
     * @param $request
     */
    public function onOpen($ws, $request)
    {
        // fd redis [1]
        \app\common\lib\redis\Predis::getInstance()->sAdd(config('redis.live_game_key'), $request->fd);
        var_dump($request->fd);
    }

    /**
     * 监听ws消息事件
     * @param $ws
     * @param $frame
     */
    public function onMessage($ws, $frame)
    {
        echo "ser-push-message:{$frame->data}\n";
        $ws->push($frame->fd, "server-push:" . date("Y-m-d H:i:s"));
    }

    /**
     * close
     * @param $ws
     * @param $fd
     */
    public function onClose($ws, $fd)
    {
        // fd del
        \app\common\lib\redis\Predis::getInstance()->sRem(config('redis.live_game_key'), $fd);
        echo __FUNCTION__ . "clientid:{$fd}\n";
    }

    /**
     * 记录日志
     */
    public function writeLog()
    {
        $datas = array_merge(['date' => date("Ymd H:i:s")], $_GET, $_POST, $_SERVER);

        $logs = "";
        foreach ($datas as $key => $value) {
            $logs .= $key . ":" . $value . " ";
        }

        swoole_async_writefile(APP_PATH . '../runtime/log/' . date("Ym") . "/" . date("d") . "_access.log", $logs . PHP_EOL, function ($filename) {
            // todo
        }, FILE_APPEND);

    }
}

new Ws();

// 20台机器    agent -> spark (计算) - 》 数据库   elasticsearch  hadoop

// sigterm sigusr1 usr2
