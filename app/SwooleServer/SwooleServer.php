<?php
namespace App\SwooleServer;


use App\Handler\FaceController;
use App\Handler\SwooleHandler;
use App\Handler\SwooleHandler24g;
use function foo\func;
use GuzzleHttp\Client;
use swoole_server;
use swoole_http_server;

class SwooleServer
{
    protected $i = 0;
    public function start_tcp(){



        $server = new swoole_server("0.0.0.0", 9000);
        $server->set([
            'open_length_check' => true,
            'package_length_type'=>'C',
            'package_length_offset'=>2,
            'package_body_offset' => 4,
            'package_max_length'=> 2000000,
            'task_worker_num' => 20,
        ]);
        $handler = new SwooleHandler();
        $server->on('connect',[$handler,'onConnect']);
        $server->on('receive',[$handler,'onReceive']);
        $server->on('close',[$handler,'onClose']);
        $server->on('task',[$handler,'onTask']);
        $server->on('finish',[$handler,'onFinish']);

        $server->on('start',function($serv){

            $func=function($id) use ($serv) {
                $school_id = env('SCHOOL_ID');
                $vpn = system("ifconfig tap0|grep 'inet '|awk -F \" \" '{print $2}'|awk '{print $1}'");
                $terminal_num = env('TERMINAL_NUM');
                if(!$school_id || !$vpn){
                    info('同步数据获取失败!school_id为空或vpn ip 获取失败');
                    return ;
                }
                $client = new Client;
                if(env('USER_ID')){
                    $url=env('SIT_SYNC_URL');
                }
                else{
                    $url=env('CURL_URL').'/heart_sync';
                }
                try{

                    $rt = $client->request('POST',$url,[
                        "form_params"=>[
                            "vpn"=>$vpn,
                            "school_id"=>$school_id,
                            "terminal_num"=>$terminal_num
                        ]
                    ]);


                    $bool = json_decode($rt->getBody(),true);
                    info($terminal_num.' 心跳',$bool);
                }catch (\Throwable $e){
                    info('心跳-异常'.$e->getMessage());
                }
            };
            $func(1);
            $serv->tick(60000, $func);

        });

        
        $server->start();
    }

    public function start_tcp_24g(){
        $server = new swoole_server("0.0.0.0", 9001);
        $server->set([
            'open_length_check' => true,
            'package_length_type'=>'C',
            'package_length_offset'=>2,
            'package_body_offset' => 4,
            'package_max_length'=> 2000000,
            'task_worker_num' => 20,
            'heartbeat_check_interval' => 20, //每20秒侦测一次心跳
            'heartbeat_idle_time' => 120,      //60秒内没有心跳服务端主动断开连接
        ]);
        $handler = new SwooleHandler24g();
        $server->on('connect',[$handler,'onConnect']);
        $server->on('receive',[$handler,'onReceive']);
        $server->on('close',[$handler,'onClose']);
        $server->on('task',[$handler,'onTask']);
        $server->on('finish',[$handler,'onFinish']);

        $server->on('start',function($serv){



        });


        $server->start();
    }


    public function start_tcp_rasp(){
        $server = new swoole_server("0.0.0.0", 9501);
        $server->set([
            'open_length_check' => true,
            'package_length_type'=>'C',
            'package_length_offset'=>2,
            'package_body_offset' => 4,
            'package_max_length'=> 2000000,
            'task_worker_num' => 20,
        ]);
        $handler = new SwooleHandler();
        $server->on('connect',function ($serv,$fd){
            echo ($fd.'-connect-').PHP_EOL;
        });
        $server->on('receive',function ($serv,$fd,$form_id,$data){
            echo ($fd.'-receive-'.$data).PHP_EOL;
            /**@
             * 函数：bool Server->send(mixed $fd, string $data, int $serverSocket = -1);
             * 作用：向客户端发送数据
             * 参数：
             *  $fd，客户端的文件描述符
             *  $data，发送的数据，TCP协议最大不得超过2M，可修改 buffer_output_size 改变允许发送的最大包长度
             *  $serverSocket，向Unix Socket DGRAM对端发送数据时需要此项参数，TCP客户端不需要填写
             */
            $serv->send($fd,"数据：".$data);
        });
        $server->on('close',function ($serv,$fd){
            echo ($fd.'-close-').PHP_EOL;
        });
//        $server->on('task',[$handler,'onTask']);
//        $server->on('finish',[$handler,'onFinish']);

        $server->on('start',function($serv,$fd){
            echo ($fd.'-start-').PHP_EOL;
//            $serv->tick(60000, function($id) use ($serv) {
//
//            });
        });


        $server->start();
    }




}
