<?php
namespace App\Handler;

use App\Http\Controllers\Api\UnionAttController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Storage;

class SwooleHandler {
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

		$head = unpack('A2', $data);
		$len = unpack('v', $data, 2);
		$terminal = unpack('A2', $data, 4);
		$cmd = unpack('H2', $data, 6);
		$card_arr = unpack('V', $data, 13);
		info('卡号', [$this->uint32val($card_arr[1])]);
		info($cmd);
		if ($cmd[1] == '0f') {
			//刷卡抓拍
			if (env('FACE', false)&&!env('UNION_ATT',false) ) {
				if (env('NEW_SYNC', false)) {
					// info('新-人脸设备-刷卡什么也不做');
					if (env('DISABLE_TAKE_FACE', false)) {
						info('屏蔽刷卡拍照');
					} else {
						$this->face_take_img_new($data);
					}
				}
//                else if(env('SCHOOL_ID',0)==769 ){
				//                    info('仁德一中特殊');
				//                }
				else {
					info('旧-人脸设备-刷卡抓拍');
					$this->face_take_img($data);
				}
			}
			else if(env('UNION_ATT',false)){
			    //该模式同时存在控制板和人脸识别两条路线
			    //刷卡不触发人脸识别拍照注册
                //
                info('联动开门认证-刷卡抓拍');

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
        $body=pack('A2C','VO',0x0b);
        $encode=mb_detect_encoding($string,array("ASCII","UTF-8","GB2312","GBK",'BIG5'));
        $gbk_string=iconv($encode,'gbk//TRANSLIT//IGNORE',$string);
        $gbk_string.=pack('C',0);
        $send_data=$body.$gbk_string;
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
		    $fd=$data['fd'];
			$data_new = $data['data'];
			if (env('SNAP_VIDEO')) {
				//视频抓拍
				$time = unpack('C6', $data_new, 7); //刷卡时间
				$str_date = "20" . $time[1] . "-" . $time[2] . "-" . $time[3] . " " . $time[4] . ":" . $time[5] . ":" . $time[6];
				$time_int = strtotime($str_date);

				$card_arr = unpack('V', $data_new, 13); //卡号
				$door_num = unpack('h', $data_new, 17); //通道
				$enter = unpack('h', $data_new, 18); //进出
				$card = $this->uint32val($card_arr[1]);
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
			    if(env('UNION_ATT',false)){
			        $this->union_snap($data,$server);
                }
                else{
                    //图片抓拍
                    $this->camear_snap($data, $server);
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
//从redis查找卡号对应的人员 TODO:用卡号查找id这个方式应该测试一下，考虑改成访问三叶草获取
        $allkeys = Redis::keys('PERSON:*');
        $list = Redis::pipeline(function ($pipe) use ($allkeys) {
            foreach ($allkeys as $item) {
                $pipe->get($item);
            }
        });
        $personId = '';
        $stu_name='';
        foreach ($list as $key => $value) {
            # code...
            $varr=json_decode($value,true);
//            info($value);
            if(!$varr['union_cards']){
                continue;
            }
            $union_cards=explode(',',$varr['union_cards']);
            foreach ($union_cards as $k=>$v){
                if($v==$card){
                    $personId = $varr['id'];
                    $stu_name=$varr['stu_name'];
                    info('找到人员' . $personId.'  '.$card.'  '.$stu_name);
                }
            }
        }
        if (!$personId) {
            info('[联动]不存在该卡号对应的联动学生:' . $personId);
            return false;
        }
        //控制板语音播音
        $format=env('UNION_VOICE','');
        $stu_name=sprintf($format,trim($stu_name));
        info('语音字符串'.$stu_name);
        $send_data=$this->pack_voice($stu_name);
        $server->send($fd,$send_data);

        $ip = $this->get_ip($door_num[1], $enter[1]);
        info($ip);
        //http 抓拍
        $client = new Client();
        $rt = $client->request('GET', $ip,
            ['auth' => [env('CAMERA_USER_NAME', 'admin'), env('CAMERA_PWD', 'admin123')]]);

        file_put_contents(env('PATH_ATT') . '/storage/app/public/union_auth/' .$personId.'-family.jpg', $rt->getBody());
        Redis::setex('unionAuthFamily:'.$personId,600,true);

//		$contents = Storage::get('public/att_images/' . $card . $door_num[1] . $time_int . '.jpg');

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

		} else {
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
		if ($getFace['success']) {
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
