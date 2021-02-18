<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-04-17
 * Time: 16:40
 */

namespace App\Services;


use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;

trait Tools
{
    public function getAllFaceIps(){
        $terminal_ips=[];
        $door = env('DOOR_NUM',0);
        $custom=env('FACE_IPS','');
        $ips=Redis::get('RESYNC_IPS');
        if($ips){
            info('获取ip type=redis',$terminal_ips);
            return $terminal_ips=json_decode($ips,true);
        }
        if($custom){
            info('获取ip type=custom',$terminal_ips);
            return $terminal_ips=explode(',',$custom);
        }
        if($door){
            if (env('SCHOOL_ID') == 735) {
                array_push($terminal_ips, '192.168.30.9');
            } else {
                for ($i = 1; $i <= $door; $i++) {
                    array_push($terminal_ips, '192.168.30.' . ($i * 5 + 3));
                    array_push($terminal_ips, '192.168.30.' . ($i * 5 + 4));
                }
            }
        }
        info('获取ip type=normal',$terminal_ips);
        return $terminal_ips;
    }

    public function getTerminalTypeForSnap($door,$enter){
//        info($door.' '.$enter);
        $face_ips=$this->getAllFaceIps();
        $faceip='192.168.30.' . ((intval($door ) * 5) + ($enter+2));
        info($faceip);

        $isface=false;
        foreach ($face_ips as $ip){
            if($ip==$faceip){$isface=true;
            }
        }
//        info('',$face_ips);
        if($isface){
            $type='face';
        }else{
            $type='snap';
        }
        return $type;
    }

    public function getUnionTime(){
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/get_union_time',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID')
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('获取时间',[$res]);
        $time=$res['data']*60;
        return $time;
    }

    public function imgToBase64($img = '')
    {
        if (!$img) {
            return false;
        }
        $imageInfo = getimagesize($img);
        $base64 = "" . chunk_split(base64_encode(file_get_contents($img)));
        return  $str = str_replace(array("\r\n", "\r", "\n"), "", chunk_split(base64_encode(file_get_contents($img))));
    }
}