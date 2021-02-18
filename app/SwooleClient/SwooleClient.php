<?php
namespace App\SwooleClient;

use App\Handler\ClientHandler;
use App\Handler\FaceController;
use swoole_client;

class SwooleClient
{
    public function start2(){
        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $client->on("connect", function(swoole_client $cli) {
            $body = pack('vA2Ch',dechex(5),'mj',0x01,0);
            $send_data = $this->send_data($body);
            dd(unpack('H*',$send_data));
            $cli->send($send_data."\n");
        });
        $client->on("receive", function(swoole_client $cli, $data){
            $data_len = unpack('C*',$data);

            $head = unpack('A2',$data);
            if(count($data_len) < 9 ){
                return ;
            }
            //包头验证
            if($head[1] != 'gy') {
                echo 'head';
                $cli->close();
            }

            $cmd = unpack('H2',$data,6);
            var_dump($cmd);

            dd(unpack('H2',$data));
           // $cli->send($data);
            sleep(1);
        });
        $client->on("error", function(swoole_client $cli){
            echo "error\n";
        });
        $client->on("close", function(swoole_client $cli){
            echo "Connection close\n";
        });
        $client->on("bufferEmpty", function(swoole_client $cli){
            $cli->close();
        });
        $client->connect('192.168.30.101', 8090);
    }

    public function start1(){
        $client = new swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('192.168.30.101', 8090, -1))
        {
            exit("connect failed. Error: {$client->errCode}\n");
        }
        $body = pack('vA2Ch',dechex(5),'mj',0x01,0);
        $send_data = $this->send_data($body);
        $client->send($send_data."\n");
        echo $client->recv();
//        $client->close();
    }

    public function send_data($body){
        $head = pack('A2','gy');
        $sum = dechex(array_sum(unpack('C*',$body))%256);
        $send_data = $head.$body.pack('C',$sum);

        return $send_data;
    }

    public function start(){
//        for($i=1;$i<2;$i++){
//            $name='dfjghdjghujadhgjkahdjgfjhasdgfyhgdsahjfgasdhjkgfdakjhgbjhdksbjhdsafvhjdsafvhgjsdvfghsdavfghsdafghsdvfvDSKHJFASDHJFVSDAHJFVHDSAFVHDSHFJASHJFHJSFHSDVFHSGFHLSJKSAHJhjsvdjhfgdhjdgjkdfahgjkfd';
//            $len = strlen($name);
//            $start_len = rand(1,$len-3);
//            $send_name= mb_substr($name,$start_len,3,"utf-8");
//            $handler = new ClientHandler();
//            $data = '{"id":"","idcardNum":"","name":"'.$send_name.'"}';
//
//            $resp = $handler->person_create('192.168.30.100',$data);
//            if($resp['success']){
////                $handler = new ClientHandler();
////                $rand = rand(1,17);
////                $image = '/mj_client/face/'.$rand.'.jpg';
////                $rt = $handler->face_create('192.168.30.100',$resp['data']['id'],$image);
////                if(!$rt['success']){
////                    var_dump($rt['data']);
////                }
//                info($resp);
//            }else{
//                echo $send_name.'人员注册失败';
//            }
//        }
        $face = new ClientHandler;
        $rt = $face->setNetInfo('192.168.30.102','192.168.30.8','spdc');
        dd($rt);
    }

    public function start_client_test(){
        $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        //注册连接成功回调
        $client->on("connect", function($cli) {
            $cli->send("hello world\n");
        });

//注册数据接收回调
        $client->on("receive", function($cli, $data){
            echo "Received: ".$data."\n";
            info("Received: ".$data);
        });

//注册连接失败回调
        $client->on("error", function($cli){

            echo "Connect failed\n";
            info("Connect failed: ");
        });

//注册连接关闭回调
        $client->on("close", function($cli){
            echo "Connection close\n";
            info("Connect close: ");
        });

//发起连接
        echo $client->connect('192.168.30.100', 12345, 0.5);

        sleep(1);
        while(1){
            $client->send("心跳");
            sleep(2);
        }

    }
}