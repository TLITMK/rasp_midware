<?php
namespace App\Handler;

use App\Services\Helper;
use GuzzleHttp\Client;

class ClientHandler
{
    /**
    * 设置设备密码
     * @param IP, pwd
     * @return bool
     */
    public function set_pwd($ip,$oldpwd,$newpass){
        if(!$ip){
            return [
              'success'=>false,
              'msg'=>'ip不能为空!',
              'data'=>''
            ];
        }
        $url = $ip.':8090/setPassWord';
        $client = new Client();
        info('8090/setPassWord-开始设置密码');
        $response = $client->request('POST',$url, [
            'form_params' => [
                'oldPass' => $oldpwd,
                'newPass' => $newpass,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            info('8090/setPassWord-设置密码成功');
            return [
                'success'=>true,
                'msg'=>'操作成功,'.$rt['data'],
                'data'=>''
            ];
        }else{
            info('8090/setPassWord-设置密码失败');
            return [
                'success'=>false,
                'msg'=>'操作失败,'.$rt['data'],
                'data'=>''
            ];
        }

    }


    /*
     * 设置配置
     * @param
     * @return data
     * **/
    public function setConfig($ip,$pwd='spdc',$data){
        if(!$ip || !$data){
            return [
                'success'=>false,
                'msg'=>'ip或配置数据不能为空!',
            ];
        }

        $url = $ip.':8090/setConfig ';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'config' => $data,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功,'.$rt['data'],
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败,'.$rt['data'],
                'data'=>''
            ];
        }

    }

    /*
     * 有线网路配置
     *
     * **/
    public function setNetInfo($ip,$set_ip,$pwd="spdc"){
        if(!$ip){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
            ];
        }

        $dns = substr($set_ip,0,11).'1';

