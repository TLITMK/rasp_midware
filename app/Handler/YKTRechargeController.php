<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2020/3/4
 * Time: 0:17
 */

namespace App\Handler;


class YKTRechargeController
{
    public function get_recharges(){
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/DC_get_recharges',[
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
                $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                    'form_params'=>[
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-1
                    ]
                ]);
                info('卡号挂失，写入consume_offline状态-1,wxpay_order_id='.$pay['wxpay_id']);
                return;
            }
            else if ($hmd&&!$ryxx){//该卡已换卡
                $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                    'form_params'=>[
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-3
                    ]
                ]);
                info('卡号已经换卡，写入consume_offline状态-3,wxpay_order_id='.$pay['wxpay_id']);
                return;
            }
            else if (!$hmd&&!$ryxx){//该卡已退卡
                $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                    'form_params'=>[
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

                $res1=\DB::table('ryxx')->where('rfkh', $pay['card_id'])->update([
                    'rfye'=>$pay['money']+$ryxx->rfye,
                    'grye'=>$pay['money']+$ryxx->rfye,
                ]);

                if($res1){
                    try{
                        $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                            'form_params'=>[
                                'payID'=>$pay['wxpay_id']
                            ]
                        ]);
                        $pay_suc=json_decode($response->getBody(),true);
                        info('修改三叶草consume_offline状态0',$pay_suc);
                    }catch (\Exception $e){
                        info($e->getMessage());
                    }

                    //插入log表
                    $res2=\DB::table('log')->insert([
                        'dn'=>'智慧校园微信充值',
                        'sjrq'=>$pay['created_at'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                        'nr'=>$pay['card_id'].'(微信充值'.$pay['money'].'后余额'.($pay['money']+$ryxx->rfye).')',
                        'lb'=>'增款',
                        'czy'=>'管理员'
                    ]);
                    info('插入Record_XF成功'.$pay['card_id'],[$res2]);
                }
                else{
                    //写入成功请求三叶草修改订单状态(本地数据库入库失败)
                    $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                        'form_params'=>[
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
        $list=\DB::select('select a.*,b.P_Name from Card_Info as a JOIN Personnel_Info as b ON b.P_ID=a.P_ID');
        if(!$list){
            echo '查询失败';
            return;
        }

        $arr=json_encode($list);
        $arr=json_decode($arr,true);
        print_r('total:'.count($arr));
        $arr=array_chunk($arr,28);

        foreach ($arr as $k=>$v){
            print_r($k.'=>'.count($v));
            $response=$client->request('POST',env('CURL_URL').'/DC_update_cardinfos',[
                'form_params'=>[
                    'arr'=>$v,
                    'msg'=>'test'
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            print_r('status:'.$response->getStatusCode());
            echo ('东川一中离线消费系统Card_Info表同步,完成'.$response->getBody()).PHP_EOL;
//            sleep(1);

        }

//            sleep(1);
    }

    public function change_sync(){
        info('换卡人员更新，开始');
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/DC_change_sync',[
            'form_params'=>[

                'msg'=>'test'
            ]
        ]);
        $res=json_decode($response->getBody(),true);
        print_r('status:'.$response->getStatusCode());
        echo ('换卡人员跟新,完成'.$response->getBody()).PHP_EOL;
        info('换卡人员跟新,完成'.$response->getBody());
    }
}