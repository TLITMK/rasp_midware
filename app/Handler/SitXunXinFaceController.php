<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-11-30
 * Time: 14:48
 */

namespace App\Handler;


use App\Services\Helper;
use App\Services\Tools;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SitXunXinFaceController
{
    public function sync_person_task($time_limit){
        $client=new Client();
        $response = $client->request('POST', 'https://d.clovedu.cn/api/v2/xxface/get_persons_task', [
            'form_params' => [
                'user_id'=>env('USER_ID'),
                'school_id' => env('SCHOOL_ID'),
                'rasp_num'=>env('TERMINAL_NUM',false)
            ],
        ]);
        $res = json_decode($response->getBody(), true);
        if($res['code']){
            info($res['msg']);
            return 999;
        }
        info(count($res['data']).'个人员同步任务');

        $con=new FaceDetectController();
        $start_time=microtime(true);
        foreach ($res['data'] as $item){
            $info_str='';
            $person_id=$item['person_id'];
            $ip=$item['terminal_ip'];
            if(!$ip)continue;
            $person_json=json_encode([
                'id'=>$person_id,
                'name'=>$item['name'],
                'idcardNum'=>$item['card_id']
            ]);
            switch($item['func']){
                case 'ADD':
                    $hasPerson = $con->personFind($item['terminal_ip'], $person_id);
                    if ($hasPerson['success'] && count($hasPerson['data'])) {
                        $info_str.='ADD-更新人员-'.$ip.$person_json.json_encode($hasPerson);
                        $bool = $con->personUpdate($ip, $person_json)['success'];
                    } else {
                        $info_str.='ADD-添加人员-'.$ip.$person_json.json_encode($hasPerson);
                        $bool = $con->personCreate($ip, $person_json)['success'];
                    }
                    break;
                case 'DEL':
                    $re = $con->delPerson($ip, $person_id);
                    $bool=$re['success'];
                    $info_str.='DEL-删除人员-'.$ip.json_encode($re);
                    break;
                case 'UPD':
                    $bool = $con->personUpdate($ip, $person_json)['success'];
                    $info_str.='UPD-更新人员-'.$ip.$person_json;
                    break;
                default:
                    $bool=false;

            }

            $response = $client->request('POST', 'https://d.clovedu.cn/api/v2/xxface/set_person_task_status', [
                'form_params' => [
                    'id' => $item['id'],
                    'status'=>$bool?1:-1,
                ],
            ]);
            $res1 = json_decode($response->getBody(), true);
            info($info_str,$res1);
            $nowspan=microtime(true)-$start_time;
            if($time_limit>0&&$nowspan>$time_limit){
                info('超时退出',['nowspan'=>$nowspan,'time_limit'=>$time_limit]);
                break;
            }
        }
        return count($res['data']);
    }
    public function sync_photo_task($time_limit){
        $client=new Client();
        $response = $client->request('POST', 'https://d.clovedu.cn/api/v2/xxface/get_photo_task', [
            'form_params' => [
                'user_id'=>env('USER_ID'),
                'school_id' => env('SCHOOL_ID'),
                'rasp_num'=>env('TERMINAL_NUM',false)
            ],
        ]);
        $res = json_decode($response->getBody(), true);
        if($res['code']){
            info($res['msg']);
            return 999;
        }
        info(count($res['data']).'个同步照片任务');
        $status_data=[];

        $start_time=microtime(true);
        $con=new FaceDetectController();
        foreach ($res['data'] as $faceimg) {
            $ip=$faceimg['terminal_ip'];
            if(!$ip)continue;
            $fail_msg = '';
            $person_id = $faceimg['person_id'];
            $index=$faceimg['photo_index'];
            //先下载
            if($faceimg['photo_url']){
//                $arr=json_decode($faceimg['photo_url'], true);

                $url = $faceimg['photo_url'];
            }else{
                $url='empty_json';
            }
            info('test   ' . $url);
            $base64 = $con->getBase64ByURL($url);
            if (!$base64) {
                $fail_msg = '获取线上base64失败！';
                info('照片访问失败，跳过该人员' . $faceimg['person_id'],['url'=>$url]);
                $status_data[$faceimg['id']]=[
                    'id'=>$faceimg['id'],
                    'status'=>-1,
                    'fail_msg'=>$fail_msg
                ];
                $this->set_photo_status($faceimg['id'],$status_data[$faceimg['id']]['status'],$status_data[$faceimg['id']]['fail_msg']);
                continue;
            }
            $face_ids=['','1','2'];

            $con->del_face_unit2($faceimg,$ip);
            $rt = $con->createByBase64($ip, $person_id,$person_id.$face_ids[$index], $base64);
            if (!$rt['success']) {

                $status_data[$faceimg['id']]=[
                    'id'=>$faceimg['id'],
                    'status'=>-1,
                    'fail_msg'=>$rt['msg']
                ];
                info($faceimg['person_id'] . '照片注册base64失败' . $fail_msg);

            }else{
                $status_data[$faceimg['id']]=[
                    'id'=>$faceimg['id'],
                    'status'=>1,
                    'fail_msg'=>''
                ];
            }
            $this->set_photo_status($faceimg['id'],$status_data[$faceimg['id']]['status'],$status_data[$faceimg['id']]['fail_msg']);
//            sleep(0.1);
            $nowspan=microtime(true)-$start_time;
            if($time_limit>0&&$nowspan>$time_limit){
                info('超时退出',['nowspan'=>$nowspan,'time_limit'=>$time_limit]);
                break;
            }

        }
        return count($res['data']);

    }
    public function sync_permission_task($time_limit){
        $client=new Client();
        $response = $client->request('POST', 'https://d.clovedu.cn/api/v2/xxface/get_permission_task', [
            'form_params' => [
                'user_id'=>env('USER_ID'),
                'school_id' => env('SCHOOL_ID'),
                'rasp_num'=>env('TERMINAL_NUM',false)
            ],
        ]);
        $res = json_decode($response->getBody(), true);
        if($res['code']){
            info($res['msg']);
            return 999;
        }
        info(count($res['data']).'个同步时段任务');

        $con=new FaceDetectController();
        $start_time=microtime(true);
        foreach ($res['data'] as $faceimg) {
            $ip=$faceimg['terminal_ip'];
            $person_id=$faceimg['person_id'];
            $time_str=$faceimg['time_str'];
            if(!$ip)continue;
            $ret=$con->set_time_permission($ip,'spdc',$person_id,$time_str);
            if($ret['success']){
                $response1=$client->request('post','https://d.clovedu.cn/api/v2/xxface/set_permission_task_status',[
                    'timeout'=>1,
                    'form_params'=>[
                        'id'=>$faceimg['id'],
                        'status'=>1,
                        'fail_msg'=>$ret['msg'],
                    ]
                ]);
                info('人脸识别设备允许时段更新-更新任务表- 状态(1)'.$response1->getBody());
            }else{
                $response1=$client->request('post','https://d.clovedu.cn/api/v2/xxface/set_permission_task_status',[
                    'timeout'=>1,
                    'form_params'=>[
                        'id'=>$faceimg['id'],
                        'status'=>-1,
                        'fail_msg'=>$ret['msg'],
                    ]
                ]);
                info('人脸识别设备允许时段更新-更新任务表- 状态失败(-1)'.$response1->getBody());
            }
            $nowspan=microtime(true)-$start_time;
            if($time_limit>0&&$nowspan>$time_limit){
                info('超时退出',['nowspan'=>$nowspan,'time_limit'=>$time_limit]);
                break;
            }

        }
//        if(!count($res['data']))return false;
//        $response1=$client->request('post','https://d.clovedu.cn/api/set_photo_task_status', [
//            'form_params' => [
//                'data'=>$status_data
//            ],
//        ]);
        return count($res['data']);
    }






    function set_photo_status($id,$status,$fail_msg){
        $client=new Client();
        $response1=$client->request('post','https://d.clovedu.cn/api/v2/xxface/set_photo_task_status', [
            'form_params' => [
                'id'=>$id,
                'status'=>$status,
                'fail_msg'=>$fail_msg
            ],
        ]);
        $res1=json_decode($response1->getBody(),true);
        info($response1->getBody());
    }

}