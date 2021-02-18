<?php
namespace App\Handler;

use App\Http\Controllers\Api\UnionAttController;
use App\Services\Tools;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Storage;

class SwooleHandler {
    use Tools;
	protected $camera_status;
	//连接
	public function onConnect($server, $fd, $from_id) {
	    echo "连接成功" . $fd . PHP_EOL;
	}

	//关闭连接
	public function onClose($server, $fd, $from_id) {
		echo "connection close: {$fd}\n";
	}

	//接收数据
	public function onReceive($server, $fd, $from_id, $data) {

		$data_len = unpack('C*', $data);

		$head = unpack('A2', $data);
		info('收到控制板消息：fd=' . $fd);
		if (count($data_len) < 9) {
			info('收到控制板数据包-长度小于9-返回');
			return;
		}

		//包头验证
		if ($head[1] != 'gy') {
			echo 'head';
			info('head is valid :' . $head[1]);
			$server->close($fd);
		}

//		$head = unpack('A2', $data);
//		$len = unpack('v', $data, 2);
//		$terminal = unpack('A2', $data, 4);
		$cmd = unpack('H2', $data, 6);
		$card_arr = unpack('V', $data, 13);
        $door_num = unpack('C', $data, 17); //通道
        $enter = unpack('h', $data, 18); //进出
		info('卡号', [$this->uint32val($card_arr[1])]);
//		info($cmd);
		if ($cmd[1] == '0f') {
			//刷卡抓拍
			if (env('FACE', false)&&!env('UNION_ATT',false) ) {
			    $terminal_type=$this->getTerminalTypeForSnap($door_num[1],$enter[1]);
				if ($terminal_type=='face') {
                    // info('新-人脸设备-刷卡什么也不做');
                    if (env('DISABLE_TAKE_FACE', false)) {
                        info('屏蔽刷卡拍照');
                    } else {
//                        $this->face_take_img_new($data);
                    }
				}elseif($terminal_type=='snap'){
                    info('[混合]摄像头设备-刷卡抓拍-已注释');
//                    $datainfo = [
//                        'type' => 1,
//                        'data' => $data,
//                    ];
//                    $server->task($datainfo);
                }
//
			}
			else if(env('UNION_ATT',false)){
			    //该模式同时存在控制板和人脸识别两条路线
			    //刷卡不触发人脸识别拍照注册
                //
                info('联动开门认证-刷卡抓拍-通道-'.$door_num[1]);

                //语音
//                $send_data=$this->pack_voice('一二三四五六七八九十一二');
//                $server->send($fd,$send_data);

//触发抓拍
                $datainfo = [
                    'type' => 1,
                    'data' => $data,
                    'fd'=>$fd
                ];
                $server->task($datainfo);
            }
            elseif(env('UNION_ATT_SOFT',false)){
                info('联动开门认证-软件版刷卡');
                $datainfo = [
                    'type' => 1,
                    'data' => $data,
                    'fd'=>$fd
                ];
                $server->task($datainfo);
            }
			else {
				info('摄像头设备-刷卡抓拍');
				$datainfo = [
					'type' => 1,
					'data' => $data
				];
				$body = pack('vA2Ch', dechex(5), 'yz', 0x8c, 0);
				$send_data = $this->send_data($body);
				$server->send($fd, $send_data);
				$server->task($datainfo);
			}

		}
		else if ($cmd[1] == '0e') {
			//非法闯入7秒视频抓拍
			return;
			$door_num_arr = unpack('H2', $data, 13);
			$door_num = (int) $door_num_arr[1];
			$num = env('SCHOOL_ID') . time() . $door_num;
			$in = $door_num * 5 + 1;
			$out = $door_num * 5 + 2;
			//$video_ip = $this->get_video_ip($door_num,$num,$in,$out);

			if (!isset($this->camera_status[$door_num]) && !$this->camera_status[$door_num]) {
				$this->camera_status = [$door_num => true];
				$data = [$in, $out];
				foreach ($data as $k => $v) {
					$str = $this->get_cmd($v, $num, 'video', 7);
					$arr = [
						'type' => 2,
						'cmd' => $str,
						'door_num' => $door_num,
						'in_or_out' => $v,
						'num' => $num,
						'time' => time(),
					];
					$server->task($arr);
				}
			}

		}

	}

	public function pack_voice($string){
//        $gbk_string="[v10][s5]".$string;
        $string=' '.$string;
        $body=pack('A2C','VO',0x0b);
        $encode=mb_detect_encoding($string,array("ASCII","UTF-8","GB2312","GBK",'BIG5'));
        $gbk_string=iconv($encode,'gbk//TRANSLIT//IGNORE',$string);
        $gbk_string="[v10][s5]".$gbk_string;
//        $gbk_string.=pack('C',0);
        $send_data=$body.trim($gbk_string).pack('C',0);
        return $send_data;
    }

