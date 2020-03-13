<?php
namespace App\SwooleServer;


use App\Handler\FaceController;
use App\Handler\SwooleHandler;
use App\Handler\SwooleHandler24g;
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

            $serv->tick(60000, function($id) use ($serv) {
                $school_id = env('SCHOOL_ID');
                $vpn = system("ifconfig tap0|grep 'inet '|awk -F \" \" '{print $2}'|awk '{print $1}'");
                $terminal_num = env('TERMINAL_NUM');
                info($terminal_num);
                if(!$school_id || !$vpn){
                    info('同步数据获取失败!school_id为空或vpn ip 获取失败');
                    return ;
                }
                $client = new Client;
                $rt = $client->request('POST',env('CURL_URL').'/heart_sync',[
                    "form_params"=>[
                        "vpn"=>$vpn,
                        "school_id"=>$school_id,
                        "terminal_num"=>$terminal_num
                    ]
                ]);


                $bool = json_decode($rt->getBody(),true);
                if(!$bool['success']){
                    info('心跳同步失败!');
                }

//                if($this->i == 2){
//                    info($this->i);
//                    $arr_type = [1,2,3,6,7];
//                    foreach($arr_type as $k =>$v){
//                        info($v);
//                        $face = new FaceController;
//                        $face->get_task($v);
//                    }
//                    $this->i = 0;
//                }else{
//                    $this->i++;
//                }
            });

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

}
