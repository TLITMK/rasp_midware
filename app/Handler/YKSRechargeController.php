<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-09-04
 * Time: 14:31
 */

namespace App\Handler;


use GuzzleHttp\Client;

class YKSRechargeController
{
    /*
     *
     * 数据库
     * iccmdb
     * 用户名
     * sit
     * 密码
     * OjC8w0n9a4Qm4XZj3Bg1Q=
     */
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
        return $res['data'];//consume_records最新一条的时间，如果没有，为当前时间一小时前'Y-m-d H:i:s'

    }

    public function sync_records($timeleft){
        if($timeleft<1)return 0;
        $begin=microtime(true);
        $client=new Client();
        $school_id=env('SCHOOL_ID');
        $url=env('YKS_URL');
        $str=$this->getBeginTime();
        $str=str_replace('-','',$str);
        $str=str_replace(':','',$str);
        $begin_time=explode(' ',$str)[0];
//        $begin_time=str_replace(' ','',$str);
        $begin_time_today=date('Ymd');
        $end_time=date('Ymd');

        $str_append='YKT_QUERY_FIN'.'200'.env('YKS_CODE').'19999'.$begin_time_today.$end_time.env('YKS_KEY');
        $mac=strtoupper(md5($str_append));
        info($str_append);
        info($mac);
        $response=$client->request('post',$url,[
            'json'=>[
                'actionStr'=>'YKT_QUERY_FIN',
                'version'=>"200",
                'thirdCode'=>env('YKS_CODE',''),
                'page'=>1,
                'pageSize'=>9999,
                'beginTime'=>$begin_time_today,
                'endTime'=>$end_time,
                'mac'=>$mac
            ]
        ]);
        $res=json_decode($response->getBody(),true);

        if($res['resultCode']!='0000'){
            info('获取易科士消费记录 '.$res['resultMsg']);
            return;
        }else{
            info('获取易科士消费记录'.$res['resultMsg']);
        }
        $list=$res['datas'];
        $data=[];
        foreach ($list as $item){
            $amount=$item['curBalance']-$item['preBalance'];
            $data[]=[
                'school_id'=>$school_id,
                'data_id'=>'YKS'.$item['tradeNo'],
                'card_no'=>$item['accountId'],
                'amount'=>$amount,
                'balance'=>$item['curBalance'],
                'date_time'=>date('Y-m-d H:i:s',strtotime($item['tradeTime'])),
            ];
        }
        $data_arr=array_chunk($data,50);
        foreach ($data_arr as $data_item){
            $this->uploadTransactions($data_item);
            sleep(0.1);
            if((microtime(true)-$begin)>=$timeleft){
                info('超时停止'.(microtime(true)-$begin));
                return 0;
            }
        }
        return ($timeleft-(microtime(true)-$begin));
    }

    public function get_recharges($timeleft){
        if($timeleft<1)return;
        $begin_time=microtime(true);
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/hpt_get_recharges',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'count'=>90
            ]
        ]);
        $pay_list=json_decode($response->getBody(),true);
        info('获取三叶草consume_offlines任务成功',$pay_list);
        foreach($pay_list['data'] as $pay) {
            $card_id=$pay['card_id'];
            //查询人员信息
            $person_info=$this->get_person_info($pay['name'],$card_id);
            if($person_info['resultCode']!=0){
                info('找不到人员-'.$pay['name'].'-'.$card_id,$person_info);
//                $this->set_recharge_status($pay,-1);
                continue;
            }
            if(!isset($person_info['total'])){
                info('找不到人员 '.$card_id.' '.$pay['name']);
                //修改状态
//                $this->set_recharge_status($pay,-1);
                continue;
            }
            if($person_info['total']==0){
                info('找不到人员2 '.$card_id.' '.$pay['name']);
                continue;
            }
            $person=$person_info['datas'][0];
            $success=$this->recharge($pay,$pay['money'],$person);

            if($success){
                $this->set_recharge_status($pay,1);
            }
            else{
                $this->set_recharge_status($pay,-1);
            }
            if((microtime(true)-$begin_time)>=$timeleft){
                info('超时停止'.(microtime(true)-$begin_time));
                return 0;
            }
        }
        return ($timeleft-(microtime(true)-$begin_time));
    }

    //
    /*
     * 消费系统人员账号导入三叶草卡号
     * 定时查询消费系统人员信息，账号+人员姓名+物理卡号
     *
     */
    function sync_person_card(){
        $client=new Client();
        for($i=1;$i<1000;$i++){
            $person_info=$this->get_person_info_page($i,100);
            if($person_info['resultCode']!=0){
                info('查询人员失败-',$person_info);
                break;
            }
            if(count($person_info['datas'])==0){
                info('人员查询-结束');
                break;
            }
            info('查询人员-页码-'.$i.'-人数-'.$person_info['total'].' 核对人数-'.count($person_info['datas']));
            $list=$person_info['datas'];
            $data=[];
            foreach ($list as $item){
                $data[]=[
                    'accountId'=>$item['accountId'],
                    'name'=>$item['name'],
                    'serialno'=>$item['serialno']
                ];
            }
            $response=$client->request('POST',env('CURL_URL')."/hpt_sync_card_by_acountid",[
                'form_params'=>[
                    'school_id'=>env('SCHOOL_ID'),
                    'data'=>$data
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            info('上传结果-',$res);
            usleep(100000);
        }



    }
    function get_person_balance($name,$card_id){
        $client=new Client();
        $url=env('YKS_URL');
        $str='YKT_QUERY_BALANCE'.'200'.env('YKS_CODE','').'3'.sprintf('%08d',$card_id).env('YKS_KEY');
        $mac=strtoupper(md5($str));
        $response=$client->request('post',$url,[
            'json'=>[
                'actionStr'=>'YKT_QUERY_BALANCE',
                'version'=>'200',
                'thirdCode'=>env('YKS_CODE',''),
                'serialType'=>3,
                'serialValue'=>sprintf('%08d',$card_id),
                'name'=>$name,
                'mac'=>$mac
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('查询',$res);
        if($res['resultCode']!='0000'){
            return false;
        }
        return true;
    }

    function get_person_info_page($page,$pageSize){
        $client=new Client();
        $url=env('YKS_URL');
        $str='YKT_GET_CUSTOMER'.'200'.env('YKS_CODE','').$page.$pageSize.env('YKS_KEY');
        $mac=strtoupper(md5($str));
        $response=$client->request('post',$url,[
            'json'=>[
                'actionStr'=>'YKT_GET_CUSTOMER',
                'version'=>'200',
                'thirdCode'=>env('YKS_CODE',''),
                'page'=>$page.'',
                'pageSize'=>$pageSize.'',
                'mac'=>$mac
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('查询全部人员报文'.$str);
        info('mac'.$mac);
        return $res;
    }
    function get_person_info($name,$accountId){
        $client=new Client();
        $url=env('YKS_URL');
//        $str='YKT_GET_CUSTOMER'.'200'.env('YKS_CODE','').'113'.sprintf('%08d',$card_id).env('YKS_KEY');
        $str='YKT_GET_CUSTOMER'.'200'.env('YKS_CODE','').'15'.sprintf('%08d',$accountId).env('YKS_KEY');
        $mac=strtoupper(md5($str));
        $response=$client->request('post',$url,[
            'json'=>[
                'actionStr'=>'YKT_GET_CUSTOMER',
                'version'=>'200',
                'thirdCode'=>env('YKS_CODE',''),
                'page'=>'1',
                'pageSize'=>'5',
                'name'=>$name,
                'accountId'=>sprintf('%08d',$accountId),
                'mac'=>$mac
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('查询结果',$res);
        info('查询报文'.$str);
        info('mac'.$mac);
        return $res;
    }
    public function recharge($pay,$money,$person){
        $client=new Client();
        $url=env('YKS_URL');
        $str='YKT_RECHARGE'.'200'.env('YKS_CODE','').$pay['wxpay_id'].$person['custNo'].$money.date('Ymd',strtotime($pay['created_at'])).env('YKS_KEY');
        $mac=strtoupper(md5($str));
        $json_arr=[
            'actionStr'=>'YKT_RECHARGE',
            'version'=>'200',
            'thirdCode'=>env('YKS_CODE',''),
            'tradeNo'=>$pay['wxpay_id'],
            'name'=>$person['name'],
            'custNo'=>$person['custNo'],
            'money'=>$money,
            'tradeTime'=>date('Ymd',strtotime($pay['created_at'])),
            'mac'=>$mac
        ];
        $response=$client->request('post',$url,[
            'json'=>$json_arr
        ]);
        $res=json_decode($response->getBody(),true);
        info('充值',$json_arr);
        info('充值',$res);
        if($res['resultCode']!='0000'){
            return false;
        }
        return true;
    }
    public function set_recharge_status($pay,$status){
        $client=new CLient();
        $url=env('CURL_URL'.'/hpt/set_recharge_status');
        $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'payID'=>$pay['wxpay_id'],
                'status'=>$status,
                'type'=>'HPT'
            ]
        ]);
        return json_decode($response->getBody(),true);
    }

    function uploadTransactions($data){
//        info($data);
        $client=new Client();
        $url=env('CURL_URL').'/hpt/syncRecords';
        $response=$client->request('post',$url,[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'data'=>$data
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('上传流水记录',$res);
    }
}