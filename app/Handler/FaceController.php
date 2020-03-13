<?php
namespace App\Handler;

use App\Handler\ClientHandler;
use GuzzleHttp\Client;

class FaceController
{
    public function get_task($type){
        if(!env('FACE')){
            return;
        }
        $client = new Client();
        try {
            info('获取task任务 type='.$type);
            $response = $client->request('POST',env('CURL_URL').'/get_task', [
                'form_params' => [
                    'type' => $type,
                    'school_id' => env('SCHOOL_ID'),
                ]
            ]);
            $rt = json_decode($response->getBody(),true);
            info('获取task任务 结果:',[$rt]);
            if($rt['code'] == 0){
                switch ($type){
                    case 1:  //人员注册
                        info('执行task-人员注册');
                        $this->person($rt['data']);
                        break;
                    case 2: //照片注册
                        info('执行task-照片注册');
                        $this->photo($rt['data']);
                        break;
                    case 3: //设置密码
                        info('执行task-设置密码');
                        self::setpass($rt['data']);
                        break;
                    case 4:
                        info('执行task-设备配置');
                        self::set_face_config($rt['data']);
                        break;
                    case 6:
                        info('执行task-删除人员');
                        $this->del_person($rt['data']);
                        break;
                    case 7: //有线网络配置
                        info('执行task-局域网络配置');
                        $this->set_net($rt['data']);
                        break;
                    case 8:
                        info('执行task-设置时段权限');
                        $this->set_face_time_permission($rt['data']);
                        break;
                    case 9:
                        info('执行task-设置设备识别回调');
                        $this->set_face_identify_callback($rt['data']);
                        break;
                    case 10:
                        info('执行task-删除所有人员时段权限');
                        $this->del_face_time_permission($rt['data']);
                        break;
                    case 11:
                        info('执行taks-删除照片但不删除人员');
                        $this->del_face_photo($rt['data']);
                        break;
                }
            }
        }catch (\Exception $e) {
            info('get_task 异常'.$e->getMessage());
        }
    }
    //设置人脸识别设备 识别分数
    public function set_face_config($data){
        $del_task_ids=[];
        foreach ($data as $k=>$item){
            $terminal=$item['terminal'];
            if(!$terminal){info('设备不存在');continue;}
            else{
                if($terminal['status']!=1){
                    info($terminal['ip'].'设备掉线');
                    continue;
                }
                $handler=new ClientHandler();
                $rt=$handler->set_face_config($terminal['ip'],'spdc',$item['data']);
                if($rt['success']){
                    array_push($del_task_ids,$item['id']);
                }
            }
        }
        if(count($del_task_ids)){
            $this->del_task($del_task_ids,1);
        }
    }

