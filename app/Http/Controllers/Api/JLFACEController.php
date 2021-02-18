<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-08-31
 * Time: 18:07
 */

namespace App\Http\Controllers\Api;


use Illuminate\Http\Request;

class JLFACEController extends Controller
{
    //* 设备类型
    //1：抓拍机    2：比对机   3：NVR   4: 比对服务器

    //巨龙中间件：tcp转http接口与外界交互 管理设备
    //树莓派：与巨龙中间件交互 http

    //方向： 树莓派-》巨龙中间件《-》设备-》树莓派


    public function test(Request $request){
        $Name=$request->input('Name');
        $TimeStamp=$request->input('TimeStamp');
        $Data=$request->input('Data');
        info($Name);
        info($TimeStamp);
        info('test123',$Data);
        switch ($Name){
            case 'registerRequest'://注册
                info('注册');
                $this->registerRequest($request);
                break;
            case "heartbeatRequest"://心跳
                info('心跳');
                $this->heartbeatRequest($request);
                break;
            case "captureInfoRequest"://抓拍上传
                info('抓拍上传');
                $this->captureInfoRequest($request);
                break;
            case "eventRequest"://主动获取任务
                info('主动获取任务');
                $this->eventRequest($request);
                break;
            case "resultRequest"://任务执行结果
                info('任务执行结果');
                $this->resultRequest($request);
                break;
        }
    }

    //****************C->S****************//

    //设备注册
    public function registerRequest(Request $request){
        $Name=$request->input('Name');//registerResponse
        $TimeStamp=$request->input('TimeStamp');
        $Data=$request->input('Data');
        $DeviceInfo=$Data['DeviceInfo'];//设备信息

        $DeviceId=$DeviceInfo['DeviceId'];//设备序列号
        $DeviceUUID=$DeviceInfo['DeviceUUID'];//设备UUID
        $DeviceMac=$DeviceInfo['DeviceMac'];//设备Mac地址格式 aa:bb:cc:dd:ee:ff
        $DeviceIp=$DeviceInfo['DeviceIp'];//设备IP地址
        $DeviceType=$DeviceInfo['DeviceType'];//1：抓拍机    2：比对机   3：NVR   4: 比对服务器
        $ChannelNum=$DeviceInfo['ChannelNum'];//通道数
        $WebVersion=$DeviceInfo['WebVersion'];//页面版本
        $CoreVersion=$DeviceInfo['CoreVersion'];//主程序版本
        $VersionDate=$DeviceInfo['VersionDate'];//版本日期。格式：Nov 14 2018 18:02:07

        $HTTPVersion=$Data['HTTPVersion'];
        $HTTPDate=$Data['HTTPDate'];

        //TODO:STH

        $timestamp=time();
        $Session=$DeviceUUID."_".$timestamp;//DeviceUUID_TimeStamp
        info('[巨龙设备]注册-成功-注册成功'.$DeviceId);
        $str=response()->json([
            'Name'=>'registerResponse',
            'TimeStamp'=>$timestamp,
            'Data'=>[
                'Session'=>$Session,
                'ServerVersion'=>$HTTPVersion
            ],
            'Code'=>1,
            'Message'=>'注册成功'
        ]);
        info($str);
        return response()->json([
            'Name'=>'registerResponse',
            'TimeStamp'=>$timestamp,
            'Data'=>[
                'Session'=>$Session,
                'ServerVersion'=>$HTTPVersion
            ],
            'Code'=>1,
            'Message'=>'注册成功'
        ]);
    }

    //共用
    public function clientToServer(Request $request){
        $Name=$request->input('Name');//方法名 此处 "heartbeatRequest"
        $TimeStamp=$request->input('TimeStamp');//Unix时间戳(秒)
        $Session=$request->input('Session');//注册返回Session
        $Data=$request->input('Data');//请求内容
        $DeviceInfo=$Data['DeviceInfo'];//设备信息

        $DeviceId=$DeviceInfo['DeviceId'];//设备序列号
        $DeviceUUID=$DeviceInfo['DeviceUUID'];//设备UUID
        $DeviceMac=$DeviceInfo['DeviceMac'];//设备Mac地址格式 aa:bb:cc:dd:ee:ff
        $DeviceIP=$DeviceInfo['DeviceIP'];//设备IP地址
        $ChannelNo=$DeviceInfo['ChannelNo'];//通道号 (NVR各通道的IPC，值255 为NVR)
        $WebVersion=$DeviceInfo['WebVersion'];//页面版本
        $CoreVersion=$DeviceInfo['CoreVersion'];//主程序版本
        $VersionDate=$DeviceInfo['VersionDate'];//版本日期。格式：Nov 14 2018 18:02:07


    }