	public function get_cmd($in, $num, $folder, $time) {
		$ip = env('VIDEO_IP');
		$str = 'ffmpeg -i rtsp://' . env('CAMERA_USER_NAME', 'admin') . ':' . env('CAMERA_PWD', 'admin123') . '@' . $ip . $in . ':554/h264/ch1/main/av_stream -vcodec copy -acodec copy -ss 0 -t ' . $time . ' -f mp4 ' . env('PATH_ATT') . '/storage/app/public/' . $folder . '/' . $num . $in . '.mp4';
		return $str;
	}

	//type1 摄像头抓拍照片  type2 非法闯入7秒视频抓拍
	public function onTask($server, $task_id, $src_worker_id, $data) {
//	    info('',['task_id'=>$task_id,'src_worker_id'=>$src_worker_id]);
		if ($data['type'] == 2) {
			system($data['cmd'], $rt);

			$server->finish($data);
		} else {
//		    $fd=$data['fd'];
			$data_new = $data['data'];
            $time = unpack('C6', $data_new, 7); //刷卡时间
            $str_date = "20" . $time[1] . "-" . $time[2] . "-" . $time[3] . " " . $time[4] . ":" . $time[5] . ":" . $time[6];
            $time_int = strtotime($str_date);

            $card_arr = unpack('V', $data_new, 13); //卡号
            $door_num = unpack('h', $data_new, 17); //通道
            $enter = unpack('h', $data_new, 18); //进出
            $card = $this->uint32val($card_arr[1]);
			if (env('SNAP_VIDEO')) {
				//视频抓拍
				if ($enter[1] == 1) {
					$in_out = $door_num[1] * 5 + 1;
					$enter = 1;
				} else {
					$in_out = $door_num[1] * 5 + 2;
					$enter = 0;
				}
				$num = env('SCHOOL_ID') . time() . $door_num[1];

				$folder = 'att_video';

				$str = $this->get_cmd($in_out, $num, $folder, 4);

				system($str, $rt);

				$data1 = [
					'type' => 3,
					'num' => $num,
					'card' => $card,
					'int_time' => $time_int,
					'door_num' => $door_num[1],
					'enter' => $enter,
					'folder' => $folder,
					'in_out' => $in_out,
				];
				$server->finish($data1);
			}
			else {
			    if(env('UNION_ATT',false)){//默认控制板只作为登记器
			        $this->union_snap($data,$server);
                }
                elseif(env('UNION_ATT_SOFT',false)){//区分学生刷卡 家长登记
			        if(strstr(env('UNION_REG_DOOR'),'D'.$door_num[1])){
			            $this->union_snap($data,$server);//家长刷卡登记
                    }
                    else
                        $this->union_soft_snap($data,$server);//学生刷卡判断抓拍
                }
                else{
                    //图片抓拍
                    $this->camear_snap($data_new, $server);
                }
			}

		}

	}

	public function onFinish($serv, $task_id, $data) {
		if (isset($data['type']) && $data['type'] == 2) {
			$file = fopen(env('PATH_ATT') . '/storage/app/public/video/' . $data['num'] . $data['in_or_out'] . '.mp4', 'r');
			//var_dump($file);
			$client = new Client();
			$response = $client->request('POST', env('CURL_URL') . '/send_video', [
				'multipart' => [
					[
						'name' => 'upload_att_image',
						'contents' => 'abc',
						'headers' => [],
					],
					[
						'name' => 'video',
						'contents' => $file,
					],
					[
						'name' => 'door_num',
						'contents' => $data['door_num'],
					],
					[
						'name' => 'school_id',
						'contents' => env('SCHOOL_ID', 0),
					],
					[
						'name' => 'in_or_out',
						'contents' => $data['in_or_out'],
					],
					[
						'name' => 'time',
						'contents' => $data['time'],
					],
				],
			]);
			$rt = json_decode($response->getBody(), true);
			//关闭文件资源
			@fclose($file);

			unset($this->camera_status[$data['door_num']]);

		} else if (isset($data['type']) && $data['type'] == 3) {
			$this->get_att_video($serv, $data);
		}
	}

	public function send_data($body) {
		$head = pack('A2', 'gy');
		$sum = dechex(array_sum(unpack('C*', $body)) % 256);
		$send_data = $head . $body . pack('C', $sum);

		return $send_data;
	}