    //设置人脸识别设备 识别回调
    public function set_face_identify_callback($data){
        $del_task_ids=[];
        foreach($data as $k=>$item){
            $terminal=$item['terminal'];
            if(!$terminal){info('设备不存在'); continue;}
            else{
                if($terminal['status']!=1){
                    info($terminal['ip'].'设备掉线');
                    continue;
                }
                $handler=new ClientHandler();
                $rt=$handler->set_identify_callback($terminal['ip'],'spdc',$item['data']);
                if($rt['success']){

                    array_push($del_task_ids,$item['id']);
                }
            }
        }
        if(count($del_task_ids)){
            $this->del_task($del_task_ids,1);
        }
    }
//设置人脸识别设备 时段权限
    public function set_face_time_permission($data){
        $del_task_ids=[];
        foreach ($data as $item) {
            $student=$item['student'];
            $terminal=$item['terminal'];
            if(!$student || !$terminal){
                continue;
            }else{
                if($terminal['status']!=1){
                    info($terminal['ip'].'设备掉线');
                    continue;
                }
                $handler=new ClientHandler();
                $rt=$handler->set_time_permission($terminal['ip'],'spdc',$student['id'],$item['data']);
                if($rt['success']){
                    array_push($del_task_ids,$item['id']);
                }
            }
        }
        if(count($del_task_ids)){
            $this->del_task($del_task_ids,1);
        }
    }
    //删除所有人员时段权限
    public function del_face_time_permission($data){
        $del_task_ids=[];
        $handler=new ClientHandler();
        foreach($data as $item){
            $terminal=$item['terminal'];
            $rt=$handler->del_time_permission($terminal['ip'],'spdc');
            if($rt['success']){
                array_push($del_task_ids,$item['id']);
            }
        }
        if(count($del_task_ids)){
            $this->del_task($del_task_ids,1);
        }
    }
    //
    public function del_face_photo($data){
        //faceimg
        $success_idx=[];
        $fail_idx=[];
        //facetask
        $del_task_ids=[];
        $handler=new ClientHandler();
        foreach($data as $key=>$item){
            $terminal=$item['terminal'];
            $rt=$handler->delPersonFace($terminal['ip'],$item['student_id']);
            if($rt['success']){
                //facetask
                array_push($del_task_ids,$item['id']);
                array_push($success_idx,$key);
            }else{
                array_push($fail_idx,$key);
            }

        }
        info('keys ',$success_idx);
        //faceimg 成功
        $student_ids=[];
        $client=new Client();
        foreach($success_idx as $idx){
            $v=$data[$idx];
            array_push($student_ids,$v['student_id']);
        }
        $student_ids=array_unique($student_ids);
        info('unique ',$student_ids);
        foreach($student_ids as $student_id){
            info('student_id '.$student_id);
            info('school_id '.env('SCHOOL_ID'));
            $response=$client->request('POST',env('CURL_URL').'/update_faceimgs_operations',[
                'form_params'=>[
                    'school_id'=>env('SCHOOL_ID',''),
                    'student_id'=>$student_id
            ]
            ]);
            $res=json_decode($response->getBody(),true);
            info($student_id.'OPERATE FACE_IMG 结果',$res);
        }

        //失败
        $fail_info=[];
        foreach($fail_idx as $idx){
            $student_id=$data[$idx]['student_id'];
            $ip=$data[$idx]['ip'];
            $fail_info[$student_id][]=$ip;
        }
        foreach($fail_info as $stu_id=>$ips){

        }

        //facetask记录
        if(count($del_task_ids)){
            $this->del_task($del_task_ids,1);
        }


    }
    //人员注册
    public function person($data){
        $task_ids = [];
        foreach($data as $item){
            $student = $item['student'];
            $terminal = $item['terminal'];
            if(!$student || !$terminal){
                continue;
            }else{
                info($terminal['status'].'状态');
                if($terminal['status'] != 1){
                    info($terminal['status'].'状态');
                    info($terminal['ip'].'设备掉线');
                    continue;
                }
                //$send_data = '{"id":"'.$student['id'].'","idcardNum":"'.$student['card_id']?$student['card_id']:''.'","name":"'.$student['stu_name'].'"}';
                $handler = new ClientHandler();
                $data = $handler->person_find($terminal['ip'],$student['id'],$terminal['new_pass']);
                if($data['success']){
                    info($student['stu_name'].$student['id'].'人员已注册,请勿重复注册');
                    continue;
                }

                $arr = [
                    'id'=>$student['id'],
                    'idcardNum'=>$student['card_id']?$student['card_id']:'',
                    'name'=>$student['stu_name'],
                ];
                $send_data = json_encode($arr);
                $handler = new ClientHandler();
                $res = $handler->person_create($terminal['ip'],$send_data,$terminal['new_pass']);
                if($res['success']){
                    array_push($task_ids,$item['id']);
                    info('人员注册操作成功!');
                }else{
                    continue;
                    info($res['msg']);
                }

            }
        }
        if(count($task_ids)){
            $this->del_task($task_ids,1);
        }

    }

    //照片注册
    public function photo($data){
        $task_ids= [];
        foreach($data as $item){

            $student = $item['student'];
            $terminal = $item['terminal'];
            if($terminal['status'] != 1){
                info($terminal['status'].'状态');
                info($terminal['ip'].'设备掉线');
                continue;
            }
            info('ip------',[$terminal['ip']]);

            $arr_ip = ['192.168.30.8','192.168.30.9'];
            if(env('NOTUPDATE') && in_array($terminal['ip'],$arr_ip)) {
                if(!in_array($item['id'],$task_ids)){
                    array_push($task_ids,$item['id']);
                }
                continue;
            }

//            $handler = new ClientHandler();
//            $data = $handler->person_find($terminal['ip'],$student['id'],$terminal['new_pass']);
//            if(!$data['success']){

                $arr = [
                    'id'=>$student['id'],
                    'idcardNum'=>$student['card_id']?$student['card_id']:'',
                    'name'=>$student['stu_name'],
                ];
                $send_data = json_encode($arr);
                $handler = new ClientHandler();
                $data = $handler->getFaceImg($terminal['ip'],$student['id'],$terminal['new_pass']);
                if($data['success']){
                    if( count($data['data'])){//如果有照片
                        if(!in_array($item['id'],$task_ids)){
                            array_push($task_ids,$item['id']);
                        }
                        info('人员已注册照片');
                        continue;
                    }
                    //如果没有照片

                    $res = $handler->person_create($terminal['ip'],$send_data,$terminal['new_pass']);//人员注册
                    info('人员注册-返回-',$res);
                    if(count($student) && count($terminal)){
                        $handler = new ClientHandler;
                        $res = $handler->face_create($terminal['ip'],$student['id'],$terminal['new_pass']);
                        if($res['success'] && $res['data']){
                            if(!in_array($item['id'],$task_ids)){
                                array_push($task_ids,$item['id']);
                            }
                            info('照片注册成功!');
                        }else{
                            $imgs=json_decode($student['photo_url'])[0];
//                            if(is_array($temp)){
//
//                            }else{
//
//                            }
//                            $imgs = [0]
                            ;//从三叶草获取照片
                            if(!$imgs){info('照片注册-'.$student['id'].'-没有照片！'); return ;}
                            $url='https://app.clovedu.cn'.$imgs;
                            $res_url=$handler->createByurl($terminal['ip'],$terminal['new_pass'],$student['id'],'',$url);
                            if($res_url['success']&&$res_url['data']){
                                if(!in_array($item['id'],$task_ids)){
                                    array_push($task_ids,$item['id']);
                                }
                                info('照片注册成功!');
                            }else{

                                info('照片注册失败！！'.$res['msg']);
                            }

                        }

                    }
                }
        }
        if(count($task_ids)){
            info($task_ids);
            $this->del_task($task_ids,2);
        }
    }