    //设备心跳
    public function heartbeatRequest(Request $request){
        $Name=$request->input('Name');//方法名 此处 "heartbeatRequest"
        $TimeStamp=$request->input('TimeStamp');//Unix时间戳(秒)
        $Session=$request->input('Session');//注册返回Session
        $Data=$request->input('Data');//请求内容
        $DeviceInfo=$Data['DeviceInfo'];//设备信息

        $DeviceId=$DeviceInfo['DeviceId'];//设备序列号
        $DeviceUUID=$DeviceInfo['DeviceUUID'];//设备UUID
        $DeviceMac=$DeviceInfo['DeviceMac'];//设备Mac地址格式 aa:bb:cc:dd:ee:ff
        $DeviceIP=$DeviceInfo['DeviceIP'];//设备IP地址
        $ChannelNo=$DeviceInfo['ChannelNo'];//通道号 (NVR各通道的IPC，值255 为NVR)
        $WebVersion=$DeviceInfo['WebVersion'];//页面版本
        $CoreVersion=$DeviceInfo['CoreVersion'];//主程序版本
        $VersionDate=$DeviceInfo['VersionDate'];//版本日期。格式：Nov 14 2018 18:02:07

        $HTTPVersion=$Data['HTTPVersion'];//HTTP协议版本
        $HTTPDate=$Data['HTTPDate'];//HTTP协议版本日期
        $HeartBeatCount=$Data['HeartbeatCount'];//心跳信息发送计数（设备启动时开始计数）
        $CaptureCount =$Data['CaptureCount '];//抓拍信息发送计数（设备启动时开始计数）

        //TODO：sth


        return response()->json([
            'Name'=>'heartbeatRequest',//固定 "heartbeatResponse"
            'TimeStamp'=>time(),//Unix时间戳(秒)
            'Session'=>$Session,//注册返回Session
            'EventCount'=>0,//大于0时，设备调用“主动获取任务”接口请求任务
            'Code'=>1,//返回操作码
            'Message'=>''//返回操作信息
        ]);
    }

    public function captureInfoRequest(Request $request){
        $Name=$request->input('Name');//方法名 此处 "heartbeatRequest"
        $TimeStamp=$request->input('TimeStamp');//Unix时间戳(秒)
        $Session=$request->input('Session');//注册返回Session
        $Data=$request->input('Data');//请求内容

        $DeviceInfo=$Data['DeviceInfo'];//设备信息
        $DeviceId=$DeviceInfo['DeviceId'];//设备序列号
        $DeviceUUID=$DeviceInfo['DeviceUUID'];//设备UUID
        $DeviceMac=$DeviceInfo['DeviceMac'];//设备Mac地址格式 aa:bb:cc:dd:ee:ff
        $DeviceIP=$DeviceInfo['DeviceIP'];//设备IP地址
        $ChannelNo=$DeviceInfo['ChannelNo'];//通道号 (NVR各通道的IPC，值255 为NVR)
        $DeviceMode=$DeviceInfo['DeviceMode'];

        $CaptureInfo=$Data['CaptureInfo'];
        $SendTime=$CaptureInfo['SendTime'];
        $CaptureTime=$CaptureInfo['CaptureTime'];
        $RecordId=$CaptureInfo['RecordId'];
        $FacePicture=$CaptureInfo['FacePicture'];//无头base64

        $TemperaInfo=$Data['TemperaInfo'];
        $Temperature=$TemperaInfo['Temperature'];
        $EnvirTemperature=$TemperaInfo['EnvirTemperature'];

        $CompareInfo=$Data['CompareInfo'];
        $AlarmEvent=$CompareInfo['AlarmEvent'];
        $Liveness=$CompareInfo['Liveness'];
        $Attribute=$CompareInfo['Attribute'];
        $CompareTime=$CompareInfo['CompareTime '];
        $VerifyType=$CompareInfo['VerifyType'];
        $VerifyStatus=$CompareInfo['VerifyStatus'];
        $PersonType=$CompareInfo['PersonType'];
        $VisitsCount=$CompareInfo['VisitsCount'];
        $Similarity=$CompareInfo['Similarity'];
        $PersonInfo=$CompareInfo['PersonInfo'];




        //TODO:STH


        $WebVersion=$DeviceInfo['WebVersion'];//页面版本
        $CoreVersion=$DeviceInfo['CoreVersion'];//主程序版本
        $VersionDate=$DeviceInfo['VersionDate'];//版本日期。格式：Nov 14 2018 18:02:07

    }

    public function eventRequest(Request $request){
        $Name=$request->input('Name');//方法名 此处 "eventRequest"
        $TimeStamp=$request->input('TimeStamp');//Unix时间戳(秒)
        $Session=$request->input('Session');//注册返回Session
        $UUID=$request->input('UUID');


        $Session='';
        $event_list=[];

        return response()->json([
            'Name'=>'eventResponse',
            'TimeStamp'=>time(),
            'Session'=>$Session,
            'Data'=>[
                'NextEvent'=>0,//下次获取任务状态
//0：处理当前任务列表完成(并上报)，等心跳通知再去获取新任务列表
//1：处理当前任务列表完成(并上报)后再次调用主动获取任务接口获取新任务列表（不用等心跳通知）
                'ListCount'=>count($event_list),
                'List'=>$event_list
            ],

        ]);

    }

    public  function resultRequest(Request $request){

        $Name=$request->input('Name');//方法名 此处 "heartbeatRequest"
        $TimeStamp=$request->input('TimeStamp');//Unix时间戳(秒)
        $Session=$request->input('Session');//注册返回Session
        $UUID=$request->input('UUID');

        $Data=$request->input('Data');
        $ListCount=$Data['ListCount'];
        $List=$Data['List'];

        foreach ($List as $item){
            switch($item['Action']){
                case 'addPerson':
                    break;
                    //...
            }
        }

        return response()->json([
            'Name'=>'resultResponse',
            'TimeStamp'=>time(),
            'Session'=>$Session,
            'Code'=>1,
            'Message'=>''
        ]);
    }


}