        $url = $ip.':8090/setNetInfo';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'isDHCPMod' => 2,
                'ip'=>$set_ip,
                'gateway'=>$dns,
                'subnetMask'=>'255.255.255.0',
                'DNS'=>$dns
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功,',
                'data'=>$rt['data']
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败,'.$rt['msg'],
                'data'=>''
            ];
        }

    }

    /*
     * 修改logo
     * @param ip pwd logo
     * return bool
     * **/
    public function changeLogo($ip,$pwd='spdc',$logo){
        if(!$ip || !$logo){
            return [
                'success'=>false,
                'msg'=>'ip或logo不能为空!',
            ];
        }
        $logo_base64 = Helper::imgToBase64($logo,200,100);
        $url = $ip.':8090/changeLogo';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'imgBase64' => $logo_base64,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功,'.$rt['data'],
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败,'.$rt['data'],
                'data'=>''
            ];
        }

    }

    /*
     * 人脸识别设备 配置选项
     * #param ip pass score
     */
    public function set_face_config($ip,$pass,$score){
        if(!$ip){
            info('设置配置选项-失败-ip为空');
            return ['success'=>false,'msg'=>'ip不能为空'];
        }
        $url=$ip.':8090/setConfig';
        $client=new Client();
        $rt=[];
        $data_pas='{
            "identifyDistance":2,
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
        try{
            info('设置配置选项-开始');
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>$pass,
                    'config'=>$data_pas
                ]
            ]);
            $rt = json_decode($response->getBody(),true);
            info($ip.'-设置配置选项-返回-'.$rt['msg']);
        }catch (\Exception $e){
            info($ip.'-设置配置选项-异常-'.$e->getMessage());
            return [
                'success'=>false,
                'msg'=>$e->getMessage()
            ];
        }
        return ['success'=>true,'msg'=>$rt['msg']];
    }

    /*
     * 设置识别回调
     * @param ip pass url_data
     */
    public function set_identify_callback($ip,$pass,$url_data){
        if(!$ip){
            info('设置识别回调-失败-ip为空');
            return ['success'=>false,'msg'=>'ip不能为空'];
        }
        $url=$ip.':8090/setIdentifyCallBack';
        $client=new Client();
        $rt=[];
        try{
            info($ip.'-设置识别回调-开始');
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>$pass,
                    'callbackUrl'=>$url_data
                ]
            ]);
            $rt = json_decode($response->getBody(),true);
            info($ip.'-设置识别回调-返回-'.$rt['msg']);
        }catch(\Exception $e){
            info($ip.'-设置识别回调-异常-'.$e->getMessage());
            return [
                'success'=>false,
                'msg'=>$e->getMessage()
            ];
        }
        return ['success'=>true,'msg'=>$rt['msg']];

    }

    /*
     * 设置时段权限
     * @param ip pass stu_id time_str
     */
    public function set_time_permission($ip,$pass,$stu_id,$time_str){
        if(!$ip){
            info('设置时段权限-失败-ip为空');
            return [
                'success'=>false,
                'msg'=>'ip不能为空'
            ];
        }
        $url=$ip.':8090/person/createPasstime';
        $client=new Client();
        $rt=[];
        try{
            info($ip.'-'.$stu_id.'-设置时段权限-开始');
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>$pass,
                    'passtime'=>'{"personId":"'.$stu_id.'","passtime":"'.$time_str.'"}'
                ]
            ]);
            $rt = json_decode($response->getBody(),true);
            info($rt);
            info($ip.'-'.$stu_id.'-设置时段权限-返回-'.$rt['msg']);
        }catch(\Exception $e){
            info($ip.'-'.$stu_id.'-设置时段权限-异常-'.$e->getMessage());
            return [
                'success'=>false,
                'msg'=>$e->getMessage()
            ];
        }

        return [
            'success'=>$rt['success'],
            'msg'=>$rt['msg'],
        ];
    }

    /*
     * 删除设备上所有人员时段权限
     * @param ip pass
     */
    public function del_time_permission($ip, $pass='spdc'){
        $url=$ip.':8090/person/deletePasstime';
        $client=new Client();
        $rt=[];
        try{
            info($ip.'-删除时段权限-开始');
            $response=$client->request('POST',$url,[
                'form_params'=>[
                    'pass'=>$pass,
                    'personId'=>-1
                ]
            ]);
            $rt=json_decode($response->getBody(),true);
            info($ip.'-设置时段权限-返回-'.$rt['msg']);
        }catch (\Exception $e){
            info($ip.'-设置时段权限-异常-'.$e->getMessage());
            return [
                'success'=>false,
                'msg'=>$e->getMessage()
            ];
        }
        return [
            'success'=>$rt['success'],
            'msg'=>$rt['msg']
        ];
    }



    /*
     * 重启设备
     * @param IP pwd
     * @return bool
     * **/
    public function restart($ip,$pwd="spdc"){
        if(!$ip){
            return [
                'success'=>false,
                'msg'=>'ip不能为空!',
            ];
        }
        //设备IP:8090/restartDevice
        $url = $ip.':8090/restartDevice';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            info('设备重启-成功');
            return [
                'success'=>true,
                'msg'=>'操作成功,',
                'data'=>''
            ];
        }else{
            info('设备重启-失败',[$rt]);
            return [
                'success'=>false,
                'msg'=>'操作失败,',
                'data'=>''
            ];
        }
    }

    /*
     * 序列号
     */
    public function getDeviceKey($ip){
        $url = $ip.':8090/getDeviceKey';
        $client = new Client();
        $response = $client->request('POST',$url, ['form_params' => []]);

        $rt = json_decode($response->getBody(),true);
        echo $response->getBody().PHP_EOL;
        if($rt['success']){
            info($url.'-成功',[$rt]);
            return [
                'success'=>true,
                'msg'=>'操作成功,',
                'data'=>$rt['data']
            ];
        }else{
            info($url.'-失败',[$rt]);
            return [
                'success'=>false,
                'msg'=>'操作失败,',
                'data'=>''
            ];
        }
    }

    /*
     * 人员注册
     * @param IP pwd person(json)
     * @return person_id
     * {"id":"","idcardNum":"","name":"钟俊雄"}
     * **/
    public function person_create($ip,$data,$pwd="spdc"){
        if(!$ip || !$data){
            info('人员注册IP————————数据为空');
            return [
                'success'=>false,
                'msg'=>'ip或配置数据不能为空!',
            ];
        }
        try{
            $url = $ip.':8090/person/create';
            $client = new Client();
            info('8090/person/create-开始人员注册');
            $response = $client->request('POST',$url, [
                'form_params' => [
                    'pass' => $pwd,
                    'person' => $data,
                ]
            ]);

            $rt = json_decode($response->getBody(),true);
            info($rt);
            if($rt['success']){
                info('8090/person/create-人员注册成功');
                return [
                    'success'=>true,
                    'msg'=>'操作成功,',
                    'data'=>$rt['data']
                ];
            }else{
                info('8090/person/create-人员注册失败-ip-'.$ip);
//                info($rt['msg']);
                return [
                    'success'=>false,
                    'msg'=>'操作失败,'.$rt['msg'],
                    'data'=>''
                ];
            }
        } catch (Exception $e) {
            info('8090/person/create-人员注册异常-msg'.$e->getMessage());
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }
    }


    /*
     * 人员更新
     * @IP pwd person(json)
     * @return person_id
     * **/
    public function person_update($ip,$pwd="spdc",$data){
        if(!$ip || !$data){
            return [
                'success'=>false,
                'msg'=>'ip或配置数据不能为空!',
            ];
        }

        $url = $ip.':8090/person/update';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'person' => $data,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功,',
                'data'=>$rt['data']
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败,',
                'data'=>$rt['data']
            ];
        }
    }

    /*人员删除
     * @param IP pwd person_id
     * **/
    public function person_delete($ip,$person_ids,$type,$pwd='spdc'){
        if(!$ip || !$person_ids || !$type){
            info('删除人员-参数错误');
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>''
            ];
        }
        $url = $ip.':8090/person/delete';
        if($type == '-1'){   //删除所有人员
            $client = new Client();
            info('删除人员-删除全部-开始');
            $response = $client->request('POST',$url, [
                'form_params' => [
                    'pass' => $pwd,
                    'id' => -1,
                ]
            ]);
        }else{
            info('删除人员-删除指定-开始');
            $client = new Client();
            $response = $client->request('POST',$url, [
                'form_params' => [
                    'pass' => $pwd,
                    'id' => (int)$person_ids,
                ]
            ]);
        }

        $rt = json_decode($response->getBody(),true);
        info('删除人员-接口返回',$rt);
        info($rt);
        if($rt['success'] == true){
            info('删除人员-成功');
            return [
                'success'=>true,
                'msg'=>'操作成功,'.$rt['msg'],
                'data'=>$rt['data']
            ];
        }else{
            info('删除人员-失败'.$rt['msg']);
            return [
                'success'=>false,
                'msg'=>'操作失败,'.$rt['msg'],
                'data'=>''
            ];
        }
    }

    /*
     *人员查询
     *@param
     * ***/
    public function person_find($ip,$person_id,$pwd='spdc'){
        //$person_id == -1 全部人员信息
        try {
            $url = $ip.':8090/person/find?pass='.$pwd.'&id='.$person_id;
            info($url);
            $client = new Client();
            $response = $client->request('GET', $url);
            $rt = json_decode($response->getBody(),true);
            info($rt);
            if($rt['success'] && isset($rt['data'][0])){
                if(count($rt['data'][0])){
                    return [
                        'success'=>true,
                        'msg'=>'操作成功',
                        'data'=>$rt['data']
                    ];
                }else{
                    return [
                        'success'=>false,
                        'msg'=>'人员信息不存在',
                        'data'=>''
                    ];
                }

            }else{
                if($rt['msg'] == '人员ID已存在，请调用删除或者更新接口'){
                    return [
                        'success'=>true,
                        'msg'=>'操作失败',
                        'data'=>''
                    ];
                }else{
                    return [
                        'success'=>false,
                        'msg'=>'操作失败',
                        'data'=>''
                    ];
                }

            }
        } catch (Exception $e) {
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }

    }

    /*时段权限
     * @param IP pwd data(json)
     * {"personId":"9eecc839cd7941c5a4d31652 02dd3c32","passtime":"09:00:00,10:00:00, 17:00:00,17:30:00,18:30:00,20:25:00"}
     * */
    public function createPasstime($ip,$data,$pwd='spdc'){
        if(!$ip || !$data){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>''
            ];
        }
        $url = $ip.':8090/person/createPasstime';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'passtime' => $data,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }

    }

    /*
     * 删除时段
     * @param IP pwd personId
     * @return
     * **/
    public function deletePasstime($ip,$person_id,$pwd="spdc"){

        $url = $ip.':8090/person/deletePasstime';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'passtime' => $person_id, //-1 删除所有人员时段权限
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }
    }

    /*
     * 清空照片
     * @param IP personId pwd
     */
    public function delPersonFace($ip,$personId,$pwd='spdc'){
        $url=$ip.':8090/face/delete';
        info($url.'-清空照片-开始');
        if(!$ip|| !$personId){
            return [
                'success'=>false,
                'msg'=>'参数错误',
                'data'=>''
            ];
        }
        $client=new Client();
        $response=$client->request('POST',$url,[
            'form_params' => [
                'pass' => $pwd,
                'faceId' => $personId,
            ]
        ]);
        $rt = json_decode($response->getBody(),true);

        info($url.'-清空照片-返回',$rt);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>$rt
            ];
        }
    }

    /*
     * 照片注册 base64
     * @param IP pwd imgurl personId faceId
     * @return faceId
     * */
    public function face_create($ip,$person_id,$pwd='spdc',$faceId=''){
        $url = $ip.':8090/face/create';
        info($url.'-照片注册-face_create-开始');
        if(!$ip || !$person_id){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>''
            ];
        }
        $file_path = env('PATH_ATT').'/storage/app/public/face/'.$person_id.'/0.jpg';
        if(!$file_path){
            info('本地照片不存在');
            return [
                'success'=>false,
                'msg'=>'本地照片不存在',
                'data'=>''
            ];
        }

