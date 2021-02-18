<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-09-18
 * Time: 13:35
 */

namespace App\Handler;



use GuzzleHttp\Client;

class HPTRechargeController
{
    public function TransactionDetails($pageSize,$pageIndex,$beginTime,$endTime){
        //[beginTime,endTime)
        $url=$this->getHPTurl("/hpt/v2/TransactionDetails/List");

        $client=new Client();
        $response=$client->request('post',$url,[

            'json'=>[
                "pageSize"=>$pageSize,
                "pageIndex"=>$pageIndex,
                "searchParameters"=>[
                    "beginTime"=>$beginTime,
                    'endTime'=>$endTime
                ]
            ]
        ]);
        info('',[
            "pageSize"=>$pageSize,
            "pageIndex"=>$pageIndex,
            "searchParameters"=>[
                "beginTime"=>$beginTime,
                'endTime'=>$endTime
            ]
        ]);
        $res=json_decode($response->getBody(),true);
//        info('获取流水',$res);
        $data=[
            'items'=>[],
            'count'=>$res['rowCount'],
            'page_index'=>$pageIndex,
            'page_count'=>ceil($res['rowCount']/$pageSize),
            'page_size'=>$pageSize
        ];
        $school_id=env('SCHOOL_ID');
        foreach ($res['transactions'] as $item){
//            $amount=($item['balance']>$item['finalBalance']?'-':'').$item['transactionAmount'];
//            info('amount',['balance'=>$item['balance'],'finalBalance'=>$item['finalBalance'],'transaction'=>$item['transactionAmount']]);
            $amount=$item['finalBalance']-$item['balance'];
            $data['items'][]=[
                'school_id'=>$school_id,
                'data_id'=>'HPT'.env('SCHOOL_ID').'-'.$item['transactionID'],
                'card_no'=>$item['cardNo'],
                'amount'=>$amount*100,
                'balance'=>$item['finalBalance']*100,
                'date_time'=>$item['transactionTime'],
            ];
        }
        return $data;

    }

    public function TransactionDetailsSchedule(){
        //向三叶草获取该校最后一条记录的时间
        $beginTime=$this->getBeginTime();
        $endTime=Date('Y-m-d H:i:s');
        $pageSize=20;
        $pageIndex=0;
        $data=$this->TransactionDetails($pageSize,$pageIndex,$beginTime,$endTime);
        $this->uploadTransactions($data['items']);
    }
//{
//"pageSize": 100,
//"pageIndex": 0,
//"searchParameter": {
//"cardNo":"3018493735",
//"accountName":"清流"
//}
//}
    public function get_person_list($cardNo){
        $url=$this->getHPTurl('/hpt/v2/Accounts/List');
        $client=new Client();
        $response=$client->request('post',$url,[
            'json'=>[
                'pageSize'=>10,
                'pageIndex'=>0,
                'searchParameter'=>[
                    'cardNo'=>$cardNo
                ]
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('海普天查询人员列表'.$cardNo,$res);
        return $res;
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
    public function recharge($pay,$money,$person){
        $client=new Client();
        $url=$this->getHPTurl('/hpt/v2/Accounts/Recharge?client-type=2');
        $response=$client->request('post',$url,[
            'json'=>[
                'amount'=>''.$money,
                'accountID'=>''.$person['accountID'],
                'accountNo'=>''.$person['accountNo'],
                'transactionNumber'=>$pay['wxpay_id']
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        info('充值',$res);
        if(array_get($res,'error')){
            return false;
        }
        return true;
    }

    public function get_recharges(){
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/hpt_get_recharges',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'count'=>20
            ]
        ]);
        $pay_list=json_decode($response->getBody(),true);
        info('获取三叶草consume_offlines任务成功',$pay_list);
        foreach($pay_list['data'] as $pay) {
            $card_id=$pay['card_id'];
            //查询人员信息
            $person_info=$this->get_person_list($card_id);
            if(!$person_info['rowCount']){
                info('找不到人员'.$card_id);
                //修改状态
                $this->set_recharge_status($pay,-1);
                continue;
            }
            $person=$person_info['cardHolderAccounts'][0];
            $pay_money=round($pay['money']*0.01,2);
            $success=$this->recharge($pay,$pay_money,$person);

            if($success){
                $this->set_recharge_status($pay,1);
            }
            else{
                $this->set_recharge_status($pay,-1);
            }
        }

    }

    function uploadTransactions($data){
        info($data);
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


    function getHPTurl($url){
        $ym=env('HPT_SERVER',false);
        if(!$ym){
            return false;
        }
        return $ym.$url;
    }
}