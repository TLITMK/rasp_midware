<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/12/19
 * Time: 14:31
 */

namespace App\HttpServer\Handlers;


use App\Handler\ClientHandler;
use App\Handler\FaceController;
use App\Handler\FaceDetectController;
use GuzzleHttp\Client;

class HttpController
{
    protected $html;
    protected $response;
    protected $request;
    protected $title;
    public function OnRequest ($request,$response) {
        $this->response=$response;
        $this->request=$request;
        $this->response->header("Content-Type", "text/html; charset=utf-8");
        $this->html="<!DOCTYPE html>";
        $this->html.="<html><head><title>".$this->title."</title>";
        $this->html.= "
<style>
body{font-size: 40px;background: #0C0C0C}
div{alignment: center}
p{background-color: #ffc2c5;}
a{background-color: #aeff93;text-decoration:none;}
form{color: #bc0007;}
input{font-size: 40px}
</style>";

        $this->html.="</head><body><div>";
        switch ($this->request->server['path_info']){
            case '/':
                $this->response->end(json_encode([
                    'success'=>true,
                    'msg'=>'提交成功',
                    'data'=>''
                ]));
                //设备拍照注册回调
                $FDC=new FaceDetectController();
                $FDC->TakeFaceCallBack($this->request->post);
                break;
                //主页
            case '/index':
                $this->index();
                break;
                //主页列表选项
            case '/class':
                $this->person_index();
                break;
            case '/face':
                $this->face_index();
                break;
            case '/register':
                break;
                //细化
            case '/class/stus':
                $this->person_stu_list();
                break;
            case '/class/stus/photo':
                $this->person_stu_photo();
            case '/get_school_id'://获取学校id
                $this->get_school_id();
                break;
            case '/face/config'://点击ip进入设置选项
                $this->face_config();
                break;
            case '/face/config/setConfig':
                $this->face_config_setConfig();
                break;
            case '/face/config/classes':
                $this->face_config_getClasses();
                break;
            case '/face/config/setConfig/setDetail':
                $this->post_setConfig();
                break;

            case '/ping_ip'://测试ping设备
                $this->response->end($this->pingAddress($this->request->get['ip']));
                break;


        }

        $this->html.="</div></body></html>";
        $this->response->end($this->html);

    }
    function getNameByIp($ip){
        $door=floor(explode('.',$ip)[3]*0.2)."通道";
        $door.=explode('.',$ip)[3]%5==3?'进 ':'出 ';
        return $door.$ip;
    }
    function index(){
        $this->title='人脸识别控制台';
        $this->html.="<h1 style='color: #a94442;'>人脸识别控制台</h1><br><br>";
        $this->html.="<a href='/face'>设备管理</a><br><br>";
        $this->html.="<a href='/class'>人员管理</a><br><br>";

    }
    function face_index(){
        $this->title='设备列表';
        $this->html.="<h1 style='color: #a94442;'>设备列表</h1><br><br>";
        $con=new FaceDetectController();
        $dev_list=[];
        $ips=$con->getIPsByDoorNum();
        $this->html.="<a href='/index'><-返回主页</a><br><br>";
        foreach ($ips as $k=>$ip){
            $online=$this->pingAddress($ip);
            $door=$this->getNameByIp($ip);
            if($online){
                $dev_list[]=$ip;
                $this->html.= "<a href='/face/config?ip=".$ip."'>".($door)."</a><br><br>";
            }else{
                $this->html.= "<p>".$door.$ip."</p><br><br>";
            }
        }
    }

    function get_school_id(){
        $this->title='学校id';
        $this->html.="<h1 style='color: #a94442;'>学校id</h1><br><br>";
        $this->html.=("<p>".env('SCHOOL_ID')."</p><br><br>");
    }

    function face_config(){
        $ip=$this->request->get['ip'];
        $name=$this->getNameByIp($ip);
        $this->title=$name;
        $this->html.="<h1 style='color: #a94442;'>".$name."</h1><br><br>";
        $this->html.="<a href='/face'><-返回设备列表</a><br><br>";
        $this->html.="<a href='/face/config/setConfig?ip=".$ip."'>设备配置-></a><br><br>";
        $this->html.="<a href=''>设备重启-></a><br><br>";
        $this->html.="<a href=''>设备开门-></a><br><br>";
        $this->html.="<a href=''>照片下发-></a><br><br>";
    }
    function face_config_setConfig(){
        $ip=$this->request->get['ip'];
        $name=$this->getNameByIp($ip);
        $this->title='设备配置'.$name;
        $this->html.="<h1 style='color: #a94442;'>设备配置".$name."</h1><br><br>";
        $this->html.="<a href='/face/config?ip=".$ip."'><-返回设备选项</a><br><br>";
        //设置识别距离
        $this->html.="<form action='/face/config/setConfig/setDetail' method='post'><br>";
        $this->html.="识别距离：<input type='text' name='distance' value=''><br>";
        $this->html.="识别分数：<input type='text' name='score' value=''><br>";
        $this->html.="<input hidden name='ip' value='".$ip."'><br>";
        $this->html.="<input type='submit' value='ping'>"."</form>";
    }
    function person_index(){
        $this->title='班级列表';
        $this->html.="<h1 style='color: #a94442;'>班级列表"."</h1><br><br>";
        $this->html.="<a href='/index'><-返回主页</a><br><br>";
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/getClassList',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID')
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        foreach ($res['data'] as $k=>$item){
            $this->html.="<a href='/class/stus?class_id=".$item['id']."'>".$item['grade']['grade_name'].$item['class_name']."-></a><br><br>";
        }
    }
    function person_stu_list(){
        $class_id=$this->request->get['class_id'];
        $this->title='学生列表';
        $this->html.="<h1 style='color: #a94442;'>学生列表"."</h1><br><br>";
        $this->html.="<a href='/class'><-返回班级列表</a><br><br>";
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/getClassStus',[
            'form_params'=>[
                'class_id'=>$class_id
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        foreach ($res['data'] as $k=>$item){
            $this->html.="<a href='/class/stus/photo?personId=".$item['id']."&class_id=".$class_id."'>".$item['stu_name']."</a><br><br>";
        }
    }
    function  person_stu_photo(){
        $personId=$this->request->get['personId'];
        $class_id=$this->request->get['class_id'];
        $this->title='学生照片';
        $this->html.="<h1 style='color: #a94442;'>学生照片"."</h1><br><br>";
        $this->html.="<a href='/class/stus?class_id=".$class_id."'><-返回学生列表</a><br><br>";
        $client=new Client();
        $con=new FaceDetectController();
        $ips=$con->getIPsByDoorNum();
        foreach ($ips as $k=>$ip){
            $door=$this->getNameByIp($ip);
            $url=$ip.":8090/face/find";
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>'spdc',
                    'personId'=>$personId
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            $dev_list[]=$ip;
            $this->html.="<p>".$door."</p><br>";
            $this->html.="<img src='".$res['data'][0]['path']."'/><br><br>";
        }

    }
    function person_stu_photo_terminal(){

    }


    function post_setConfig(){
        $distance=$this->request->post['distance'];
        $score=$this->request->post['score'];
        $ip=$this->request->post['ip'];
        $url=$ip.':8090/setConfig';
        $client=new Client();
        $data_pas='{
            "identifyDistance":'.$distance.',
            "identifyScores":'.$score.',
            "saveIdentifyTime":0,
            "ttsModType":100,
            "ttsModContent":"欢迎{name}",
            "comModType":1,
            "comModContent":"hello",
            "displayModType":100,
            "displayModContent":"{name}欢迎你",
            "slogan":"三叶草智慧校园",
            "intro":"三叶草智慧校园",
            "recStrangerTimesThreshold":3,
            "recStrangerType":2,
            "ttsModStrangerType":100,
            "ttsModStrangerContent":"陌生人",
            "multiplayerDetection":2,
            "wg":"#34WG{id}#",
            "recRank":2,
            "delayTimeForCloseDoor":500,
            "companyName":"维护电话：18808800781"}';
        $response=$client->request('POST',$url,[
            'form_params'=>[
                'pass'=>'spdc',
                'config'=>$data_pas
            ]
        ]);
        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            $this->success('/face',$response->getBody());
        }else{
            $this->fail('/face',$response->getBody());
        }
    }




    function success($path,$content){
        $this->title='操作结果';
        $this->html.="<p>成功</p><br><br>";
        $this->html.="<a href=".$path."><-返回</a><br><br>";
    }
    function fail($path,$content){
        $this->title='操作结果';
        $this->html.="<p>失败</p><br><br>";
        $this->html.="<p>".$content."</p><br><br>";
        $this->html.="<a href=".$path."><-返回</a><br><br>";
    }


    protected function pingAddress($address) {
        $status = -1;

        if (strcasecmp(PHP_OS, 'WINNT') === 0) {
            // Windows 服务器下
            $pingresult = exec("ping -n 1 {$address}", $outcome, $status);
        } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
            // Linux 服务器下
            $pingresult = exec("ping -c 1 {$address}", $outcome, $status);
        }
        if (0 == $status) {
            $status = true;
        } else {
            $status = false;
        }
        return $status;
    }

}