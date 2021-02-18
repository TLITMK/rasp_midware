<?php

namespace App\Console;

use App\Handler\DCRechargeController;
use App\Handler\FaceController;
use App\Handler\FaceDetectController;
use App\Handler\HPTRechargeController;
use App\Handler\SitXunXinFaceController;
use App\Handler\SwooleHandler;
use App\Handler\YKSRechargeController;
use App\Handler\YKTRechargeController;
use App\Http\Controllers\Api\UnionAttController;
use function foo\func;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Kernel extends ConsoleKernel {
	/**
	 * The Artisan commands provided by your application.
	 *
	 * @var array
	 */
	protected $commands = [
		//
		\App\Console\Commands\SwooleArtisan::class,
		\App\Console\Commands\HttpServerArtisan::class,
		\App\Console\Commands\HeartSync::class,
		\App\Console\Commands\TestSnap::class,
		\App\Console\Commands\SwooleClient::class,
		\App\Console\Commands\FaceSyncSearchFace::class,
	];

	/**
	 * Define the application's command schedule.
	 *
	 * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
	 * @return void
	 */

	protected function schedule(Schedule $schedule) {


	    //TEST
//        foreach ([1,2,3] as $no){
//            $schedule->call(function () use($no){
//                foreach ([1,2,3,4,5] as $v){
//                    info('test'.$no.'-'.$v);
////                    sleep(1);
//            }
//            })->everyMinute()->runInBackground();
//        }

        //test redis
//        if(env('SCHOOL_ID')==735){
//            $schedule->call(function (){
//                $list=Redis::keys('unionSnapPic192.168.30.7*',);
//                info('test',[$list]);
//            });
//        }

		$str = 'rm -rf ' . env('PATH_ATT') . '/storage/app/public/att_images/*';
		$schedule->call(function () use ($str) {
			system($str);
		})->daily();

		//联动考勤签到版
		if(env('UNION_ATT_SOFT',false)){
            //同步人员
            $schedule->call(function(){
                $time_start=microtime(true);
                $face=new FaceDetectController();
                $face->getAllPersons();

            })->everyMinute();

            //获取联动授权信息 （家长刷卡/家长点击签到）model=UnionRegister
            $schedule->call(function (){
                $client=new Client();
                try{
                    $response=$client->request('post',env('CURL_URL').'/union/face/get_union_registers',[
                        'form_params'=>[
                            'school_id'=>env('SCHOOL_ID',false)
                        ]
                    ]);
                    $res=json_decode($response->getBody(),true);
                }catch (\Throwable $e){
                    info('获取联动授权信息'.$e->getMessage());
                    return;
                }
                info('res',$res);
                if(!array_get($res,'data',false)){
                    //记录为空
                    info('获取联动授权信息'.'记录为空');
                    return;
                }
                $handler=new SwooleHandler();
                $union_time=$handler->getUnionTime();
                Redis::set('unionTime',$union_time);

                Redis::pipeline(function ($pipe)use($res,$union_time){
                    foreach($res['data'] as $item){
                        $pipe->setex('unionAuthFamilySoft:'.$item['student_id'],$union_time,true);
                    }
                });
            })->everyMinute();


        }


        //联动考勤刷卡登记版
        if(env('UNION_ATT',false)){
            $schedule->call(function(){
                $timestamp=microtime(true);
                $handler=new SwooleHandler();
                $union_time=$handler->getUnionTime();
                Redis::set('unionTime',$union_time);
                $min=date('i');

                $ips_str=env('UNION_ATT');
                $iparr=explode(',',$ips_str);
                for($i=0;$i<59;$i++){
                    $unit_time=microtime(true);
                    $now_time=time();
                    $is_snap=$now_time>strtotime("12:00")
                        &&$now_time<strtotime('20:00');
                    if($is_snap){
                        $promises=[];
                        foreach ($iparr as $ip){
                            $client=new Client();
                            $promises[$ip]=$client->requestAsync('GET',$ip.'/ISAPI/Streaming/channels/1/picture', [
                                'timeout'=>1,
                                'auth' => [
                                    env('CAMERA_USER_NAME', 'admin'),
                                    env('CAMERA_PWD', 'admin123')
                                ]
                            ]);
                        }
                        $total_time=0;
                        foreach ($promises as $ip=>$promise){
                            $timetest1=microtime(true);
                            try{
                                $res=$promise->wait();
                                Redis::setex('unionSnapPic'.$ip.$now_time,4,base64_encode($res->getBody()) );
                                $now=microtime(true);
                                $span=$now-$timetest1;
//                                info('【联动】'.$ip.'抓拍',['单元耗时'=>$span,'time'=>$now_time,'i'=>$i]);
                            }catch (\Throwable $e){
                                $now=microtime(true);
                                $span=$now-$timetest1;
                                info('【联动】'.$ip.$e->getMessage(),['单元耗时'=>$span,'time'=>$now_time,'i'=>$i]);
                                $iparr=array_diff($iparr,[$ip]) ;

                            }
                            $total_time+=$span;
                        }
//                        info('剩余ip',$iparr);
                    }else{
                        $total_time=0;
//		            info('不轮询抓拍');
                    }
                    $sleep=1000000-$total_time*1000000;
                    if($sleep>0){
                        usleep($sleep);
                    }
                    if((microtime(true)-$timestamp)>=59)break;
                }
            })->everyMinute();
        }
//		$schedule->call(function(){
//		    if(!env('UNION_ATT'))return;
//		    $timestamp=microtime(true);
//		    $handler=new SwooleHandler();
//		    $union_time=$handler->getUnionTime();
//            Redis::set('unionTime',$union_time);
//		    $min=date('i');
//		    for($i=0;$i<59;$i++){
//		        $unit_time=microtime(true);
////		        $times=Redis::get('unionSnapIn');
//                $now_time=time();
//                $is_snap=$now_time>strtotime("12:00")
//                    &&$now_time<strtotime('20:00');
//		        if($is_snap){
//                    try{
//                        $timetest1=microtime(true);
//                        $client=new Client();
//                        $ip='192.168.30.6';
//                        $rt = $client->request('GET', $ip.'/ISAPI/Streaming/channels/1/picture',
//                            ['auth' => [env('CAMERA_USER_NAME', 'admin'), env('CAMERA_PWD', 'admin123')]]);
////                $path=env('PATH_ATT') . '/storage/app/public/union_auth/' .$personId.'-family.jpg';
////                file_put_contents($path, $rt->getBody());
//                        Redis::setex('unionSnapPicIn'.time(),5,base64_encode($rt->getBody()) );
//                        $now=microtime(true);
//                        info('【联动】30.6抓拍',['单元耗时'=>$now-$timetest1,'time'=>$min,'i'=>$i]);
//                    }catch (\Exception $e){
//                        info('【联动】抓拍失败-摄像头不在线-ip='.$ip.'错误信息:'.$e->getMessage(),['单元耗时'=>microtime(true)-$timetest1]);
//                        Redis::set('unionSnapIn',0);
//                        break;
//                    }
////                    Redis::set('unionSnapIn',$times-1);
//                }else{
////		            info('不轮询抓拍');
//                }
//
//		        usleep(1000000);
//                if((microtime(true)-$timestamp)>=59)break;
//            }
//        });

//        $schedule->call(function(){
//            if(!env('UNION_ATT'))return;
//            $timestamp=microtime(true);
////            $handler=new SwooleHandler();
////            $union_time=$handler->getUnionTime();
//            $min=date('i');
//            for($i=0;$i<59;$i++){
//                $unit_time=microtime(true);
////                $times=Redis::get('unionSnapOut');
//                $now_time=time();
//                $is_snap=$now_time>strtotime("12:00")
//                    &&$now_time<strtotime('20:00');
//                if($is_snap){
//                    try{
//                        $timetest1=microtime(true);
//                        $client=new Client();
//                        $ip='192.168.30.7';
//                        $rt = $client->request('GET', $ip.'/ISAPI/Streaming/channels/1/picture',
//                            ['auth' => [env('CAMERA_USER_NAME', 'admin'), env('CAMERA_PWD', 'admin123')]]);
////                $path=env('PATH_ATT') . '/storage/app/public/union_auth/' .$personId.'-family.jpg';
////                file_put_contents($path, $rt->getBody());
//                        Redis::setex('unionSnapPicOut'.time(),3,base64_encode($rt->getBody()) );
////                        info('test'.substr(base64_encode($rt->getBody()) ,0,100));
//                        $now=microtime(true);
//                        info('【联动】30.7抓拍',['单元耗时'=>$now-$timetest1,'time'=>$min,'i'=>$i]);
//                    }catch (\Exception $e){
//                        info('【联动】抓拍失败-摄像头不在线-ip='.$ip.'错误信息:'.$e->getMessage(),['单元耗时'=>microtime(true)-$timetest1]);
//                        Redis::set('unionSnapOut',0);
//                        break;
//                    }
////                    Redis::set('unionSnapOut',$times-1);
//                }else{
////		            info('不轮询抓拍');
//                }
//
//                usleep(1000000);
//                if((microtime(true)-$timestamp)>=59)break;
//            }
//        });


        $schedule->call(function(){
            if(!env('UNION_ATT'))return;
            $keys=Redis::keys('unionSuccessInfo*');
            $success_vals = Redis::pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $item) {
                    $pipe->get($item);
                }
            });
            info('[联动]异步处理联动照片和考勤-count='.count($success_vals));
            usleep(100000);
            foreach($success_vals as $val){
                usleep(200000);
                $timestamp=microtime(true);
                $att_info=json_decode($val,true);
                $personId=$att_info['personId'];
                $deviceKey=$att_info['deviceKey'];
                $ip=$att_info['ip'];
                $type=$att_info['type'];
                $time=$att_info['time'];
                $base64_string=$att_info['imgBase64'];

//                $is_out=false;
//                $snap_time=Redis::get('unionAuthFamilyIn:'.$personId);
//                if(!$snap_time){
//                    $snap_time=Redis::get('unionAuthFamilyOut:'.$personId);
//                    $is_out=true;
//                }
                $family_pic=false;
//                for($i=0;$i<3;$i++){
//                    if($is_out){
//                        $family_pic=Redis::get('unionSnapPicOut'.($snap_time+$i));
//                        if($family_pic){
//                            info('[联动]30.7照片处理考勤推送-$snap_time='.$snap_time.'-find_time='.($snap_time+$i));
//                            break;
//                        }
//                    }else{
//                        $family_pic=Redis::get('unionSnapPicIn'.($snap_time+$i));
//                        if($family_pic){
//                            info('[联动]30.6照片处理考勤推送-$snap_time='.$snap_time.'-find_time='.($snap_time+$i));
//                            break;
//                        }
//                    }
//
//                }
                if(!$family_pic){
                    info('[联动]照片处理考勤推送-没找到家长照片-发送普通考勤');
                    $send_time1=microtime(true);
                    try {
                        $client=new Client();
                        $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
                            'timeout'=>0.8,
                            'form_params' => $att_info
                        ]);
                    } catch (\Exception $e) {
                        info($e->getMessage(),['timespan'=>[
                            '请求耗时'=>microtime(true)-$send_time1,
                            '总耗时'=>microtime(true)-$timestamp
                        ]]);
                        continue;
                    }
                    $res=json_decode($response->getBody(),true);

                    info($att_info['personId'].'[联动]上报考勤信息(仅学生照片)',['res'=>$res,
                        'timespan'=>[
                            '请求耗时'=>microtime(true)-$send_time1,
                            '总耗时'=>microtime(true)-$timestamp
                        ]
                    ]);
                    Redis::del('unionSuccessInfo'.$personId);
                }
                else{
//                    $con=new UnionAttController();
//                    $res_base64=$con->combine_pic(
//                        env('PATH_ATT') . '/storage/app/public/union_auth/' .'tempIn'.'-family.jpg',
//                        $family_pic,
//                        env('PATH_ATT') . '/storage/app/public/union_auth/' .'tempIn'.'-student.jpg',
//                        $base64_string,
//                        env('PATH_ATT') . '/storage/app/public/union_auth/'.$personId.'.jpg');
//
//
//                    //访问sync_face_test()接口
//                    try {
//                        $send_time4=microtime(true);
//                        $client=new Client();
//                        $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
//                            'form_params' => [
//                                'personId'=>$personId,
//                                'deviceKey'=>$deviceKey,
//                                'ip'=>$ip,
//                                'type'=>$type,//'face_0'为非允许时段
//                                'time'=>$time,//毫秒时间戳
//                                'imgBase64'=>$res_base64['base64']
//                            ]
//                        ]);
//                        $res=json_decode($response->getBody(),true);
//                        info($personId.'[联动异步]联动成功-模拟人脸识别上报考勤信息',$res);
//                        info('返回252',
//                            [
//                                '上报耗时'=>microtime(true)-$send_time4,
//                                '总耗时'=>microtime(true)-$timestamp
//                            ]);
//
//                    }
//                    catch (GuzzleException $e) {
//                        info($personId.'[联动]联动成功-'.$e->getMessage().(microtime(true)-$timestamp));
//                    }
//                    Redis::del('unionSuccessInfo'.$personId);

                }
                if(microtime(true)-$timestamp>=59)break;
            }
        })->everyMinute();

		$schedule->call(function(){
		    if(!env('UNION_ATT'))return;
		    $keys=Redis::keys('unionFamilyRegist*');
		    $register_vals=Redis::pipeLine(function($pipe)use($keys){
                foreach ($keys as $item){
                    $pipe->get($item);
                }
            });
		    info('[联动]异步发送家长登记通知');
		    usleep(50000);
		    foreach($register_vals as $val){
                usleep(200000);
                $timestamp=microtime(true);
                $att_info=json_decode($val,true);
                try {
                    $send_time4=microtime(true);
                    $client=new Client();
                    $response = $client->request('post', env('CURL_URL') . '/union/face/send_union_register', [
                        'timeout'=>1,
                        'form_params' => $att_info
                    ]);
                    $res=json_decode($response->getBody(),true);
                    info($att_info['person_id'].'[联动]家长登记-上报推送登记通知',$res);
                    info('返回252',
                        [
                            '上报耗时'=>microtime(true)-$send_time4,
//                        '总耗时'=>microtime(true)-$timestamp
                        ]);
                    Redis::del('unionFamilyRegist'.$att_info['person_id']);
                }
                catch (\Throwable $e) {
                    info($att_info['person_id'].'[联动]家长登记-'.$e->getMessage());
                }
                if(microtime(true)-$timestamp>=59)break;
            }
        })->everyMinute();

