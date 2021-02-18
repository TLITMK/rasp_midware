<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2020/3/4
 * Time: 0:17
 */

namespace App\Handler;


use App\Services\Tools;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class YKTRechargeController
{
    public function get_recharges(){
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/YKT_get_recharges',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'count'=>20
            ]
        ]);
        $pay_list=json_decode($response->getBody(),true);
        info('获取三叶草consume_offlines任务成功',$pay_list);

        //查询黑名单
        foreach($pay_list['data'] as $pay) {
            $cardIdHEX = dechex(intval($pay['card_id']));
            $cardIdHEX = sprintf('%08s', $cardIdHEX);
            info('卡号' . $pay['card_id'] . ' 十六进制 ' . $cardIdHEX);
            $hmd=\DB::table('hmd')->where('rfkh', $pay['card_id'])->first();
            $ryxx=\DB::table('ryxx')->where('rfkh',$pay['card_id'])->first();
            if($hmd&&$ryxx){//该卡已挂失
                $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
                    'form_params'=>[
                        'school_id'=>env('SCHOOL_ID'),
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-1
                    ]
                ]);
                info('卡号挂失，写入consume_offline状态-1,wxpay_order_id='.$pay['wxpay_id']);
                return;
            }
            else if ($hmd&&!$ryxx){//该卡已换卡
                $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
                    'form_params'=>[
                        'school_id'=>env('SCHOOL_ID'),
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-3
                    ]
                ]);
                info('卡号已经换卡，写入consume_offline状态-3,wxpay_order_id='.$pay['wxpay_id']);
                return;
            }
            else if (!$hmd&&!$ryxx){//该卡已退卡
                $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
                    'form_params'=>[
                        'school_id'=>env('SCHOOL_ID'),
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-2
                    ]
                ]);
                info('卡号已经退卡，写入consume_offline状态-2,wxpay_order_id='.$pay['wxpay_id']);
                return;
            }
            else if(!$hmd&&$ryxx){//正常充值
                //写入离线数据库
                //更新ryxx表 TODO:配置数据库
                //update ryxx set rfye=103,grye=103,btye=0 where rfkh='3018493735'
                //go

                DB::beginTransaction();
                $res1=$res2=$res3=false;
                try{
                    $pay_money=round($pay['money']*0.01,2);
                    $res1=DB::table('ryxx')->where('rfkh', $pay['card_id'])->update([
                        'rfye'=>$pay_money+$ryxx->rfye,
                        'grye'=>$pay_money+$ryxx->rfye,
                    ]);
                    //插入lssj表流水数据
                    $res3=DB::table('lssj')->insert([
                        'ryid'=>$ryxx->ryid,
                        'rfkh'=>$pay['card_id'],
                        'xfjh'=>'0',
                        'xfje'=>$pay_money,
                        'rfye'=>($pay_money+$ryxx->rfye),
                        'xfsj'=>$pay['created_at'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                        'xffs'=>'微信充值',
                        'xfzl'=>'增款',
                        'sky'=>'管理员',
                        'grje'=>($pay_money+$ryxx->rfye)
                    ]);
                    //插入log表
                    $res2=DB::table('log')->insert([
                        'dn'=>'智慧校园微信充值',
                        'sjrq'=>$pay['created_at'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                        'nr'=>$pay['card_id'].'(微信充值'.$pay_money.'后余额'.($pay_money+$ryxx->rfye).')',
                        'lb'=>'增款',
                        'czy'=>'管理员'
                    ]);
                    $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
                        'form_params'=>[
                            'school_id'=>env('SCHOOL_ID'),
                            'payID'=>$pay['wxpay_id']
                        ]
                    ]);
                    $pay_suc=json_decode($response->getBody(),true);
                    info('修改三叶草consume_offline状态0',$pay_suc);
                }catch (\Throwable $e){
                    info($e->getMessage());
                    DB::rollback();
//                    return;
                }

                if($res1&&$res2&&$res3&&$pay_suc['success']){
                    DB::commit();
                    info('插入Record_XF成功'.$pay['card_id'],[$res2]);
                }
                else{
                    //写入成功请求三叶草修改订单状态(本地数据库入库失败)
                    $response=$client->request('POST',env('CURL_URL').'/YKT_recharge_success',[
                        'form_params'=>[
                            'school_id'=>env('SCHOOL_ID'),
                            'payID'=>$pay['wxpay_id'],
                            'status'=>-4
                        ]
                    ]);
                    $pay_suc=json_decode($response->getBody(),true);
                    info('修改三叶草consume_offline状态-4',$pay_suc);
                }

            }
        }

    }
    public function sync_card_info(){
        info('同步card_info');
        $client=new Client();
        $list=\DB::select('select * from ryxx');
        if(!$list){
            echo '查询失败';
            return;
        }

        $arr=json_encode($list);
        $arr=json_decode($arr,true);
        print_r('total:'.count($arr));

        foreach ($arr as $k=>$v){
            try{
                $response=$client->request('POST',env('CURL_URL').'/YKT_update_cardinfos',[
                    'form_params'=>[
                        'info'=>$v,
                        'msg'=>'test',
                        'school_id'=>env('SCHOOL_ID')
                    ]
                ]);
                $res=json_decode($response->getBody(),true);
                if($res['success']){
                }else{
                    info('YKT同步人员资料失败-'.$res['msg'],[$v]);
                }
            }catch (\Throwable $e){
                info('YKT同步人员资料失败-'.$e->getMessage(),[$v]);
            }
        }

//            sleep(1);
    }

    public function sync_records(){
//        $this->get
        $common=new HPTRechargeController();
        $beginTime=$common->getBeginTime();
        $endTime=Date('Y-m-d H:i:s');
        $school_id=env('SCHOOL_ID',false);
        if(!$school_id){
            info('同步消费记录失败，学校id未配置');
            return;}
            info($beginTime.' - '.$endTime);
        $records=DB::table('lssj')->where('xfsj','>',$beginTime)
            ->where('xfsj','<=',$endTime)
            ->orderBy('xfsj')
            ->limit(20)->get();
        if(!$records->count()){
            info('没有记录');
            return;
        }
        $data=[];
        foreach ($records as $item){
//            info($item->xfsj);
            if(strstr($item->xfzl,'消费')){
                $amount='-'.($item->xfje*100);
            }
            else{
                $amount=($item->xfje*100);
            }
            $data[]=[
                'school_id'=>$school_id,
                'data_id'=>'YKT'.$item->rfkh.strtotime($item->xfsj),
                'card_no'=>$item->rfkh,
                'amount'=>$amount,//xfzl 增款、消费的符号为正 减款符号为负
                'balance'=>$item->rfye*100,
                'date_time'=>$item->xfsj,
            ];
            usleep(10);
        }
        info(is_array($data),$data);
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

    public function change_sync(){

    }

    public function getRefundTask($count,$time_limit){
        $start_time=microtime(1);
        $school_id=env('SCHOOL_ID');
        $client=new Client();
        try{
            $response=$client->request('post',env('CURL_URL').'/Refund_get_tasks',[
                'form_params'=>[
                    'school_id'=>$school_id,
                    'count'=>$count
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            if(!$res['success']){
                return $res['msg'];
            }
            info('退款-获取退款任务'.count($res['data']));
        }catch (\Throwable $e){
            info('获取退款任务-异常-'.$e);
            return false;
        }
        foreach ($res['data'] as $refund){
            if((microtime(1)-$start_time)>$time_limit){
                info('退款-超时跳出');
                break;
            }
            info('test',$refund);
            $stu_name=$refund['stu_name'];
            $card_id=$refund['card_id'];
            $money=$refund['refund_money'];
            $refund_id=$refund['id'];
            $hmd=\DB::table('hmd')->where('rfkh', $refund['card_id'])->first();
            $ryxx=\DB::table('ryxx')->where('rfkh',$refund['card_id'])->first();
            if($hmd&&$ryxx){//该卡已挂失
                $response=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                    'form_params'=>[
                        'refund_id'=>$refund_id,
                        'status'=>-2,
                        'fail_msg'=>'卡号'.$refund['card_id'].'已挂失，退款失败，请更新卡号信息'
                    ]
                ]);
                info('退款-卡号挂失'.$refund['card_id'].$response->getBody());
                return;
            }
            else if ($hmd&&!$ryxx){//该卡已换卡
                $response=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                    'form_params'=>[
                        'refund_id'=>$refund_id,
                        'status'=>-2,
                        'fail_msg'=>'卡号'.$refund['card_id'].'已换卡，退款失败，请更新卡号信息'
                    ]
                ]);
                info('退款-该卡已换卡'.$refund['card_id'].$response->getBody());
                return;
            }
            else if (!$hmd&&!$ryxx){//该卡已退卡
                $response=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                    'form_params'=>[
                        'refund_id'=>$refund_id,
                        'status'=>-2,
                        'fail_msg'=>'卡号'.$refund['card_id'].'已退卡，退款失败'
                    ]
                ]);
                info($refund_id.'退款-该卡已退卡'.$refund['card_id'].$response->getBody());
                return;
            }
            else if(!$hmd&&$ryxx){//正常充值
                //写入离线数据库
                //更新ryxx表 TODO:配置数据库
                //update ryxx set rfye=103,grye=103,btye=0 where rfkh='3018493735'
                //go

                if($money>$ryxx->rfye*100){
                    $response=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                        'form_params'=>[
                            'refund_id'=>$refund_id,
                            'status'=>-2,
                            'fail_msg'=>'卡号'.$refund['card_id'].'余额不足，退款失败'
                        ]
                    ]);
                    info($refund_id.'退款-余额不足'.$refund['card_id'].$response->getBody());
                    return;
                }
                DB::beginTransaction();
                $res1=$res2=$res3=false;
                try{
                    $pay_money=round($money*0.01,2);
                    $res1=DB::table('ryxx')->where('rfkh', $card_id)->update([
                        'rfye'=>$ryxx->rfye-$pay_money,
                        'grye'=>$ryxx->rfye-$pay_money,
                    ]);
                    //插入lssj表流水数据
                    $res3=DB::table('lssj')->insert([
                        'ryid'=>$ryxx->ryid,
                        'rfkh'=>$card_id,
                        'xfjh'=>'0',
                        'xfje'=>(-$pay_money),
                        'rfye'=>($ryxx->rfye-$pay_money),
                        'xfsj'=>$refund['submit_time'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                        'xffs'=>'微信退款',
                        'xfzl'=>'减款',
                        'sky'=>'管理员',
                        'grje'=>($ryxx->rfye-$pay_money)
                    ]);
                    //插入log表
                    $res2=DB::table('log')->insert([
                        'dn'=>'智慧校园微信充值',
                        'sjrq'=>$refund['submit_time'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                        'nr'=>$card_id.'(微信退款'.$pay_money.'后余额'.($ryxx->rfye-$pay_money).')',
                        'lb'=>'增款',
                        'czy'=>'管理员'
                    ]);
                    $response1=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                        'form_params'=>[
                            'refund_id'=>$refund_id,
                            'status'=>2,
                            'fail_msg'=>''
                        ]
                    ]);
//                    info('response  '.$response1);
                    info('退款成功  respons  '.$response1->getBody());
                    $pay_suc=json_decode($response1->getBody().'',true);
                    info('退款-退款成功'.$refund['card_id'],[$pay_suc]);
//                    info('修改三叶草consume_offline状态0',$pay_suc);
                }catch (\Throwable $e){
                    info($e->getMessage());
                    $pay_suc['success']=false;
                    DB::rollback();
//                    return;
                }

                if($res1&&$res2&&$res3&&$pay_suc['success']){
                    DB::commit();
                    info('退款-退款成功'.$refund['card_id']);
//                    info('插入Record_XF成功'.$pay['card_id'],[$res2]);
                }
                else{
                    //写入成功请求三叶草修改订单状态(本地数据库入库失败)
                    $response=$client->request('POST',env('CURL_URL').'/Refund_set_status',[
                        'form_params'=>[
                            'refund_id'=>$refund_id,
                            'status'=>-2,
                            'fail_msg'=>'卡号'.$refund['card_id'].'学校消费系统退款失败，退款失败'
                        ]
                    ]);
                    $pay_suc=json_decode($response->getBody(),true);
                    info('退款-本地入库失败'.$refund['card_id'],[$pay_suc]);
//                    info('修改三叶草consume_offline状态-4',$pay_suc);
                }

            }
        }
    }
}