    //人员信息删除
    public function del_person($data){
        $task_ids = [];
        foreach($data as $item){
            $terminal = $item['terminal'];
            $student = $item['student'];
            if(!$terminal){
                info('人员删除-设备信息不完整',$terminal);
                continue;
            }else{
                if($terminal['status'] != 1){
                    info($terminal['ip'].'设备掉线');
                    continue;
                }
                if($item['student_id'] == '-1'){
                    $handler = new ClientHandler;
                    $res = $handler->person_delete($terminal['ip'],-1,'-1',$terminal['new_pass']);
                }else{
                    $handler = new ClientHandler;
                    $res = $handler->person_delete($terminal['ip'],$student['id'],'2',$terminal['new_pass']);
                }
                if($res['success']){
                    info($res['msg']);
                    array_push($task_ids,$item['id']);
                }else{
                    info($res['msg']);
                }

            }
        }
        if(count($task_ids)){
            $this->del_task($task_ids,3);
        }
    }
    //设置密码
    public function setpass($data){
        $task_ids = [];
        foreach($data as $item){
            $terminal = $item['terminal'];
            if(!$terminal){
                continue;
            }else{

                $res = (new ClientHandler)->set_pwd($terminal['ip'],$terminal['new_pass'],$terminal['old_pass']);
                if($res['success']){
                    array_push($task_ids,$item['id']);
                }else{
                    info($res['msg']);
                }

            }
        }
        if(count($task_ids)){
            $this->del_task($task_ids,3);
        }
    }

    //有线网络配置
    public function set_net($data){
        $task_ids = [];
        foreach($data as $item){
            $terminal = $item['terminal'];
            if(!$terminal){
                continue;
            }else{
                $handler= new ClientHandler;
                $res = $handler->setNetInfo($terminal['ip'],$terminal['new_pass']);
                if($res['success']){
                    array_push($task_ids,$item->id);
                }else{
                    info($res['msg']);
                }

            }
        }
        if(count($task_ids)){
            $this->del_task($task_ids,7);
        }
    }

    //请求三叶草服务端删除执行完的任务
    public function del_task($task_ids,$type){
//        try {
            $client = new Client();
            $response = $client->request('POST',env('CURL_URL').'/del_task', [
                'form_params' => [
                    'task_ids' => implode(',',$task_ids),
                    'type' => $type,
                    'school_id' => env('SCHOOL_ID'),
                ]
            ]);
            $resp = json_decode($response->getBody(),true);
            info($resp);
            if($resp['code']){
                info('任务删除失败!');
            }else{
                info('任务删除成功!');
            }
//        }catch (\Exception $e) {
//            info('上报任务删除异常');
//        }
    }