//        $schedule->call(function(){

//            if(!env('UNION_ATT'))return;
//            info('[未登记]异步通知家长登记-开始');
//            $keys=Redis::keys('unionAlertInfo:*');
//            $success_vals=Redis::pipeline(function ($pipe)use($keys){
//                foreach ($keys as $k){
//                    $pipe->get
//                }
//            })

//        });
        $schedule->call(function (){
            if(!env('UNION_ATT'))return;
            info('[普通]异步模拟人脸识别上报考勤信息-开始');
            $keys=Redis::keys('NORMAL_ATT_INFO*');
            $success_vals = Redis::pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $item) {
                    $pipe->get($item);
                }
            });
            $client=new Client();
            foreach ($success_vals as $val){
                usleep(200000);
                $timestamp=microtime(true);
                $att_info=json_decode($val,true);
                $personId=$att_info['personId'];
                $deviceKey=$att_info['deviceKey'];
                $ip=$att_info['ip'];
                $type=$att_info['type'];
                $time=$att_info['time'];
                $base64_string=isset($att_info['imgBase64'])?$att_info['imgBase64']:'';
                info(array_keys($att_info));
                $send_time1=microtime(true);

                try {
                    $response = $client->request('post', env('CURL_URL') . '/face/sync_face_test', [
                        'timeout'=>0.8,
                        'form_params' => $att_info
                    ]);
                } catch (\Exception $e) {
                    info($e->getMessage(),['timespan'=>[
                        '请求耗时'=>microtime(true)-$send_time1,
                        '总耗时'=>microtime(true)-$timestamp
                    ]]);
                    continue;
                }
                $res=json_decode($response->getBody(),true);
                info($att_info['personId'].'[普通]异步模拟人脸识别上报考勤信息',['res'=>$res,
                    'timespan'=>[
                        '请求耗时'=>microtime(true)-$send_time1,
                        '总耗时'=>microtime(true)-$timestamp
                    ]
                ]);
                Redis::del('NORMAL_ATT_INFO:'.$att_info['personId']);
                if(microtime(true)-$timestamp>=59)break;
            }

        })->everyMinute();




		$schedule->call(function(){
            if(!env('UNION_ATT'))return;
		    $con=new UnionAttController();
		    $res=$con->sync_att_notify_time();
		    info('同步考勤推送时间(联动人脸设备专用)',$res);
        })->everyMinute();

		if (env('SNAP')) {
//考勤抓拍设备 terminal_type=5
			$schedule->call(function () {
				$school_id = env('SCHOOL_ID');
				// $vpn = system("ifconfig tap0|grep 'inet '|awk -F \" \" '{print $2}'|awk '{print $1}'");
				$vpn_str = system('sudo ifconfig tap0|grep inet|grep -v inet6', $status);
				info($status);
				$arr_str = explode(' ', $vpn_str);
				$array = array_where($arr_str, function ($key, $value) {
					return $key;
				});
				$array = array_values($array);
				info($array);
				$vpn = isset($array[1]) ? $array[1] : '';
				$terminal_num = env('TERMINAL_NUM');
				info($terminal_num);
				info($vpn);
				if (!$school_id || !$vpn) {
					info('同步数据获取失败!school_id为空或vpn ip 获取失败');
					return;
				}
				$client = new Client;
				$rt = $client->request('POST', env('CURL_URL') . '/heart_sync', [
					"form_params" => [
						"vpn" => $vpn,
						"school_id" => $school_id,
						"terminal_num" => $terminal_num,
						"terminal_type" => 5,
					],
				]);

				$bool = json_decode($rt->getBody(), true);
				if (!$bool['success']) {
					info('心跳同步失败!');
				}

			})->everyMinute();
		}
		else if (env('FACE',false)||env('UNION_ATT',false)) {
//人脸识别设备 terminal_time=4
			//            if(env('SCHOOL_ID')==769){info('仁德一中暫時屏蔽同步2019年8月30日');return;}

			$face = new FaceDetectController();
			info('[弃用]NEW_SYNC:', [env('NEW_SYNC', false)]);
			if(env('AUTO_SYNC',false)){
                //8设置时段  10删除时段  11删除照片
//                $schedule->call(function () {
//                    foreach ([8, 10] as $k => $v) {
//                        $face = new FaceController;
//                        $face->get_task($v);
//                    }
//                })->everyMinute();

//                if(count(Redis::keys('unionSnapPic*'))){
//                    info('联动抓拍中不同步');
//                    return;
//                }

                $schedule->call(function () use ($face) {

//                    if(Redis::exists('IS_RESYNC')){
//                        info('正在重新同步，跳过正常同步');
//                        return ;
//                    }
//                    return;
//                    info('重新同步-测试-exists'.Redis::exists('RESYNC_IPS'));
//                    if(Redis::exists('RESYNC_IPS')){
//                        info('正在重新同步，跳过正常同步');
//                        return ;
//                    }
                    $time_start=microtime(true);
                    //重新同步
//                    Redis::set('IS_RESYNC',true);
//                    $face->get_resyncs();

//                    $this->modifyEnv(['AUTO_SYNC'=>'true']);
//                        info('AUTO_SYNC='.env('AUTO_SYNC'));

                    $union_success_keys=Redis::keys('unionSuccessInfo*');
                    $union_normal_keys=Redis::keys('NORMAL_ATT_INFO*');
//
                    $timeleft=60-(microtime(true)-$time_start);
                    if($timeleft<0){
                        info('重新同步执行完毕跳出');
                        return ;
                    }
                    //同步人员1
//                    if(!count($union_success_keys)&&!count($union_normal_keys)){
                        $face->getAllPersons();
//                    }
                    $face->personDeleteByReids(); //注意顺序！！！！！
                    $timeleft=60-(microtime(true)-$time_start);
                    info('timeleft='.$timeleft);
                    $face->personCreateByRedis($timeleft);
//                    $timeleft=60-(microtime(true)-$time_start);
//                    info('timeleft='.$timeleft);
                    $face->personUpdateByRedis();
                    //上传同步 + test拍照注册

                    $timeleft=60-(microtime(true)-$time_start);
                    info('timeleft='.$timeleft);
                    $face->excuteFaceDel($timeleft);
                    $timeleft=60-(microtime(true)-$time_start);
                    info('timeleft='.$timeleft);
                    $face->excuteFaceImgUpload(100,$timeleft);
                    $timeleft=60-(microtime(true)-$time_start);
                    info('timeleft='.$timeleft);
                    $face->sync_time_permissions($timeleft);
                })->everyMinute();
            }
            elseif(env('AUTO_SYNC_NEW',false)){

                $schedule->call(function ()use($face){
                    //同步人员
                    $face->getAllPersons();
                    $count=$face->sync_person_task();
//                    if(!$count){
//                        $count=$face->sync_time_permissions(59);
//                    }
                    if(!$count){//如果没有人员同步任务，开始同步照片
                        //同步照片
                        $face->sync_photo_task();
                    }
                })->everyMinute();
            }


            //从redis读取拍照注册人员上传并保存到树莓派(废弃)
            //                $schedule->call(function()use($face){
            //                    $face->downFaceFromTerminalById();
            //                })->everyMinute();


        }

        elseif(env('SIT_XXFACE',false)){
            $con=new SitXunXinFaceController();
            $schedule->call(function()use($con){
                $person_cnt=$con->sync_person_task(59);
                if(!$person_cnt){
                    $photo_cnt=$con->sync_photo_task(59);
                    if(!$photo_cnt){
                        $perm_cnt=$con->sync_permission_task(59);
                    }
                }

            })->everyMinute();
        }
		else if (env('DC_CONSUMER')) {
			$schedule->call(function () {
				info('测试东川一中服务主机');
				//每分钟获取pre_wxpay_order该学校未成功的固定数量条目，数量视测试性能而定
				$DC_hd = new DCRechargeController();
				$DC_hd->get_recharges();

			})->everyMinute();


			$schedule->call(function () {
				$con=new DCRechargeController();
				$con->sync_card_info();
				$con->change_sync();
				//TODO:同步cardInfo
			})
//                ->everyFiveMinutes();
				->dailyAt('02:00');
		}
		else if(env('YKT_CONSUMER',false)){
            $con=new YKTRechargeController();
            $schedule->call(function()use ($con){
                $con->get_recharges();
                $con->getRefundTask(0,36000);
                $con->sync_records();
            })->everyMinute();
            $schedule->call(function()use($con){
                $con->sync_card_info();
            })->dailyAt('10:00');
            $schedule->call(function()use($con){
//                $con->getRefundTask(0,36000);
                $con->sync_card_info();
            })->dailyAt('20:00');

        }
        else if(env('HPT_CONSUMER',false)){//需要设置HPT_CONSUMER 和 HPT_SERVER ip:port
		    $con=new HPTRechargeController();
		    $schedule->call(function()use($con){
		        $con->get_recharges();
            })->everyMinute();
		    $schedule->call(function ()use($con){
		        $con->TransactionDetailsSchedule();
            })->everyMinute();
        }
        elseif(env('YKS_CONSUMER',false)){
		    if(!env('YKS_KEY')||!env('YKS_CODE')||!env("YKS_URL")){
		        info('YKS_KEY,YKS_CODE,YKS_URL 未配置！');
		        return;
            }
		    $con=new YKSRechargeController();
            $timeleft_yks=59;
		    $schedule->call(function()use($con,$timeleft_yks){

                $timeleft_yks=$con->get_recharges(59);
                $timeleft_yks=$con->sync_records($timeleft_yks);
            })->everyFiveMinutes();

		    $schedule->call(function()use($con,$timeleft_yks){
		        $con->sync_person_card();
            })->everyThirtyMinutes();

//            $schedule->call(function()use($con,$timeleft_yks){
//                $con->get_recharges($timeleft_yks);
//            })->everyMinute();

        }
		else if (env('WHDC_CONSUMER')) {
			$schedule->call(function () {
				info('测试五和大成消费服务');

			});
		}

		if(!env('DC_CONSUMER')
            &&!env('YKT_CONSUMER')
            &&!env('HPT_CONSUMER')
            &&!env('YKS_CONSUMER')
        ){
		    $schedule->call(function(){
		        system('reboot');
            })->dailyAt('04:00');
        }

	}

	/**
	 * Register the commands for the application.
	 *
	 * @return void
	 */
	protected function commands() {
		$this->load(__DIR__ . '/Commands');

		require base_path('routes/console.php');
	}

	//TODO:定时获取冲销记录
	protected function getYKTRecord($timeInterval){
	    $school_id=env('SCHOOL_ID','');
	    if(!$school_id){
	        info('学校id未配置！');
	        return false;
        }
//	    $list=\DB::table('lssj')->where('')
    }

    function modifyEnv(array $data)
    {
        $envPath = base_path() . DIRECTORY_SEPARATOR . '.env';

        $contentArray = collect(file($envPath, FILE_IGNORE_NEW_LINES));

        $contentArray->transform(function ($item) use ($data){
            foreach ($data as $key => $value){
                if(str_contains($item, $key)){
                    return $key . '=' . $value;
                }
            }

            return $item;
        });

        $content = implode($contentArray->toArray(), "\n");

        \File::put($envPath, $content);
    }


}
