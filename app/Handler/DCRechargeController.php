<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/10/21
 * Time: 10:31
 */

namespace App\Handler;

//东川一中线上充值
use GuzzleHttp\Client;

class DCRechargeController
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
        foreach($pay_list['data'] as $pay){
            //通过卡号查询人员
            $cardIdHEX=dechex(intval($pay['card_id']));
            $cardIdHEX=sprintf('%08s',$cardIdHEX);
            info('卡号'.$pay['card_id'].' 十六进制 '.$cardIdHEX);
            $old_card_info=\DB::table('Card_Info')->where('Card_No',''.$cardIdHEX)->first();
            if(!$old_card_info){
                info('获取Card_Info失败 卡号 '.$cardIdHEX.' 对应的人员不存在');
                continue;
            }
            switch ($old_card_info->Card_Loss){
                case 1:
                    $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                        'form_params'=>[
                            'payID'=>$pay['wxpay_id'],
                            'status'=>-1
                        ]
                    ]);
                    info('卡号挂失，写入consume_offline状态-1,wxpay_order_id='.$pay['wxpay_id']);
                    return;
                case 2:
                    $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                        'form_params'=>[
                            'payID'=>$pay['wxpay_id'],
                            'status'=>-2
                        ]
                    ]);
                    info('卡号已经退卡，写入consume_offline状态-2,wxpay_order_id='.$pay['wxpay_id']);
                    return;
                case 3:
                    $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                        'form_params'=>[
                            'payID'=>$pay['wxpay_id'],
                            'status'=>-3
                        ]
                    ]);
                    info('卡号已经换卡，写入consume_offline状态-3,wxpay_order_id='.$pay['wxpay_id']);
                    return;
                default:
                    break;
            }
            info('获取Card_Info成功 人员id '.$old_card_info->P_ID);
            //写入离线数据库
            $bool=\DB::update(
                'update Card_Info 
                set Card_Ci=:Card_Ci,Card_Money=Card_Money+:Add_Money,Water_Money=Water_Money+0,Electric_Money=Electric_Money+0,Card_Ci_LS=:Card_Ci_LS ,Card_Moeny_LS=Card_Moeny_LS+1 ,End_Time=:End_Time 
                where P_ID=:P_ID and Card_Loss=:Card_Loss',
                [
                    'Card_Ci'=>0,
                    'Add_Money'=>$pay['money'],
                    'Card_Ci_LS'=>0,
                    'End_Time'=>'2099-12-31',
                    'Card_Loss'=>0,
                    'P_ID'=>$old_card_info->P_ID
                ]);
            info('更新CardInfo结果',[$bool]);
            if($bool){//1成功 0失败
                info('更新Card_Info表成功'.$old_card_info->P_ID);

                //写入成功请求三叶草修改订单状态
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

                //插入充值记录
                $rt=\DB::table('Record_XF')->insert([
                    'CompanyID'=>592,
                    'Mach_ID'=>-1,
                    'P_ID'=>$old_card_info->P_ID,
                    'CashierNo'=>0,
                    'Card_Type_ID'=>$old_card_info->Card_Type_ID,
                    'PlaceID'=>0,//充值地点,
                    'XF_Type'=>200,//线上充值,
                    'XF_Meal'=>0,
                    'Money_Old'=>$old_card_info->Card_Money,
                    'Money_Change'=>$pay['money'],
                    'Money_New'=>$old_card_info->Card_Money+($pay['money']),
                    'Card_QuHao'=>2,//????????
                    'Card_LS'=>$old_card_info->Card_Moeny_LS+1,
                    'Mach_LS'=>-3,
                    'Date_Time'=>$pay['created_at'].'.'.explode('.',sprintf('%01.3f',microtime(true)))[1],
                    'Card_No'=>$cardIdHEX,
                    'Ci_Change'=>0,
                    'Ci_LS'=>0,'Ci_New'=>0,
                    'UnitID'=>784,//业主id
                    'Cost_Code'=>1,
                    'BtMoney_Change'=>0,
                    'BtCi_Change'=>0,
                    'Per_Value'=>10000
                ]);
                info('插入Record_XF成功'.$old_card_info->P_ID,[$rt]);
            }else{
                info('更新Card_Info表失败'.$old_card_info->P_ID);
                //写入成功请求三叶草修改订单状态
                $response=$client->request('POST',env('CURL_URL').'/DC_recharge_success',[
                    'form_params'=>[
                        'payID'=>$pay['wxpay_id'],
                        'status'=>-4
                    ]
                ]);
                $pay_suc=json_decode($response->getBody(),true);
                info('修改三叶草consume_offline状态-4',$pay_suc);
            }
            usleep(1);
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