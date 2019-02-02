<?php
/**
 * Index 
 * User: sishen007
 * Date: 19/02/02
 * Time: 上午11:15
 */

namespace app\index\controller;
class Index
{
    public function index()
    {
        echo '';
    }

    public function index_view()
    {
        require '/srv/swoole/SwooleDemo/thinkphp/public/static/live/detail.html';
    }

    public function singwa()
    {
        echo time();
    }

    public function hello($name = 'ThinkPHP5')
    {
        echo 'hessdggsg' . $name . time();
    }

    public function test()
    {
        echo "this is test content....";
    }
}
