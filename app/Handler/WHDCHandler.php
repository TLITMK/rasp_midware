<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/11/7
 * Time: 9:21
 */

namespace App\Handler;


use GuzzleHttp\Client;

class WHDCHandler
{

    public function getRecharges(){
        $client=new Client();
        $response=$client->request('POST',env('CURL_URL').'/DC_get_recharges',[
            'form_params'=>[
                'school_id'=>env('SCHOOL_ID'),
                'count'=>1
            ]
        ]);
        $pay_list=json_decode($response->getBody(),true);
        foreach ($pay_list['data'] as $k=>$pay){
            $card_id=$pay['student']['card_id'];
            $WH_stu=$this->WH_GetPersonInfoByCardNo($card_id);

        }
    }
    //获取token
    public function WH_GetAccessToken(){
        $sign_string="GetAccessToken|1|1-9FBA5FB5DAB24E748AA75BF483856D5C";
        $gb2312_string=iconv('ASCII','gb2312//IGNORE',$sign_string);
        $sign=md5($gb2312_string);
        $client=new \SoapClient('http://106.58.210.86:7777/soap?wsdl');
        $param=[
            'method'=>'GetAccessToken',
            'args'=>[1],
            'sign'=>$sign
        ];
        $param_json=\GuzzleHttp\json_encode($param);
        $param_json=iconv('ASCII','GB2312//IGNORE',$param_json);

        $testparam=[
            'params'=>$param_json
        ];
        $response=$client->WHDCIF($testparam);
        //json的key 没有引号，用正则表达式匹配添加
        $res = preg_replace('/(\w+):/is', '"$1":', $response->Result);
        $res=\GuzzleHttp\json_decode($res,true);
        if($res['responsecode']!=='0000'){
            return false;
        }
        return $res['result']['accesstoken'];
    }

    //获取个人充值明细
    public function WH_GetPayDetails(){
        $token=$this->WH_GetAccessToken();
        $res=$this->WHDC_QUERY_API(
            "GetPayDetails",
            [1,$token,'','','2018-10-01','2019-11-31','00:00','23:59',0,10,1]
        );
        return $res;
    }
    //通过卡号获取人员信息 卡号10位十进制，前补0
    public function WH_GetPersonInfoByCardNo($card_id){
        // info('card_id='.$card_id);
        // $card_id=sprintf("%010u",$card_id);
        info('card_id='.$card_id);
        $token=$this->WH_GetAccessToken();
        $res=$this->WHDC_QUERY_API(
            'GetPersonInfoByCardNo',
            [1,$token,$card_id]
        );
        return $res;
    }
    //充值
    //number 人员编号
    //name 人员姓名
    //money 充值金额 单位分 
    public function WH_Recharge($number,$name,$money)
    {
        //
        $token = $this->WH_GetAccessToken();
        $res = $this->WHDC_QUERY_API(
            "Recharge",
            [1, $token, time(), $number, $name, $money, 1]
//            [1,$token,time(),'201903240001','tangbohu',1,1],
        );
        return $res;
    }




    //转码+请求api
    function WHDC_QUERY_API($method_name, array $params){
        $key="1-9FBA5FB5DAB24E748AA75BF483856D5C";
        $sign_string=$method_name.'|';
        $args=[];
        foreach($params as $k=>$v){
            $sign_string.=$v.'|';
            $args[]=$v;
        }
        $sign_string.=$key;
//        echo 'string '.$sign_string.PHP_EOL;
        $origin=mb_detect_encoding($sign_string, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
        info($origin);
        $gb2312_string=iconv($origin,'gb2312//TRANSLIT//IGNORE',$sign_string);
        info(mb_detect_encoding($gb2312_string,array("ASCII",'UTF-8',"GB2312","GBK",'BIG5')));
        $sign=md5($gb2312_string);
        info('string '.$gb2312_string);
//        echo ' gbstring '.$gb2312_string.PHP_EOL;
        info('sign '.$sign);
        $param=[
            'method'=>$method_name,
            'args'=>array_values($args),
            'sign'=>$sign
        ];
        $client=new \SoapClient('http://106.58.210.86:7777/soap?wsdl');
        $param_json=\GuzzleHttp\json_encode($param,JSON_UNESCAPED_UNICODE);
        $param=[
            'params'=>$param_json
        ];
        $response=$client->WHDCIF($param);
        //json的key 没有引号，用正则表达式匹配添加 ,不匹配时间
        info($response->Result);
        $res = preg_replace('/(?<={|,)(\w+?)(?=:)/', '"$1"', $response->Result);
        $res=json_decode($res,true);
        return $res;

    }


//封装充值接口 
//card_id 卡号
//number 人员编号
//name 人员姓名
//money 充值金额
    public function WHDC_Recharge($card_id,$money){
        $person=$this->WH_GetPersonInfoByCardNo($card_id);
        if($person['responsecode']=='0000'){//成功
            $number=$person['result']['number'];
            $name=$person['result']['name'];
            $balance=$person['result']['balance'];
            info('获取人员',['number'=>$number,'name'=>$name,'balance'=>$balance]);

            $recharge=$this->WH_Recharge($number,$name,$money);
            info('充值',$recharge);
            if($recharge['responsecode']=='0000'){//充值成功
                info('充值成功');
            }else{
                info('充值失败');
            }
        }else{
                info('充值失败');
        }
    }

    //轮询使用 获取充值任务


}