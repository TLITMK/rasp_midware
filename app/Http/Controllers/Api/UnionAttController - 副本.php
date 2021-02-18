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
//        info('',$request->all());
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

        info(date('Y-m-d H:i:s',$time*0.001).'-'.time().'识别回调延时'.(time()-$time*0.001).'秒，ip='.$ip);
        if((time()-$time*0.001)>5)return $return;

        if($type=='face_1'||$type=='card_1'){
            info($personId.'[联动]时段规则-不允许进出-face_1');
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
//            return $return;
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
                //检测联动是否成立
                if(Redis::exists('unionAuthFamily:'.$personId)){
                    //执行联动
                    $this->union_att($request,$start_time);
                    info('返回84');
                    return $return;
                }else{
                    info($personId.'[联动]联动不成立');
                    return $return;
                }
//                info($personId.'[联动]联动不成立');
//                if(Redis::exists('unionAuthStudent:'.$personId)){
//                    info($personId.'[联动]等待状态中'.$personId);
//                    info('返回90');
//                    return $return;
//                }
//                else{
//                    $info=[
//                        'personId'=>$personId,
//                        'deviceKey'=>$deviceKey,
//                        'ip'=>$ip,
//                        'type'=>$type,//'face_0'为非允许时段
//                        'time'=>$time//毫秒时间戳
//                    ];
//                    $info=json_encode($info);
//                    Redis::setex('unionAuthStudent:'.$personId,Redis::get('unionTime'),$info);
//                    info($personId.'[联动]添加联动student-redis记录，等待中'.$personId);
//
//                    //联动学生 出 保存照片（覆盖）
//                    $savepic_time=microtime(true);
//                    $imgdata=base64_decode($base64_string);
//                    $rt=Storage::put('public/union_auth/'.$personId.'-student.jpg',$imgdata);
//                    info($personId.'[联动]联动照片student添加-'.$rt.'-'.$personId,
//                        [
//                            '单元耗时'=>(microtime(true)-$savepic_time),
//                            '总耗时'=>(microtime(true)-$start_time)
//                        ]);
//                    info('返回109');
//                    return $return;
//
//                }
//                info('返回113');
//                return $return;
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
        //联动 开门 考勤信息转发到正常接口
        //联动学生 进 开门 考勤信息转发到正常接口
        //开门
//        $send_time2=microtime(true);
//        try{
//            $url="http://".$info['ip'].":8090/device/openDoorControl";
//            $response=$client->request('POST',$url,[
//                'form_params'=>[
//                    'pass'=>'spdc'
//                ]
//            ]);
//            $res=json_decode($response->getBody(),true);
//            info($info['personId'].'[联动]正常接口开门',
//                [
//                    '请求耗时'=>microtime(true)-$send_time2,
//                    '总耗时'=>microtime(true)-$start_time
//                ]);
//        }catch (GuzzleException $e){
//            info($e->getMessage());
//        }
        $this->face_open_door($info,$start_time);

        //访问sync_face_test()接口
        $send_time1=microtime(true);
        try {
            $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
                'form_params' => $info
            ]);
            $res=json_decode($response->getBody(),true);
            info($info['personId'].'[联动]模拟人脸识别上报考勤信息',$res);
        } catch (GuzzleException $e) {
            info($e->getMessage());
        }
        info($info['personId'].'[联动]发送正常考勤 ',
            [
                '请求耗时'=>microtime(true)-$send_time1,
                '总耗时'=>microtime(true)-$start_time
            ]);

        info('返回162');
        return response()->json([
            'result'=>1,
            'success'=>true
        ]);
    }

    //联动认证成功
    function  union_att($request,$start_time){
        $personId=$request->input('personId');
        $deviceKey=$request->input('deviceKey');
        $ip=$request->input('ip');
        $type=$request->input('type');//'face_0'为非允许时段
        $time=$request->input('time');//毫秒时间戳
        $base64_string=$request->input('imgBase64');//不包含【data:image/png;base64,】

//        Redis::setex('unionAuthFamily:'.$personId,120,true);
        //取出redis中对应的考勤信息
//        $att_info=Redis::get('unionAuthStudent:'.$personId);
//        $att_info=json_decode($att_info,true);
//        if(!$att_info){
//            info('【联动】att_info为空');
//            info('返回179');
//            return response()->json([
//                'result'=>1,
//                'success'=>true
//            ]);}
//        $send_time3=microtime(true);
        //开门
//            $url="http://".$ip.":8090/device/openDoorControl";
//            $response=$client->request('POST',$url,[
//                'form_params'=>[
//                    'pass'=>'spdc'
//                ]
//            ]);
//            $res=json_decode($response->getBody(),true);
//            $end_time=microtime(true);
//            info($personId.'[联动]联动成功-人脸识别开门',
//                [
//                    '请求耗时'=>microtime(true)-$send_time3,
//                    '总耗时'=>microtime(true)-$start_time
//                ]);
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
        $client=new Client();
        $send_time2=microtime(true);
        if(!$info['ip']){info('[联动]人脸识别开门失败-ip错误'.$info['ip']);return;}
        $iparr=explode('.',$info['ip']);
        if(count($iparr)<4){info('[联动]人脸识别开门失败-ip错误'.$info['ip']);return;}
        $ipend=$iparr[3];
        $enter=$ipend%5==3?true:false;
        $is_open_door=$this->is_open_by_notify_time($enter);
        if(!$is_open_door){
            info($info['personId'].'[联动]考勤推送时段-时段判断不开门'.$is_open_door);
            return;
        }

        try{
            $url="http://".$info['ip'].":8090/device/openDoorControl";
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>'spdc'
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            info($info['personId'].'[联动]正常接口开门',
                [
                    '请求耗时'=>microtime(true)-$send_time2,
                    '总耗时'=>microtime(true)-$start_time
                ]);
        }catch (GuzzleException $e){
            info($e->getMessage());
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
        $response=$client->request('post',$url,[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID',false)
            ]
        ]);
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



}