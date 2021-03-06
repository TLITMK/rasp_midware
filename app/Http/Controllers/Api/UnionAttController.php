<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2020/2/4
 * Time: 16:54
 */

namespace App\Http\Controllers\Api;

use App\Services\Tools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class UnionAttController extends Controller
{
    use Tools;
    public function face_union_auth(Request $request){
        info('',$request->all());
        $start_time=microtime(true);
        $return=response()->json([
            'result'=>1,
            'success'=>true
        ]);
        info('test',array_keys($request->all()));


        $personId=$request->input('personId');
        $deviceKey=$request->input('deviceKey');
        $ip=$request->input('ip');
        $type=$request->input('type');//'face_0'人员在passtime权限时间内 允许
        $time=$request->input('time');//毫秒时间戳
        $base64_string=$request->input('imgBase64');//不包含【data:image/png;base64,】
        $now=time();
        $uptime=$time*0.001;
        $time_span=$now-$uptime;
        info($now.'-'.$uptime.'识别回调延时'.($time_span).'秒ip='.$ip);
        if($time_span>2){
            info('识别回调延时大于2秒，返回'.$ip);
            return $return;

        }


        if(!$personId ){
            info($personId.'[联动]非法请求-personId为空'.(microtime(true)-$start_time));
            info('返回39');
            return $return;
        }
        if( strstr($personId,'STRANGE')){
            info($personId.'[联动]非法请求-陌生人'.(microtime(true)-$start_time));
            info('返回44');
            return $return;
        }
        if(Redis::exists('faceCallback:'.$personId.'-'.$deviceKey)){
            info($personId.'[联动]人脸识别联动打卡-频繁识别,返回-'.$request->input('personId'));
//            //开门
//            $client=new Client();
//            try{
//                $url="http://".$ip.":8090/device/openDoorControl";
//                $response=$client->request('POST',$url,[
//                    'form_params'=>[
//                        'pass'=>'spdc'
//                    ]
//                ]);
//                $res=json_decode($response->getBody(),true);
//                $end_time=microtime(true);
//                info($personId.'[联动]人脸识别频繁识别开门,耗时='.($end_time-$start_time),$res);
//            }catch (GuzzleException $e){
//                info($e->getMessage());
//            }
            return $return;
        }else{
            Redis::setex('faceCallback:'.$personId.'-'.$deviceKey,2,true);
            info($personId.'[联动]人脸识别联动打卡-正常添加-'.$request->input('personId'));
        }
        //从redis 查询是否为联动学生
        $info=Redis::get('PERSON:'.$personId);
        $info=json_decode($info,true);

        //连动时段判断
        $time_time=microtime(true);
        if(isset($info['union_time']) && $info['union_time']){
            $timestr=$info['union_time'];
            $now=date('H:i');
            $timearr=json_decode($timestr,true);
            $is_in_time=false;
            foreach($timearr as $time){
                if($now>$time['start_time'] && $now<$time['end_time']){
                    info('联动时段判断-true',[
                        'time'=>$time,
                        '单元耗时'=>microtime(true)-$time_time,
                        '总耗时'=>microtime(true)-$start_time
                    ]);
                    $is_in_time=true;
                    break;
                }
            }
        }
        if($info['union_cards'] && $is_in_time){//联动学生
            $enter_arr = explode('.',$ip);
            $enter = $enter_arr[count($enter_arr)-1]%5;//3 进 4 出
            if($enter==3){//进入 正常开门发送信息
                info($personId.'[联动]联动学生进校');
                $this->normal_att($request->all(),$start_time);
                info('返回75');
                return $return;
            }
            else if ($enter==4){//出校 联动
                info($personId.'[联动]联动逻辑开始');
                $camera_ips=explode(',',env('UNION_ATT'));
                $success=false;
                foreach ($camera_ips as $cip){

                }
                //检测联动是否成立
                if(Redis::exists('unionAuthFamilySoft:'.$personId)){
                    //执行联动
                    $this->union_att($request,$start_time);
                    info('返回84');
                    return $return;
                }else{
                    $this->show_content($ip,'家长未登记，请到一旁等候');
                    info($personId.'[联动]联动不成立');
                    $this->alert_att($request,$start_time);
                    return $return;
                }

            }
            info('返回116');
            return $return;
        }
        else{//非联动 或 非联动时段
            $this->normal_att($request->all(),$start_time);
            info($personId.'[联动]非联动人员进出校 或 非联动时段人员进出校');
            info('返回122');
            return $return;
        }
        info('返回125');
        return $return;
    }


    //正常接口
    public function normal_att($info,$start_time){
        $client=new Client();

        if(strstr($info['personId'],'t')){//老师
            info('[联动]考勤推送时段-数段判断开门-教师-开门');
        }
        else{//学生

            if(!$info['ip']){info('[联动]人脸识别开门失败-ip错误'.$info['ip']);return response()->json([
                'result'=>1,
                'success'=>true
            ]);}
            $iparr=explode('.',$info['ip']);
            if(count($iparr)<4){info('[联动]人脸识别开门失败-ip错误'.$info['ip']);return response()->json([
                'result'=>1,
                'success'=>true
            ]);}
            $ipend=$iparr[3];
            $enter=$ipend%5==3?true:false;
            $is_open_door=$this->is_open_by_notify_time($enter);
            if(!$is_open_door){
                info($info['personId'].'[联动]考勤推送时段-时段判断不开门-也不推送'.$is_open_door);
                return response()->json([
                    'result'=>1,
                    'success'=>true
                ]);
            }
        }
        $this->face_open_door($info,$start_time);

        //访问sync_face_test()接口
        Redis::setex('NORMAL_ATT_INFO:'.$info['personId'],86400,json_encode($info));


//        info($info['personId'].'[联动]发送正常考勤 ',
//            [
//                '请求耗时'=>microtime(true)-$send_time1,
//                '总耗时'=>microtime(true)-$start_time
//            ]);

        info('返回162');
        return response()->json([
            'result'=>1,
            'success'=>true
        ]);
    }
    //家长未登记发送通知
    function alert_att($request ,$start_time){
        $person_id=$request->input('personId');
        $time_str=date('Y-m-d H:i:s');
//        Redis::set('unionAlertInfo:'.$personId,$time_str);
        try {
            $send_time4=microtime(true);
            $client=new Client();
            $response=$client->request('post',env('CURL_URL').'/union/face/send_union_unregister',[
                'timeout'=>1,
                'form_params' => [
                    'person_id'=>$person_id,
                    'time_str'=>$time_str,//string Y-m-d H:i:s
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            info($person_id.'[联动]未登记通知-上报未登记通知',$res);
            info('返回UnionAttController236',
                [
                    '上报耗时'=>microtime(true)-$send_time4,
//                        '总耗时'=>microtime(true)-$timestamp
                ]);
        }
        catch (\Throwable $e) {
            info($person_id.'[联动]未登记通知-'.$e->getMessage());
        }
    }

    //联动认证成功
    function  union_att($request,$start_time){
        $personId=$request->input('personId');
        $deviceKey=$request->input('deviceKey');
        $ip=$request->input('ip');
        $type=$request->input('type');//'face_0'允许
        $time=$request->input('time');//毫秒时间戳
        $base64_string=$request->input('imgBase64');//不包含【data:image/png;base64,】

        $this->face_open_door($request->all(),$start_time);
        $info_arr=[
            'personId'=>$personId,
            'deviceKey'=>$deviceKey,
            'ip'=>$ip,
            'type'=>$type,//'face_0'为非允许时段
            'time'=>$time,//毫秒时间戳
            'imgBase64'=>$base64_string
        ];
        Redis::set('unionSuccessInfo'.$personId,json_encode($info_arr));
        info('[联动成功]-保存成功信息-'.'unionSuccessInfo'.$personId);
    }

    function face_open_door($info,$start_time){
        $type=$info['type'];
        if($type=='face_1'||$type=='card_1'){
            info($info['personId'].'[开门信号]时段规则-不允许进出-face_1'.$info['ip']);
            return;
        }
        $client=new Client();
        $send_time2=microtime(true);


        try{
            $url="http://".$info['ip'].":8090/device/openDoorControl";
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>'spdc'
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            info($info['personId'].'[联动]开门信号'.$info['ip'],
                [
                    '请求耗时'=>microtime(true)-$send_time2,
                    '总耗时'=>microtime(true)-$start_time
                ]);
        }catch (GuzzleException $e){
            info($info['personId'].'[联动]开门信号'.$e->getMessage());
        }
    }


    public function is_open_by_notify_time($enter){
        $notify_time=Redis::get('ATT_NOTIFY_TIME');
        $time_res=json_decode($notify_time,true);
        $time_arr=$time_res['data'];
        if(!$time_arr){
            return true;
        }else{
            $now=time();
            $is_in_time=false;
            foreach ($time_arr as $time){
                if(strtotime($time['start_time'])< $now && $now < strtotime($time['end_time'])){//在时段
//                $now_time_end=strtotime($time['end_time']);
                    $is_in_time=true;
                    break;
                }
            }
            if($is_in_time){//在时段内
                return true;
            }
            else{//不在时段只进不出
                if($enter){//进
                    return true;
                }else{//出
                    return false;
                }
            }
        }
//        if($notify_time)
    }

    public function sync_att_notify_time(){
        $client=new Client();
        $url=env('CURL_URL').'/get_att_notify_time';
        try{
            $response=$client->request('post',$url,[
                'form_params'=>[
                    'school_id'=>env('SCHOOL_ID',false)
                ]
            ]);
        }catch (\Exception $e){
            return ['error_msg'=>$e->getMessage()];
        }
        $res=json_decode($response->getBody(),true);
        Redis::set('ATT_NOTIFY_TIME',json_encode($res));
        return $res;
    }

    public function combine_pic($url_1,$base64_1,$url_2,$base64_2,$res_url){
        $timestamp=microtime(true);
        file_put_contents($url_1, base64_decode($base64_1));
        file_put_contents($url_2, base64_decode($base64_2));
        $imgs    = array();
        $imgs[0] = $url_1;
        $imgs[1] = $url_2;
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
        $target_w=$source[0]['size'][0]+floor($source[1]['size'][0]*$h_percent);
        $target_img = imagecreatetruecolor($target_w,$target_h);
//        for ($i = 0; $i < 2; $i++) {
//            imagecopyresized($target_img,$source[$i]['source'],floor($source[0]['size'][0]*$percent)*$i,0,0,0,$source[$i]['size'][0]*$percent,$source[$i]['size'][1]*$percent,$source[$i]['size'][0],$source[$i]['size'][1]);
//        }

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
            $source[1]['size'][0],
            $source[1]['size'][1]);
        $target_path=$res_url;
        Imagejpeg($target_img, $target_path);
        $content= file_get_contents($target_path);
        $base64=base64_encode($content);
        info('[联动异步]照片合成',['单元耗时'=>microtime(true)-$timestamp]);
        return [
            'base64'=>$base64,
            'url'=>$res_url
        ];
    }
    public function show_content($ip,$content,$speak='false'){
        $client=new Client();
        $url=$ip.':8090/api/v2/device/showMessage';
        $response=$client->request('POST',$url,[
            'form_params'=>[
                'pass'=>'spdc',
                'content'=>$content,
                'speak'=>$speak
            ]
        ]);
        echo $response->getBody();
    }


}