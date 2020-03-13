<?php
namespace App\Handler;

use GuzzleHttp\Client;
class HeartSync
{
    public function sync(){
        $school_id = env('SCHOOL_ID');
        $vpn = system("ifconfig tap0|grep 'inet '|awk -F \" \" '{print $2}'|awk '{print $1}'");
        $door_num = env('SCHOOL_CODE');
        info($vpn);
        info($school_id);
        if(!$school_id || !$vpn){
            info('同步数据获取失败!');
            return ;
        }
        $client = new Client;
        $rt = $client->request('POST',env('CURL_URL').'/heart_sync',[
            "form_params"=>[
                "vpn"=>$vpn,
                "school_id"=>$school_id,
            ]
        ]);


        $bool = json_decode($rt->getBody(),true);
        if($bool['success']){
            info('心跳同步成功!');
        }else{
            info('心跳同步失败!');
        }


    }
}