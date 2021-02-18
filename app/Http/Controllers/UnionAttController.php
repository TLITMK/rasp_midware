<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2020/2/4
 * Time: 16:54
 */

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class UnionAttController extends Controller
{
    public function face_union_auth(Request $request){
//        info('',$request->all());
        $return=response()->json([
            'result'=>1,
            'success'=>true
        ]);


        $personId=$request->input('personId');
        $deviceKey=$request->input('deviceKey');
        $ip=$request->input('ip');
        $type=$request->input('type');//'face_0'为非允许时段
        $time=$request->input('time');//毫秒时间戳
        $base64_string=$request->input('imgBase64');//不包含【data:image/png;base64,】

        if(!$personId){
            info('[联动]非法请求-personId为空');
            return $return;
        }
        if(Redis::exists('faceCallBack:'.$personId.'-'.$deviceKey)){
            info('[联动]人脸识别联动打卡-频繁识别,返回-'.$request->input('personId'));
            return $return;
        }else{
            Redis::setex('faceCallback:'.$personId.'-'.$deviceKey,120,true);
            info('[联动]人脸识别联动打卡-正常添加-'.$request->input('personId'));
        }
        //从redis 查询是否为联动学生
        $info=Redis::get('PERSON:'.$personId);
        $info=json_decode($info,true);

        if($info['union_cards']){//联动学生
            $enter_arr = explode('.',$ip);
            $enter = $enter_arr[count($enter_arr)-1]%5;//3 进 4 出
            if($enter==3){//进入 正常开门发送信息
                info('[联动]联动学生进校');
                $this->normal_att($request->all());
                return $return;
            }
            else if ($enter==4){//出校 联动
                info('[联动]联动逻辑开始');
                if(Redis::exists('unionAuthFamily:'.$personId)){
                    //执行联动
                    $this->union_att($personId);
                    return $return;
                }
                info('[联动]联动不成立');
                if(Redis::exists('unionAuthStudent:'.$personId)){
                    info('[联动]等待状态中'.$personId);
                    return $return;
                }else{
                    $info=[
                        'personId'=>$personId,
                        'deviceKey'=>$deviceKey,
                        'ip'=>$ip,
                        'type'=>$type,//'face_0'为非允许时段
                        'time'=>$time//毫秒时间戳
                    ];
                    $info=json_encode($info);
                    Redis::setex('unionAuthStudent:'.$personId,600,$info);
                    info('[联动]添加联动student-redis记录，等待中'.$personId);

                    //联动学生 出 保存照片（覆盖）
                    $imgdata=base64_decode($base64_string);
                    $rt=Storage::put('public/union_auth/'.$personId.'-student.jpg',$imgdata);
                    info('[联动]联动照片student添加-'.$rt.'-'.$personId);


                }
            }
        }
        else{//非联动
            $this->normal_att($request->all());
            info('[联动]非联动人员进出校');
            return $return;
        }
    }


    //正常接口
    public function normal_att($info){
//        $client=new Client();
//        //联动 开门 考勤信息转发到正常接口
//        //联动学生 进 开门 考勤信息转发到正常接口
//        //开门
//        try{
//            $url="http://".$info['ip'].":8090/device/openDoorControl";
//            $response=$client->request('POST',$url,[
//                'form_params'=>[
//                    'pass'=>'spdc'
//                ]
//            ]);
//            $res=json_decode($response->getBody(),true);
//            info('[联动]人脸识别开门',$res);
//        }catch (GuzzleException $e){
//            info($e->getMessage());
//        }
//        //访问sync_face_test()接口
//        try {
//            $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
//                'form_params' => $info
//            ]);
//            $res=json_decode($response->getBody(),true);
//            info('[联动]模拟人脸识别上报考勤信息',$res);
//        } catch (GuzzleException $e) {
//            info($e->getMessage());
//        }
//        info('[联动]发送正常考勤',[]);
    }

    //联动认证成功
    function  union_att($personId){
//        //取出personId对应的两张照片
//        $imgs    = array();
//        $imgs[0] = env('PATH_ATT') . '/storage/app/public/union_auth/'.$personId.'-student.jpg';
//        $imgs[1] = env('PATH_ATT') . '/storage/app/public/union_auth/'.$personId.'-family.jpg';
//
//        //执行照片合成 并转换为base64 base64不包含头部
//        $source ;= array();
//        foreach ($imgs as $k => $v) {
//            switch (mime_content_type($v)){
//                case 'image/jpeg':
//                    $source[$k]['source'] = Imagecreatefromjpeg($v);
//                    break;
//                case 'image/png':
//                    $source[$k]['source'] = imagecreatefrompng($v);
//                    break;
//                default:
//                    return;
//            }
//            $source[$k]['size'] = getimagesize($v);
//        }
//        $percent=728/$source[0]['size'][0];
//        $target_h=floor($source[0]['size'][1]*$percent) >=floor($source[1]['size'][1]*$percent)
//            ?floor($source[0]['size'][1]*$percent):floor($source[1]['size'][1]*$percent);
//        $target_w=floor($source[0]['size'][0]*$percent)+floor($source[1]['size'][0]*$percent);
//        $target_img = imagecreatetruecolor($target_w,$target_h);
//        for ($i = 0; $i < 2; $i++) {
//            imagecopyresized($target_img,$source[$i]['source'],floor($source[0]['size'][0]*$percent)*$i,0,0,0,$source[$i]['size'][0]*$percent,$source[$i]['size'][1]*$percent,$source[$i]['size'][0],$source[$i]['size'][1]);
//        }
//        $target_path=env('PATH_ATT') . '/storage/app/public/union_auth/'.$personId.'.jpg';
//        Imagejpeg($target_img, $target_path);
//        $content= file_get_contents($target_path);
//        $base64=base64_encode($content);
//
//        //取出redis中对应的考勤信息
//        $att_info=Redis::get('unionAuthStudent:'.$personId);
//        $att_info=json_decode($att_info,true);
//
//        if(!$att_info)return ;
//        $client=new Client();
//        //开门
//        try{
//            $url="http://".$att_info['ip'].":8090/device/openDoorControl";
//            $response=$client->request('POST',$url,[
//                'form_params'=>[
//                    'pass'=>'spdc'
//                ]
//            ]);
//            $res=json_decode($response->getBody(),true);
//            info('[联动]人脸识别开门',$res);
//        }catch (GuzzleException $e){
//            info($e->getMessage());
//        }
//        //访问sync_face_test()接口
//
//        try {
//            $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
//                'form_params' => [
//                    'personId'=>$personId,
//                    'deviceKey'=>$att_info['deviceKey'],
//                    'ip'=>$att_info['ip'],
//                    'type'=>$att_info['type'],//'face_0'为非允许时段
//                    'time'=>$att_info['time'],//毫秒时间戳
//                    'imgBase64'=>$base64
//                ]
//            ]);
//            $res=json_decode($response->getBody(),true);
//            info('[联动]模拟人脸识别上报考勤信息',$res);
//            Redis::del('unionAuthFamily:'.$personId);
//            Redis::del('unionAuthStudent:'.$personId);
//        } catch (GuzzleException $e) {
//            info('[联动]'.$e->getMessage());
//        }
    }

    public function test_combine_pic(){
        $imgs    = array();
        $imgs[0] = "C:\\Users\\SPDC-07\\Desktop\\fsdownload\\temp-family.jpg";
        $imgs[1] = "C:\\Users\\SPDC-07\\Desktop\\fsdownload\\temp-student.jpg";
        $source = array();
        foreach ($imgs as $k => $v) {
            switch (mime_content_type($v)){
                case 'image/jpeg':
                    $source[$k]['source'] = imagecreatefromjpeg($v);
                    break;
                case 'image/png':
                    $source[$k]['source'] =  imagecreatefrompng($v);
                    break;
                default:
                    break;
            }
            $source[$k]['size'] = getimagesize($v);
        }
        $percent=1;//728/$source[0]['size'][0];
        $h_percent=$source[0]['size'][1]/$source[1]['size'][1];
        $target_h=$source[0]['size'][1];
//            floor($source[0]['size'][1]*$percent) >=floor($source[1]['size'][1]*$percent)
//            ?floor($source[0]['size'][1]*$percent):floor($source[1]['size'][1]*$percent);
        $target_w=$source[0]['size'][0]+floor($source[1]['size'][0]*$h_percent);
        echo $target_h.PHP_EOL;
        echo $source[0]['size'][0].PHP_EOL;
        echo floor($source[1]['size'][0]*$h_percent).PHP_EOL;
        echo $target_w.PHP_EOL;
        //floor($source[0]['size'][0]*$percent)+floor($source[1]['size'][0]*$percent);
        $target_img = imagecreatetruecolor($target_w,$target_h);
//        $target_h=floor($source[0]['size'][1]*$percent) >=floor($source[1]['size'][1]*$percent)
//            ?floor($source[0]['size'][1]*$percent):floor($source[1]['size'][1]*$percent);
//        $target_w=floor($source[0]['size'][0]*$percent)+floor($source[1]['size'][0]*$percent);
//        $target_img = imagecreatetruecolor($target_w,$target_h);
//        $h_percent=$source[0]['size'][1]/$source[1]['size'][1];
        imagecopyresized($target_img,$source[0]['source'],
            0,
            0,
            0,
            0,
            $source[0]['size'][0],
            $source[0]['size'][1],
            ''.$source[0]['size'][0],
            ''.$source[0]['size'][1]);
        imagecopyresized($target_img,$source[1]['source'],
            floor($source[0]['size'][0]),
            0,
            0,
            0,
            floor($source[1]['size'][0]*$h_percent),
            floor($source[1]['size'][1]*$h_percent),
            ''.$source[1]['size'][0],
            ''.$source[1]['size'][1]);
        $target_path="C:\\Users\\SPDC-07\\Desktop\\fsdownload\\combine.jpg";
        Imagejpeg($target_img, $target_path);
    }

    public function setConfig(Request $request){
        $data=$request->all();
        info('test',$data);
        $data['config']['delayTimeForCloseDoor']=intval($data['config']['delayTimeForCloseDoor']);
//        unset($data['delayTimeForCloseDoor']);
        $data['config']['displayModType']=intval($data['config']['displayModType']);
        $data['config']['identifyDistance']=intval($data['config']['identifyDistance']);
        $data['config']['saveIdentifyTime']=intval($data['config']['saveIdentifyTime']);
        $data['config']['identifyScores']=intval($data['config']['identifyScores']);
        $data['config']['multiplayerDetection']=intval($data['config']['multiplayerDetection']);
        $data['config']['recStrangerTimesThreshold']=intval($data['config']['recStrangerTimesThreshold']);
        $data['config']['recStrangerType']=intval($data['config']['recStrangerType']);
        $data['config']['ttsModStrangerType']=intval($data['config']['ttsModStrangerType']);
        $data['config']['ttsModType']=intval($data['config']['ttsModType']);
        $data['config']['whitelist']=intval($data['config']['whitelist']);
        $data['config']['saveIdentifyMode']=intval($data['config']['saveIdentifyMode']);
        $data['config']['onLightStartTime']=intval($data['config']['onLightStartTime']);
        $data['config']['onLightEndTime']=intval($data['config']['onLightEndTime']);
        $data['config']['comModType']=intval($data['config']['comModType']);
        $data['config']['recRank']=intval($data['config']['recRank']);
        info('ssss',$data['config']);

        $client=new Client();
        $response=$client->request('POST',$data['ip'].':8090/setConfig',[
            'form_params'=>[
                'pass'=>'spdc',
                'config'=>json_encode($data['config'])
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        return response()->json([
            'success'=>true,
            'msg'=>'',
            'data'=>$res
        ]);
    }

}