//        info('face_create-file_path'.$file_path);
        $base64 = Helper::imgToBase64($file_path,'');

        $client = new Client();
//        info($base64);
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'personId' => $person_id,
                'faceId'=>$person_id,
                'imgBase64'=>$base64
            ]
        ]);

        $rt = json_decode($response->getBody(),true);

        info($url.'-照片注册-face_create-返回',$rt);
        if($rt['success']){
            if($rt['msg']!='success'){
                return [
                    'success'=>false,
                    'msg'=>$rt['msg'],
                    'data'=>''
                ];
            }
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>$rt['data']
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>$rt
            ];

        }
    }

    /*
     * 拍照注册
     * @param ip pwd personId
     * @return bool
     * */
    public function faceTakeImg($ip,$person_id,$pwd='spdc'){
        if(!$ip || !$person_id){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>'',
            ];
        }

        $url = $ip.':8090/face/takeImg';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'personId' => $person_id,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功,'.$rt['msg'],
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }
    }

    /*
     * 照片查询
     * @param ip pwd personId
     * @return bool
     * */
    public function getFaceImg($ip,$person_id,$pwd='spdc'){
        if(!$ip || !$person_id){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>'',
            ];
        }
        $url = $ip.':8090/face/find';
        $client = new Client();
        try{
            $response = $client->request('POST',$url, [
                'form_params' => [
                    'pass' => $pwd,
                    'personId' => $person_id,
                ]
            ]);
            $rt = json_decode($response->getBody(),true);
            info($url.'-返回-',$rt);
            if($rt['success']){
                return [
                    'success'=>true,
                    'msg'=>'照片查询成功',
                    'data'=>$rt['data']
                ];
//            if(count($rt['data'])){
//                return [
//                    'success'=>true,
//                    'msg'=>'照片查询成功',
//                    'data'=>$rt['data']
//                ];
//            }else{
//                return [
//                    'success'=>false,
//                    'msg'=>'照片查询失败',
//                    'data'=>''
//                ];
//            }

            }else{
                return [
                    'success'=>false,
                    'msg'=>'照片查询失败',
                    'data'=>''
                ];
            }
        }catch (\Exception $e){
            info($ip.':8090/face/find 请求异常! 请确认设备是否在线'.$e->getMessage());
            return ['success'=>false,
                'msg'=>$e->getMessage()
                ];
        }



    }

    /*
     * 特征注册
     * @param ip pwd personId faceId feature featureKey
     * @return data
     * */
    public function featureReg($ip,$personId,$faceId,$feature,$featureKey,$pwd='spdc'){
        if(!$ip || !$personId || !$faceId || !$feature || !$featureKey){
            return [
                'success'=>false,
                'msg'=>'参数错误!',
                'data'=>'',
            ];
        }

        $url = $ip.':8090/face/featureReg';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'personId' => $personId,
                'faceId'=>$faceId,
                'feature'=>$feature,
                'featureKey'=>$featureKey
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        info('特征注册');
        info($rt);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>$rt['data']
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }
    }

    //照片注册url
    public function createByurl($ip,$pwd='spdc',$personId,$faceId,$imgUrl){
        $url = $ip.':8090/face/createByUrl';
        $client = new Client();
        $response = $client->request('POST',$url, [
            'form_params' => [
                'pass' => $pwd,
                'personId' => $personId,
                'faceId'=>$faceId,
                'imgUrl'=>$imgUrl,
            ]
        ]);

        $rt = json_decode($response->getBody(),true);
        info('--------------------照片注册url');
        info($rt);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'照片操作成功',
                'data'=>$rt['data']
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>''
            ];
        }
    }



}