    //调度任务  每天0点同步人脸
    public function sync_face(){
        if(!env('FACE')){
            return ;
        }
        $school_id = env('SCHOOL_ID',0);

        $client = new Client();
        $response = $client->request('POST',env('CURL_URL').'/getstu/list', [
            'form_params' => [
                'school_id' => $school_id,
            ]
        ]);

        $resp = json_decode($response->getBody(),true);
        if(!$resp['success']){
            info($resp['msg']);
            return ;
        }else{
            foreach($resp['data'] as $key =>$val){
                info('----------------------------');
                info($val);
                for($i=env('DOOR_NUM');$i>=1;$i--) {
                    //if($i != $door_num[1]){
                    $en = [1, 2];
                    foreach ($en as $k => $v) {
                        $ip = $this->get_face_ip($i,$v);
                        $handler = new ClientHandler;
                        $resp = $handler->getFaceImg($ip,$val['id'],'spdc');
                        if($resp['success']){
                            if(count($resp['data'])){//如果有照片
                                for($a=env('DOOR_NUM');$a>=1;$a--) {
                                    $enter = [1, 2];
                                    foreach ($enter as $c => $s) {
                                        $sync_ip = $this->get_face_ip($a,$s);
                                        $arr = [
                                            'id'=>$val['id'],
                                            'idcardNum'=>$val['card_id'],
                                            'name'=>$val['stu_name'],
                                        ];
                                        $send_data = json_encode($arr);
                                        $handler = new ClientHandler;
                                        //人员注册
                                        $rt = $handler->person_create($sync_ip,$send_data,'spdc');
                                        if($rt['success']){
                                            //照片注册url
                                            $r = $handler->createByurl($sync_ip,'spdc',$val['id'],$resp['data'][0]['faceId'],$resp['data'][0]['path']);
                                            info($r);
                                        }
                                    }
                                }

                            }else{
                                continue;
                            }
                        }
                    }
                }
            }
        }
    }

    public function get_face_ip($door_num,$enter){
        $ip = env('VIDEO_IP');
        $i = $door_num*5 + ($enter+2);
        return $ip.$i;
    }

    public function sync_face_serv(){
        if(!env('FACE')){
            return ;
        }
        $school_id = env('SCHOOL_ID',0);

        $client = new Client();
        info('sync_face_serv-开始获取10个学生');
        try{
            $response = $client->request('POST',env('CURL_URL').'/getstu/list', [
                'form_params' => [
                    'school_id' => $school_id,
                ]
            ]);
        }catch (\Exception $e){
            info('获取10个学生-访问/getstu/list异常!-'.$e->getMessage());
            return;
        }


        $resp = json_decode($response->getBody(),true);
        if(!$resp['success']){
            info($school_id.'sync_face_serv-获取10个学生失败'.$resp['msg']);
            return ;
        }else{
            info($school_id.'sync_face_serv-获取10个学生成功');
            info($school_id.'sync_face_serv-开始遍历学生和设备');
            foreach($resp['data'] as $key =>$val){
                for($i=env('DOOR_NUM');$i>=1;$i--) {
                    $en = [1, 2];
                    foreach ($en as $k => $v) {
                        $ip = $this->get_face_ip($i,$v);
                        $handler = new ClientHandler;
                        info('----------------------------------------');
                        info('遍历-照片查询-学生id-'.$val['id'].'-开始');
                        $resp = $handler->getFaceImg($ip,$val['id'],'spdc');
                        if($resp['success']){
                            if(count($resp['data'])){//如果有照片

                                file_put_contents(env('PATH_ATT').'/storage/app/public/face/'.$val['id'].'/0.jpg',file_get_contents($resp['data'][0]['path']));
                                $file = fopen(env('PATH_ATT').'/storage/app/public/face/'.$val['id'].'/0.jpg', 'r');
                                $client = new Client();
                                try{
                                    $response = $client->request('POST', env('CURL_URL').'/face/sync', [
                                        'verify'=>false,
                                        'multipart' => [
                                            [
                                                'name'     => 'image',
                                                'contents' => $file
                                            ],
                                            [
                                                'name'     => 'student_id',
                                                'contents' => $val['id'],
                                            ],
                                            [
                                                'name'=>'ip',
                                                'contents'=>$ip
                                            ],
                                        ]
                                    ]);
                                }catch (\Exception $e){
                                    info('遍历-照片查询-异常!(有照片)'.$e->getMessage());
                                    continue;
                                }

                                info('遍历-照片查询-成功-调用/face/sync(有照片)');
                            }else{
                                $client = new Client();
                                try{
                                    $response = $client->request('POST', env('CURL_URL').'/face/sync', [
                                        'verify'=>false,
                                        'multipart' => [
                                            [
                                                'name'     => 'image',
                                                'contents' => ''
                                            ],
                                            [
                                                'name'     => 'student_id',
                                                'contents' => $val['id'],
                                            ],
                                            [
                                                'name'=>'ip',
                                                'contents'=>$ip
                                            ],
                                        ]
                                    ]);
                                }catch(\Exception $e){
                                    info('遍历-照片查询-异常!(无照片)');
                                    continue;
                                }

                                info('遍历-照片查询-失败-调用/face/sync(无照片)');
                            }
                        }

                        $rt = json_decode($response->getBody(),true);
                        info($rt);
//                        if(isset($string))
//                            system('rm -rf '.env('PATH_ATT').'/storage/app/public/face/'.$val['card_id'].$ip.$string.'.jpg');
                        //关闭文件资源
                        @fclose($file);

                        info('遍历-照片查询-学生id-'.$val['id'].'-结束');
                    }
                }
            }
        }
    }


}
?>