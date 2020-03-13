<?php
namespace App\HttpServer;

use App\Handler\FaceController;
use App\Handler\FaceDetectController;
use App\Handler\SwooleHandler;
use App\HttpServer\Handlers\HttpController;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
//use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Http\Response;
use Intervention\Image\Facades\Image;
use swoole_http_server;

class HttpServer
{

    public function http_serv(){
        $http = new swoole_http_server("0.0.0.0",8088);//照片注册回调专用端口
        $http->set([
//            'daemonize'=> false,
//            'task_worker_num' => 10,
            'log_level'=>SWOOLE_LOG_INFO,
            'trace_flags' => SWOOLE_TRACE_ALL,
            'worker_num'=>30
        ]);
        $http->on('request',[new HttpController(),'OnRequest']);
        $http->on('start',function($serv){
            info('http start');

        });

        $http->start();

        //回调返回值
//        {
//            "imgBase64":"/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBA",
//            "imgPath":"http://192.168.30.8:8090/apk_imgs/1562312178666.jpg",
//            "ip":"192.168.30.8",
//            "deviceKey":"40A709DE53FBFEDE",
//            "personId":"393552",
//            "time":"1562312179051",
//            "faceId":"157eb87c5dea4c89b191531ba3808e2a"
//        }
    }


    public function http_start(){
        $http = new swoole_http_server("0.0.0.0", 80);
        $http->set([
            'enable_static_handler'=>true,
            'document_root' => base_path('template'),
            //配置项目的目录
            'project_path' => __DIR__ . '/Handlers/',
        ]);

        $http->on('request', function ($request,$response) {

            info('收到HTTP请求',[$request]);
            $arr_data = $request->post;
            if(!isset($arr_data['type'])){
                $response->end(json_encode([
                    'success'=>false,
                    'msg'=>'非法请求！',
                    'data'=>$arr_data
                ]));
            }
            if($request->server['request_uri'] == '/face'){
                $data = $request->get;
//                info($data);
//                $res = $this->face($data);
                //return '{"result":1,"success":true}';
//                if($res['success']){
//                    $response->header('Content-Type', 'application/json',false);
//                    $response->end(json_encode([
//                        'result'=>1,
//                        'success'=>true,
//                    ]));
//                }

//                return response()->json([
//                    'result'=>1,
//                    'success'=>true,
//                ]);

            }
            switch ($arr_data['type']){
                case 1: //获取 env

                    //重新加载.env 到环境变量
                    $dotEnv = new Dotenv(base_path());
                    $dotEnv->overload();

                    $response->end(json_encode([
                        'success' => true,
                        'msg' => '提交成功',
                        'data' => [
                            'school_id' => env('SCHOOL_ID'),
                            'door_num' => env('DOOR_NUM'),
                            'url' => env('CURL_URL'),
                            'camera_ip' => env('CAMERA_IP'),
                            'switch' => !env('SNAP_VIDEO')?0:1,
                            'camera_uname' => env('CAMERA_USER_NAME'),
                            'camera_pwd' => env('CAMERA_PWD'),
                        ]
                    ]));
                    break;
                case 2: //配置env
                    $data = [
                        'SCHOOL_ID'=>$arr_data['school_id'],
                        'DOOR_NUM'=>$arr_data['door_num'],
                        'CURL_URL'=>$arr_data['url'],
                        'CAMERA_IP'=>$arr_data['camera_ip'],
                        'SNAP_VIDEO'=>$arr_data['switch']==1?'true':'false',
                        'CAMERA_USER_NAME'=>$arr_data['camera_uname'],
                        'CAMERA_PWD'=>$arr_data['camera_pwd']
                    ];
                    $this->modifyEnv($data);

                    $response->end(json_encode([
                        'success'=>true,
                        'msg'=>'提交成功',
                        'data'=>$data
                    ]));
                    break;
                case 3: //检测摄像头
                    $door_num = env('DOOR_NUM');
                    if(!$door_num){
                        $response->end(json_encode([
                            'success'=>true,
                            'msg'=>'请先配置安装通道数量',
                            'data'=>''
                        ]));
                    }
                    $list = [];
                    $arr = [1,2];
                    for($i=1;$i<=$door_num;$i++){
                        foreach($arr as $k => $v){
                            $http_ip = $this->get_ip($i,$v);
                            list($a,$ip) = explode('//',$http_ip);
//                            echo 'test ip: '.$ip.PHP_EOL;
                            system('nc -w 2 -zv '.$ip.' 80',$rt);
                            $list[$http_ip] = $rt==0?'在线':'掉线';
                        }
                    }
                    $response->end(json_encode([
                        'success'=>true,
                        'msg'=>'',
                        'data'=>$list
                    ]));
                    break;
                case 4://抓拍
                    $ip = $arr_data['ip'];
                    if(!$ip){
                        $response->end(json_encode([
                            'success'=>false,
                            'msg'=>'ip不存在！',
                            'data'=>''
                        ]));
                    }
                    try {
                        $client = new Client();
                        $rt = $client->request('GET', $ip.'/ISAPI/Streaming/channels/1/picture',[
                                'auth' => [env('CAMERA_USER_NAME','admin'), env('CAMERA_PWD','admin123')],
                                'timeout'=>3
                            ]);
                        $imgFileName = last( explode('.',$ip) );

                        file_put_contents(env('PATH_ATT').'/template/images/'.$imgFileName.'.jpg',$rt->getBody());
                        $image = './images/'.$imgFileName.'.jpg';
                        $response->end(json_encode([
                            'success'=>true,
                            'msg'=>'操作成功！',
                            'data'=>$image
                        ]));
                    }catch (\Exception $e) {
                        $response->end(json_encode([
                            'success'=>false,
                            'msg'=>'操作失败！'.$e->getMessage(),
                            'data'=>''
                        ]));
                    }




                    break;
                case 5://获取通道ip列表
                    $door_num = env('DOOR_NUM');
                    if(!$door_num){
                        $response->end(json_encode([
                            'success'=>true,
                            'msg'=>'请先配置安装通道数量',
                            'data'=>''
                        ]));
                    }
                    $images = [];
                    $list = [];
                    $arr = [1,2];
                    for($i=1;$i<=$door_num;$i++){
                        $list[$i] = [];
                        foreach($arr as $k => $v){
                            $ip = $this->get_ip($i,$v);
                            $list[$i][$v] = $ip;
                        }
                    }
                    foreach($list as $k => $v){
                       foreach($list[$k] as $item => $c){
                           $images[$c] = '';
                       }
                    }
                    $response->end(json_encode([
                        'success'=>true,
                        'msg'=>'操作成功！',
                        'data'=>['list'=>$list,'images'=>$images]
                    ]));
            }

//            $response->header('Content-Type', 'text/html; charset=utf-8');
//            exec("ifconfig | grep inet",$output);
//
//            $response->end("<h1>".join('<br>',$output)."</h1>");

        });
        $http->start();
    }

