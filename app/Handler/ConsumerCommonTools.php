<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-11-11
 * Time: 16:33
 */

namespace App\Handler;


trait ConsumerCommonTools
{

    //获取三叶草消费记录最新一条，用于定时同步消费记录
    function getBeginTime(){
        $client=new Client();
        $url=env('CURL_URL').'/hpt/getBeginTime';
        $response=$client->request('post',$url,[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID')
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('获取流水开始时间',$res);
        return $res['data'];//consume_records最新一条的时间，如果没有，为当前时间一小时前

    }
}