	//获取摄像头IP
	public function get_ip($door_num, $enter) {
		$ip = env('CAMERA_IP');
		$i = $door_num * 5 + $enter;
		return $ip . $i . '/ISAPI/Streaming/channels/1/picture';
	}

	public function get_face_ip($door_num, $enter) {
		$ip = env('VIDEO_IP');
		$i = $door_num * 5 + ($enter + 2);
		return $ip . $i;
	}

	protected function uint32val($var) {
		if (is_string($var)) {
			if (PHP_INT_MAX > 2147483647) {
				$var = intval($var);
			} else {
				$var = floatval($var);
			}
		}
		if (!is_int($var)) {
			$var = intval($var);
		}
		if ((0 > $var) || ($var > 4294967295)) {
			$var &= 4294967295;
			if (0 > $var) {
				$var = sprintf("%u", $var);
			}
		}
		return $var;
	}

	protected function union_soft_snap($data,$server){
//	    info('test',$data);
        $fd=$data['fd'];
        $data=$data['data'];

        $time = unpack('C6', $data, 7); //刷卡时间
        $str_date = "20" . $time[1] . "-" . $time[2] . "-" . $time[3] . " " . $time[4] . ":" . $time[5] . ":" . $time[6];
        $time_int = strtotime($str_date);
        $card_arr = unpack('V', $data, 13); //卡号
        $door_num = unpack('C', $data, 17); //通道
        $enter = unpack('h', $data, 18); //进出
        $card = $this->uint32val($card_arr[1]);
        $ip = $this->get_ip($door_num[1], $enter[1]);

        if ($enter[1] == 1) {
            $enter = 1; //进
        } else {
            $enter = 0;
        }
        $allkeys = Redis::keys('PERSON:*');
        $list = Redis::pipeline(function ($pipe) use ($allkeys) {
            foreach ($allkeys as $item) {
                $pipe->get($item);
            }
        });
        $person=false;
        foreach ($list as $key => $value) {
            $varr=json_decode($value,true);
            $all_cards=explode(',',$varr['all_cards']);
            foreach ($all_cards as $k=>$v){
                if($v==$card){
                    $person=$varr;
                    info('找到人员' . $person['id'].'  '.$card.'  '.$person['stu_name']);
                }
            }
        }
        if(!$person){
            info('陌生卡'.$card);
            //陌生卡 播音 TODO：播音返回
            $send_data=$this->pack_voice('陌生卡');
            $server->send($fd,$send_data);
            return false;

        }
        else{
            //找到人员
            if($enter){//进入
                info($person['id'].'进校');
                //什么也不做
            }
            else{
                if(!array_get($person,'union_time',false)){
                    info($person['id'].'不是联动 正常开门');
                    //不是联动 往下走开门
                }
                else{
                    info($person['id'].'是联动人员');
                    //是联动 判断时段
                    $timestr=$person['union_time'];
                    $now=date('H:i');
                    $timearr=json_decode($timestr,true);
                    $is_in_time=false;
                    foreach($timearr as $time){
                        if($now>$time['start_time'] && $now<$time['end_time']){
                            info('联动时段判断-true',[
                                'time'=>$time
                            ]);
                            $is_in_time=true;
                            break;
                        }
                    }
                    if(!$is_in_time){
                        info($person['id'].'不在连动时段 开门');
                        //不在连动时段 往下走，开门
                    }
                    else{
                        info($person['id'].'在连动时段');
                        //在联动时段 判断联动是否成功
                        if(Redis::exists('unionAuthFamilySoft:'.$person['id'])){
                            info($person['id'].'联动成功 开门');
                            //成功开门 往下走开门
                        }
                        else{
                            info($person['id'].'联动不成功 返回');
                            //联动不成功 TODO：波音 返回

                            //控制板语音播音
                            $send_data=$this->pack_voice('家长未登记');
                            $server->send($fd,$send_data);
                            return false;
                        }
                    }

                }
            }
        }
        //开门信号
        info('远程开门 ');
        $body = pack('A2H2H2H2','SR',$enter[1]==1?'01':'02','f4','01');
        $server->send($fd,$body);
        usleep(100000);
        //控制板语音播音
        $send_data=$this->pack_voice($person['stu_name']);
        $server->send($fd,$send_data);
        //发送考勤
        $client=new Client();
        $rt = $client->request('GET', $ip,
            ['auth' => [env('CAMERA_USER_NAME', 'admin'), env('CAMERA_PWD', 'admin123')]]);
        file_put_contents(env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg', $rt->getBody());


        $file = fopen(env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg', 'r');
        $client = new Client();
        $data = [
            'dateTime' => date('Y-m-d H:i:s', $time_int),
            'door_num' => $door_num[1] ? $door_num[1] : 0,
            'school_code' => env('SCHOOL_CODE', 0), //没用到
            'card' => $card,
            'enter' => $enter,
            'id' => $door_num[1] . $time_int,
        ];
        ksort($data);
        try {
            $response = $client->request('POST', env('CURL_URL') . '/send_img_attendance', [
                'verify' => false,
                'multipart' => [
                    [
                        'name' => 'upload_att_image',
                        'contents' => 'abc',
                        'headers' => [],
                    ],
                    [
                        'name' => 'image',
                        'contents' => $file,
                    ],
                    [
                        'name' => 'int_time',
                        'contents' => $time_int,
                    ],
                    [
                        'name' => 'school_id',
                        'contents' => env('SCHOOL_ID', 0),
                    ],
                    [
                        'name' => 'card',
                        'contents' => $card,
                    ],
                    [
                        'name' => 'door_num',
                        'contents' => $door_num[1] ? $door_num[1] : 0,
                    ],
                    [
                        'name' => 'enter',
                        'contents' => $enter,
                    ],
                    [
                        'name' => 'type',
                        'contents' => 1, //没用
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            info('刷卡抓拍-三叶草-异常-' . $e->getMessage());
            return false;
        }
        $rt = json_decode($response->getBody(), true);

        //关闭文件资源
        @fclose($file);
        info('刷卡抓拍-结果-', [$rt]);
        if (isset($rt['code'])) {
            $server->finish($data);
        } else {
            system('rm -rf ' . env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg');
            //Storage::disk('att_image')->delete($card.$door_num[1].$time_int.'.jpg');
        }


    }

	protected function union_snap($data,$server){
	    $fd=$data['fd'];
	    $data=$data['data'];
        $time = unpack('C6', $data, 7); //刷卡时间
        $str_date = "20" . $time[1] . "-" . $time[2] . "-" . $time[3] . " " . $time[4] . ":" . $time[5] . ":" . $time[6];
        $time_int = strtotime($str_date);

        $card_arr = unpack('V', $data, 13); //卡号
        $door_num = unpack('C', $data, 17); //通道
        $enter = unpack('h', $data, 18); //进出
        $card = $this->uint32val($card_arr[1]);
        $timetest2=microtime(true);


//        $client = new Client();
//        $url=env('CURL_URL').'/union_auth/get_person_info?card_id='.$card.'&school_id='.env('SCHOOL_ID');
//        $response=$client->request('GET',$url);
//        $union_person=json_decode($response->getBody(),true);
//        if($union_person['code']==1)
//        {
//            info('[联动]不存在该卡号对应的联动学生:'.$union_person['msg'] ,[
//                '单元耗时'=>microtime(true)-$timetest2
//            ]);
//            return false;
//        }
//        $union_person=$union_person['data'];
        $person_id=Redis::get('UNION_CARD:'.$card);
        if($person_id){
            $union_person=Redis::get('PERSON:'.$person_id);
            if(!$union_person){
                info('[联动]redis人员不存:id=' .$person_id,[
                    '单元耗时'=>microtime(true)-$timetest2
                ]);
                return false;}
            $union_person=json_decode($union_person,true);

        }
        else{
            info('[联动]不存在该卡号对应的联动学生:card=' .$card,[
                '单元耗时'=>microtime(true)-$timetest2
            ]);
            return false;
        }

        $personId=$union_person['id'];
        $stu_name=$union_person['stu_name'];
        info('找到人员' . $personId.'  '.$card.'  '.$stu_name,[
            '单元耗时'=>microtime(true)-$timetest2
        ]);

        //开门信号-继电器控制亮灯
        info('继电器控制亮灯 '.$stu_name);
        $body = pack('vA2H2H2',5,'yz',sprintf('%02x',(int)71),sprintf('%02x',0));
        $body = pack('A2H2H2H2','SR',$enter[1]==1?'01':'02','f4','01');
        $send_data = $this->send_data($body);
        $server->send($fd,$body);
        if(!$union_person['union_time']){//学生不是联动
            info('[联动]学生不是联动-刷卡亮灯返回:' ,[
                '单元耗时'=>microtime(true)-$timetest2
            ]);
            return false;
        }
        //控制板语音播音
//        $format=env('UNION_VOICE','');
//        $stu_name=sprintf($format,trim($stu_name));
//        info('语音字符串 '.$stu_name);
//        $send_data=$this->pack_voice($stu_name);
//        $server->send($fd,$send_data);


//        //http 抓拍
        $timetest3=microtime(true);
        $time=Redis::get('unionTime');
        info('刷卡-联动时间'.$time);
        Redis::setex('unionAuthFamilySoft:'.$personId,$time,time());

//        $ip = $this->get_ip($door_num[1], $enter[1]);
//        info($ip);
//        //http 抓拍
//        $timetest3=microtime(true);
//        info($door_num[1].'通道'.($enter[1]==1?'进':'出'));
//        if($door_num[1]==1||$door_num[1]==2){
//
//            Redis::setex('unionAuthFamilyIn:'.$personId,$time,time());
//            Redis::set('unionSnapIn',3);
//            info('刷卡进');
//        }
//        elseif($door_num[1]==3||$door_num[1]==4){
//
//            Redis::setex('unionAuthFamilyOut:'.$personId,$time,time());
//            Redis::set('unionSnapOut',3);
//            info('刷卡出');
//        }
        info('设置unionAuthFamilySoft'.$personId.'time='.$time,[
            '单元耗时'=>microtime(true)-$timetest3
        ]);

        //发送家长通知
        //
        $family_pic=false;
        $now_time=time()-1;
        $camera_ip=$this->getPathCameraIP($door_num[1]);
        $keys=Redis::keys('unionSnapPic'.$camera_ip.'*');
        if(count($keys)){
            $family_pic=Redis::get($keys[0]);
        }
        else{
            $family_pic=false;
        }
//        for($i=0;$i<5;$i++){
//            $family_pic=Redis::get('unionSnapPic'.$camera_ip.($now_time-$i));
//            if($family_pic){
//                info('[联动]'.$camera_ip.'家长登记-$snap_time='.$now_time.'-find_time='.($now_time-$i));
//                break;
//            }
//            if($door_num[1]==3||$door_num[1]==4){
//                $family_pic=Redis::get('unionSnapPicOut'.($now_time-$i));
//                if($family_pic){
//                    info('[联动]'.$camera_ip.'家长登记-$snap_time='.$now_time.'-find_time='.($now_time-$i));
//                    break;
//                }
//            }
//            elseif($door_num[1]==1||$door_num[1]==2){
//                $family_pic=Redis::get('unionSnapPicIn'.($now_time-$i));
//                if($family_pic){
//                    info('[联动]30.6家长登记-$snap_time='.$now_time.'-find_time='.($now_time-$i));
//                    break;
//                }
//            }
//        }
        if(!$family_pic){
            info('[联动]家长登记-没找到家长照片-');
//            info('[联动]家长登记-没找到家长照片-不发送通知');
//            return false;
        }
//        else{
        if(true){
            //访问sync_face_test()接口
            Redis::set('unionFamilyRegist'.$personId,json_encode([
                'person_id'=>$personId,
                'card_id'=>$card,
                'time'=>date('Y-m-d H:i:s',time()),//string Y-m-d H:i:s
                'imgBase64'=>$family_pic
            ]));
//            try {
//                $send_time4=microtime(true);
//                $client=new Client();
//                $response = $client->request('post', env('CURL_URL') . '/union/face/send_union_register', [
//                    'timeout'=>1,
//                    'form_params' => [
//                        'person_id'=>$personId,
//                        'card_id'=>$card,
//                        'time'=>date('Y-m-d H:i:s',time()),//string Y-m-d H:i:s
//                        'imgBase64'=>$family_pic
//                    ]
//                ]);
//                $res=json_decode($response->getBody(),true);
//                info($personId.'[联动]家长登记-上报推送登记通知',$res);
//                info('返回252',
//                    [
//                        '上报耗时'=>microtime(true)-$send_time4,
////                        '总耗时'=>microtime(true)-$timestamp
//                    ]);
//
//            }
//            catch (\Throwable $e) {
//                info($personId.'[联动]家长登记-'.$e->getMessage());
//            }

        }

    }

    function getPathCameraIP($door_num){
	    $ipstr=env('UNION_ATT',false);
	    if(!$ipstr)return false;
	    $iparr=explode(',',$ipstr);
	    if($door_num==1||$door_num==2){
            return array_get($iparr,0);
        }
        elseif($door_num==3||$door_num==4){
            return array_get($iparr,1);
        }
        elseif($door_num==5||$door_num==6){
            return array_get($iparr,2);
        }
        elseif($door_num==7||$door_num==8){
            return array_get($iparr,3);
        }
        return false;
    }


	protected function camear_snap($data, $server) {
		$time = unpack('C6', $data, 7); //刷卡时间
		$str_date = "20" . $time[1] . "-" . $time[2] . "-" . $time[3] . " " . $time[4] . ":" . $time[5] . ":" . $time[6];
		$time_int = strtotime($str_date);

		$card_arr = unpack('V', $data, 13); //卡号
		$door_num = unpack('C', $data, 17); //通道
		$enter = unpack('h', $data, 18); //进出
		$card = $this->uint32val($card_arr[1]);

		//$card = sprintf('%010s',$card_str);
		//var_dump($card);
		$ip = $this->get_ip($door_num[1], $enter[1]);
		info($ip);
		//http 抓拍
		$client = new Client();
		$rt = $client->request('GET', $ip,
			['auth' => [env('CAMERA_USER_NAME', 'admin'), env('CAMERA_PWD', 'admin123')]]);

		file_put_contents(env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg', $rt->getBody());

//		$contents = Storage::get('public/att_images/' . $card . $door_num[1] . $time_int . '.jpg');

		if ($enter[1] == 1) {
			$enter = 1; //进
		} else {
			$enter = 0;
		}
		$file = fopen(env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg', 'r');
		$client = new Client();
		$data = [
			'dateTime' => date('Y-m-d H:i:s', $time_int),
			'door_num' => $door_num[1] ? $door_num[1] : 0,
			'school_code' => env('SCHOOL_CODE', 0), //没用到
			'card' => $card,
			'enter' => $enter,
			'id' => $door_num[1] . $time_int,
		];
		ksort($data);
		$sign = $this->get_sign($data, config('secret.secret'));
		$json_data = json_encode($data);
		if (config('secret.secret')) {
//是开放平台的第三方用户的secret,像嵩明县一中用的抓拍服务器就配置了secret
			try {
				$response = $client->request('POST', env('CURL_URL'), [
					'verify' => false,
					'multipart' => [
						[
							'name' => 'upload_att_image',
							'contents' => 'abc',
							'headers' => [],
						],
						[
							'name' => 'image',
							'contents' => $file,
						],
						[
							'name' => 'body',
							'contents' => $json_data,
						],
						[
							'name' => 'video',
							'contents' => '',
						],
						[
							'name' => 'sign',
							'contents' => $sign,
						],
					],
				]);
			} catch (\Exception $e) {
				info('刷卡抓拍-开放平台-异常-' . $e->getMessage());
				return false;
			}

		}
		else {
			try {
				$response = $client->request('POST', env('CURL_URL') . '/send_img_attendance', [
					'verify' => false,
					'multipart' => [
						[
							'name' => 'upload_att_image',
							'contents' => 'abc',
							'headers' => [],
						],
						[
							'name' => 'image',
							'contents' => $file,
						],
						[
							'name' => 'int_time',
							'contents' => $time_int,
						],
						[
							'name' => 'school_id',
							'contents' => env('SCHOOL_ID', 0),
						],
						[
							'name' => 'card',
							'contents' => $card,
						],
						[
							'name' => 'door_num',
							'contents' => $door_num[1] ? $door_num[1] : 0,
						],
						[
							'name' => 'enter',
							'contents' => $enter,
						],
						[
							'name' => 'type',
							'contents' => 1, //没用
						],
					],
				]);
			} catch (\Exception $e) {
				info('刷卡抓拍-三叶草-异常-' . $e->getMessage());
				return false;
			}

		}

		$rt = json_decode($response->getBody(), true);

		//关闭文件资源
		@fclose($file);
		info('刷卡抓拍-结果-', [$rt]);
		if (isset($rt['code'])) {
			$server->finish($data);
		} else {
			system('rm -rf ' . env('PATH_ATT') . '/storage/app/public/att_images/' . $card . $door_num[1] . $time_int . '.jpg');
			//Storage::disk('att_image')->delete($card.$door_num[1].$time_int.'.jpg');
		}
	}

	protected function get_att_video($server, $data) {
		$file = fopen(env('PATH_ATT') . '/storage/app/public/' . $data['folder'] . '/' . $data['num'] . $data['in_out'] . '.mp4', 'r');
		$client = new Client();

		$door_num = $data['door_num'] ? $data['door_num'] : 0;
		$send_data = [
			'dateTime' => date('Y-m-d H:i:s', $data['int_time']),
			'door_num' => $door_num,
			'school_code' => env('SCHOOL_CODE', 0),
			'card' => $data['card'],
			'enter' => $data['enter'],
			'id' => $door_num . $data['int_time'],
			'school_code' => env('SCHOOL_CODE', 0),
		];
		ksort($send_data);
		$sign = $this->get_sign($data, config('secret.secret'));
		$json_data = json_encode($data);
		if (config('secret.secret')) {
			$response = $client->request('POST', env('CURL_URL'), [
				'verify' => false,
				'multipart' => [
					[
						'name' => 'upload_att_image',
						'contents' => 'abc',
						'headers' => [],
					],
					[
						'name' => 'image',
						'contents' => '',
					],
					[
						'name' => 'body',
						'contents' => $json_data,
					],
					[
						'name' => 'video',
						'contents' => $file,
					],
					[
						'name' => 'sign',
						'contents' => $sign,
					],
				],
			]);
		} else {
			$response = $client->request('POST', env('CURL_URL') . '/send_img_attendance', [
				'verify' => false,
				'multipart' => [
					[
						'name' => 'upload_att_image',
						'contents' => 'abc',
						'headers' => [],
					],
					[
						'name' => 'video',
						'contents' => $file,
					],
					[
						'name' => 'int_time',
						'contents' => $data['int_time'],
					],
					[
						'name' => 'school_id',
						'contents' => env('SCHOOL_ID', 0),
					],
					[
						'name' => 'card',
						'contents' => $data['card'],
					],
					[
						'name' => 'door_num',
						'contents' => $door_num,
					],
					[
						'name' => 'enter',
						'contents' => $data['enter'],
					],
					[
						'name' => 'type',
						'contents' => 1,
					],
				],
			]);
		}

		$rt = json_decode($response->getBody(), true);

		//关闭文件资源
		@fclose($file);
		info($rt);
		if (!$rt['code']) {
			$server->finish($data);
		} else {
			Storage::disk('att_video')->delete('storage/app/public/' . $data['folder'] . '/' . $data['card'] . $data['door_num'] . $data['int_time'] . '.mp4');
		}
	}

	protected function get_sign($data, $secret) {
		$str_data = json_encode($data);
		return md5($str_data . $secret);
	}

	//废弃
	public function face_take_img_new($data) {

		$card_arr = unpack('V', $data, 13); //卡号
		$door_num = unpack('C', $data, 17); //通道
		$enter = unpack('h', $data, 18); //进出
		$card = $this->uint32val($card_arr[1]);
		$ip = $this->get_face_ip($door_num[1], $enter[1]);
		$allkeys = Redis::keys('PERSON:*');
		$list = Redis::pipeline(function ($pipe) use ($allkeys) {
			foreach ($allkeys as $item) {
				$pipe->get($item);
			}
		});
		$stu = '';
		foreach ($list as $key => $value) {
			# code...
			$varr=json_decode($value,true);
			if ($varr['card_id']==$card) {

				$stu = $value;
				info('找到人员' . $stu.'  '.$card);
			} else {
				continue;
			}
		}
		if (!$stu) {

			info('redis中不存在该卡号对应的学生:' . $stu);
			return;
		}

		$data1 = json_decode($stu, true);
		$FDC = new FaceDetectController();
		$getFace = $FDC->searchFaceImg($ip, $data1['id']);
		if ($getFace['data']) {
            $photo_count=count($getFace['data']);
            //设备上每个人员最多三张照片，索引为0，1，2 最新的在0
			if ($photo_count) {
//有照片
				info('刷卡-人脸识别-照片查询-有照片-'.$photo_count);
				foreach ($getFace['data'] as $key => $value) {
					# code..
//					$FDC->delFaceByFid($ip, $value['faceId']);
                    info($key.'--',$value);
				}
				//达到三张照片 将最旧的照片删除
				if($photo_count==3){
				    $faceId=$getFace['data'][2]['faceId'];
					$FDC->delFaceByFid($ip, $faceId);
					info('删除 0--',$getFace['data'][2]);
                }
			}
		}
		else{
		    info('刷卡-人脸识别-照片查询-接口请求失败');
		    return;
        }
		//拍照注册   这个接口只负责打开拍照界面，拍照注册成不成功并不知道，所以存入redis的信息有可能没有照片
		$takeImg = $FDC->faceTakeImg($ip, $data1['id']);
		if (!$takeImg['success']) {
			info('刷卡-人脸识别-拍照注册-接口请求失败', $takeImg);
			return;
		}
		info('刷卡-人脸识别-拍照注册-接口请求成功等待拍照');

		return;

	}

	//人脸抓拍注册
	public function face_take_img($data) {
		$card_arr = unpack('V', $data, 13); //卡号
		$door_num = unpack('C', $data, 17); //通道
		$enter = unpack('h', $data, 18); //进出
		$card = $this->uint32val($card_arr[1]);

		$ip = $this->get_face_ip($door_num[1], $enter[1]);
		//$ip = '192.168.30.102';
		$client = new Client();
		$response = $client->request('POST', env('CURL_URL') . '/get_stu', [
			'form_params' => [
				'school_id' => env('SCHOOL_ID'),
				'card_id' => $card,
				'ip' => $ip,
			],
		]);

		$rt = json_decode($response->getBody(), true);
		info($rt);
		if ($rt['code'] == 1) {
			info($rt['msg']);
			return;
		}
		info('disable_take_face', [env('DISABLE_TAKE_FACE', false)]);

		if (env('DISABLE_TAKE_FACE', false)) {
			info('======================仁德二中屏蔽录入人脸======================');
			return;
		}
		$this->face_img($ip, $rt['data']['id'], $rt['data']['card_id'], $rt['data']['stu_name'], $rt['data']['pass']);

	}

	protected function create_face($ip, $id, $idcardNum, $name, $pass) {
		info('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!create_face!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
		$arr = [
			'id' => $id,
			'idcardNum' => $idcardNum,
			'name' => $name,
		];
		$send_data = json_encode($arr);
		$handler = new ClientHandler;
		//人员注册
		$resp = $handler->person_create($ip, $send_data, $pass);
		//照片查询
		$handler = new ClientHandler;
		$resp = $handler->getFaceImg($ip, $id, $pass);
		info($resp);
		if ($resp['success']) {
			//有照片
			if (count($resp['data'])) {
				$data = $resp['data'];
				info('--------------------------');
				info($data);
				for ($i = env('DOOR_NUM'); $i >= 1; $i--) {
					//if($i != $door_num[1]){
					$en = [1, 2];
					foreach ($en as $k => $v) {
						$ip = $this->get_face_ip($i, $v);
						$arr = [
							'id' => $id,
							'idcardNum' => $idcardNum,
							'name' => $name,
						];
						$send_data = json_encode($arr);
						$handler = new ClientHandler;
						//人员注册
						$resp = $handler->person_create($ip, $send_data, $pass);

						if ($resp['success']) {
							$r = $handler->createByurl($ip, $pass, $id, $data[0]['faceId'], $data[0]['path']);
							info($r);
						}
					}
				}
			} else {
				//没有照片
				$handler = new ClientHandler;
				$handler->faceTakeImg($ip, $id, $pass);
				sleep(15);
				$resp = $handler->getFaceImg($ip, $id, $pass);
				if ($resp['success']) {
					//有照片
					if (count($resp['data'])) {
						$data = $resp['data'];
						for ($i = env('DOOR_NUM'); $i >= 1; $i--) {
							//if($i != $door_num[1]){
							$en = [1, 2];
							foreach ($en as $k => $v) {
								$ip = $this->get_face_ip($i, $v);
								$arr = [
									'id' => $id,
									'idcardNum' => $idcardNum,
									'name' => $name,
								];
								$send_data = json_encode($arr);
								$handler = new ClientHandler;
								//人员注册
								$resp = $handler->person_create($ip, $send_data, $pass);

								if ($resp['success']) {
									$r = $handler->createByurl($ip, $pass, $id, $data[0]['faceId'], $data[0]['path']);
									info($r);
								}
							}
						}
					}
				}

			}
		}

	}

	public function face_img($ip, $id, $idcardNum, $name, $pass) {
		$arr = [
			'id' => $id,
			'idcardNum' => $idcardNum,
			'name' => $name,
		];
		$send_data = json_encode($arr);
		$handler = new ClientHandler;
		//人员注册
		$handler->person_create($ip, $send_data, $pass);
		//照片查询
		$handler = new ClientHandler;
		$resp = $handler->getFaceImg($ip, $id, $pass);

		if ($resp['success']) {
			if (!count($resp['data'])) {
//如果没有照片
				$handler = new ClientHandler;
				info('拍照注册-开始');
				$rt = $handler->faceTakeImg($ip, $id, $pass); //拍照注册

				if ($rt['success']) {
					info('拍照注册-成功');
					$client = new Client();
					info('刷卡拍照-修改is_face_sync-开始');
					$response = $client->request('POST', env('CURL_URL') . '/face/sync_stu/is_face_sync', [
						'form_params' => [
							'student_id' => $id,
						],
					]);
					$rt = json_decode($response->getBody(), true);
					info($rt);
					info('刷卡拍照-修改is_face_sync-结束');

				}
			} else {
				info('人脸已存在');
				info($resp['data']);
				//info(count($resp['data']));
			}
		}

	}

}