    public function modifyEnv($data)
    {
        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';

        $contentArray = collect(file($envPath, FILE_IGNORE_NEW_LINES));

        $contentArray->transform(function ($item) use ($data){
            foreach ($data as $key => $value){
                if(str_contains($item, $key)){
                    return $key . '=' . $value;
                }
            }

            return $item;
        });

        $content = implode($contentArray->toArray(), "\n");

        \File::put($envPath, $content);
    }

    //'/ISAPI/Streaming/channels/1/picture'
    public function get_ip($door_num,$enter){
        $ip = env('CAMERA_IP');
        $i = $door_num*5 + $enter;
        return $ip.$i;
    }

    public function validate($user, $pass) {
        $users = ['dee'=>'123456', 'admin'=>'admin'];
        if(isset($users[$user]) && $users[$user] === $pass) {
            return true;
        } else {
            return false;
        }
    }

    //人脸识别回调
    public function face($data){
//        if(!count($data)){
//            return ;
//        }
//        if($data['type'] != 'face_0'){
//            return;
//        }
////        if($data['id'] == 'STRANGERBABY'){
////            return ;
////        }
//        //try{
//            $img = Image::make($data['path']);
//            $name = time().rand(10000,99999);
//            $img->save(env('PATH_ATT').'/public/face/'.$name.'.jpg');
//            $file = fopen(env('PATH_ATT').'/public/face/'.$name.'.jpg', 'r');
//            $int_time = (int)substr($data['time'],0,10);
//            $dateTime = date('Y-m-d H:i:s',$int_time);
//            $client = new Client();
//            $response = $client->request('POST', env('CURL_URL').'/face/sync_face', [
//                'multipart' => [
//                    [
//                        'name'     => 'upload_att_image',
//                        'contents' => 'abc',
//                        'headers'  => []
//                    ],
//                    [
//                        'name'     => 'image',
//                        'contents' => $file
//                    ],
//                    [
//                        'name'     => 'terminal_num',
//                        'contents' => $data['deviceKey']
//                    ],
//                    [
//                        'name'     => 'ip',
//                        'contents' => $data['ip']
//                    ],
//                    [
//                        'name'     => 'dateTime',
//                        'contents' => $dateTime
//                    ],
//                    [
//                        'name'     => 'student_id',
//                        'contents' => $data['personId']
//                    ],
//                ]
//            ]);
//            system('rm -rf '.env('PATH_ATT').'/public/face/'.$name.'.jpg');
//            @fclose($file);
//            $rt = json_decode($response->getBody(),true);
//        if(!$rt['code']){
//            info('识别信息上报成功!');
//                return [
//                    'success'=>true,
//                    'msg'=>'识别信息上报成功!'
//                ];
//            }else{
//                return [
//                    'success'=>false,
//                    'msg'=>$rt['msg']
//                ];
//                info($rt['msg']);
//            }
//        }catch (\Exception $e) {
//            info('上报识别记录异常');
//        }
    }

}