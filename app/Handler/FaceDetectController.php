<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/6
 * Time: 10:17
 */

namespace App\Handler;

use App\Services\Helper;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use SebastianBergmann\CodeCoverage\Report\PHP;

class FaceDetectController {
	public function delAllRedisPerson() {
		info('删除redis所有人员记录');
		$all_list = Redis::keys('*');
		$old_val_list = Redis::pipeline(function ($pipe) use ($all_list) {
			foreach ($all_list as $item) {
				$pipe->del($item);
			}
		});
		info('删除redis所有人员记录:', [$old_val_list]);
	}
//人员查询
	public function getAllPersons() {
		info('人员查询同步REDIS-开始');
		$school_id = env('SCHOOL_ID', 0);
		$client = new Client();
		if (!$school_id) {
			info('请设置env文件中的SCHOOL_ID！！');
			return;
		}
		try {

			$res = $client->request('POST', env('CURL_URL') . '/get_all_stus', [
				'form_params' => [
					'school_id' => env('SCHOOL_ID'),
				],
			]);
			$res = json_decode($res->getBody(), true);
			$old_key_list = Redis::keys('PERSON:*');
//            Redis::del('OLD_PERSON:*');
			foreach ($old_key_list as $k) {
				$t = Redis::get($k);
				// OLD_PERSON: 记录同步前的旧数据
				Redis::set('OLD_' . $k, $t);
			}
//
			//            if(count($old_key_list)){
			//                $count=Redis::del(Redis::keys('PERSON:*'));
			//            }
			info('rec_' . count($old_key_list) . '-old list-' . count($old_key_list));
			foreach ($res['data'] as $item) {
				$test_arr_json = [
					"card_id" => $item['card_id'],
					"id" => $item['id'],
					"stu_name" => $item['stu_name'],
                    "union_cards"=>$item['union_cards']?$item['union_cards']['card_id']:''
				];
				Redis::set('PERSON:' . $item['id'], json_encode($test_arr_json, true));
			}
			$new_keylist = Redis::keys('PERSON:*');
			info('new list-' . count($new_keylist));
			$same_list = array_intersect($new_keylist, $old_key_list);

			//更新人员 添加记录 记录卡号 从PERSON获取信息
			$old_val_list = Redis::pipeline(function ($pipe) use ($same_list) {
				foreach ($same_list as $item) {
					$pipe->get('OLD_' . $item);
				}
			});
			$new_val_list = Redis::pipeline(function ($pipe) use ($same_list) {
				foreach ($same_list as $item) {
					$pipe->get($item);
				}
			});
			$diff_upd = array_diff($new_val_list, $old_val_list);
			$diff_del = array_diff($old_key_list, $new_keylist);
			$diff_add = array_diff($new_keylist, $old_key_list);
			if (count($diff_del)) {
//删除
				//人员删除 添加删除记录 从旧PERSONI_INFO 获取信息
				$ids = '';
				foreach ($diff_del as $k) {
					$temp = Redis::get('OLD_' . $k);
					$person_id = json_decode($temp, true)['id'];
					info('del_id', [$person_id]);
					$ids .= $person_id . ',';
				}
				Redis::set('PERSON_DEL:', $ids);
				$del_list = Redis::get('PERSON_DEL:');
				info('PERSON_DEL:', [$del_list]);
			}
			if (count($diff_add)) {
				//人员注册 添加注册记录 记录卡号 从新PERSON获取信息
				$ids = '';
				foreach ($diff_add as $k) {
					$card_id = substr($k, 7);
					$ids .= $card_id . ',';

				}
				Redis::set('PERSON_ADD:', $ids);
				$add_list = Redis::get('PERSON_ADD:');
				info('PERSON_ADD', [$add_list]);
			}
			if (count($diff_upd)) {
			    if(Redis::get("PERSON_UPD:")){
			        info('已有更新内容 暂时跳过');
                }
                else{
                    //对比更新内容 添加修改记录 从新PERSON 获取信息
                    $infos = ''; //# 分隔 已处理格式 满足注册人员接口
                    foreach ($diff_upd as $v) {
                        $v = str_replace('card_id', 'idcardNum', $v);
                        $v = str_replace('stu_name', 'name', $v);
                        $infos .= $v . '#';
                    }
                    Redis::set('PERSON_UPD:', $infos);
                    info('PERSON_UPD:', [$infos]);
                }
			}

		} catch (\Exception $e) {
			info('人员查询同步REDIS-异常-' . $e->getMessage());
		}
	}

	public function TakeFaceCallBack($data) {
//        "imgBase64":"/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBA",
		//        "imgPath":"http://192.168.30.8:8090/apk_imgs/1562312178666.jpg",
		//        "ip":"192.168.30.8",
		//        "deviceKey":"40A709DE53FBFEDE",
		//        "personId":"393552",
		//        "time":"1562312179051",
		//        "faceId":"157eb87c5dea4c89b191531ba3808e2a"
//        return;
		$client = new Client();
		$base64 = $data['imgBase64'];
		$imgPath = $data['imgPath'];
		$ip = $data['ip'];
		$personId = $data['personId'];
		$timestamp = $data['time'];
		$faceId = $data['faceId'];
		$redis = Redis::connection();

		$stu = [
			"student_id" => $personId,
			'ip' => $ip,
			'faceId' => $faceId,
			'timestamp' => $timestamp,
		];
		//保存图片
		//存储图片
		$base64_string = $base64; //识别回调数据不需要截取data:image/png;base64, 这个逗号后的字符
		//        $base64_string= explode(',', $base64_string); //截取data:image/png;base64, 这个逗号后的字符
		$img_data = base64_decode($base64_string); //对截取后的字符使用base64_decode进行解码
        if(Storage::exists('public/face/'.$personId.'/2.jpg')){
            if(Storage::exists('public/face/'.$personId.'/bak.jpg'))
                Storage::delete('public/face/'.$personId.'/bak.jpg');
            Storage::move('public/face/'.$personId.'/2.jpg','public/face/'.$personId.'/bak.jpg');
        }
        if(Storage::exists('public/face/'.$personId.'/1.jpg')){
            Storage::move('public/face/'.$personId.'/1.jpg','public/face/'.$personId.'/2.jpg');
        }
        if(Storage::exists('public/face/'.$personId.'/0.jpg')){
            Storage::move('public/face/'.$personId.'/0.jpg','public/face/'.$personId.'/1.jpg');
        }
		$rt = Storage::put('public/face/' . $personId . '/0.jpg', $img_data);

		//访问三叶草记录到face_imgs
		$response = $client->request('POST', env('CURL_URL') . '/record_takedface_img', [
			'form_params' => $stu,
		]);
		$res = json_decode($response->getBody(), true);
		if ($res['success']) {
			info('拍照注册成功-记录已保存到三叶草face_imgs表');
		} else {
			info('拍照注册成功-记录保存失败-暂存到redis');

			//记录到redis 晚上同步照片之前先写入face_imgs表
			$redis->set('TAKEFACE_RECORD:' . $personId, json_encode($stu, true));
		}
		$this->uploadImg($personId);

	}

	//通过通道数量获取所有人脸识别设备ip
	public function getIPsByDoorNum() {
		$terminal_ips = [];
		$door = env('DOOR_NUM');
		if (env('SCHOOL_ID') == 735) {
			array_push($terminal_ips, '192.168.30.9');
		} else {
			for ($i = 1; $i <= $door; $i++) {
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 3));
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 4));
			}
		}
		return $terminal_ips;
	}

    public function excuteFaceDel(){
        $school_id = env('SCHOOL_ID', '');
        if (!$school_id) {
            info('env中的学校id不存在');
            return false;
        }
        $client = new Client();
        $response = $client->request('POST', env('CURL_URL') . '/getFaceImgDels', [
            'form_params' => [
                'school_id' => $school_id
            ],
        ]);
        $res = json_decode($response->getBody(), true);
        info( '同步删除的照片res', $res);
        if (!$res['data']) {
            info('获取face_imgs-数据为空', [$res['data']]);
            return false;
        }
        $terminal_ips = $this->getIPsByDoorNum();
        foreach($res['data'] as $faceimg){
            $suc = true;
            $fail_ips = [];
            $fail_msg = '';
            $personId = $faceimg['student_id'];
            foreach($terminal_ips as $kip=>$ip){
                if (!$suc) {
                    $fail_ips[] = $ip;
                    continue;}
                $rt1 = $this->delPersonFace($ip, $personId);
                if (!$rt1['success']) {
                    $suc = false;
                    $fail_ips[] = $ip;
                    $fail_msg = '清空照片失败';
                    info($personId . '失败');
                    continue;
                }
            }
            if (!$suc) {
                $response = $client->request('POST', env('CURL_URL') . '/fail_faceimgs_operations', [
                    'form_params' => [
                        'school_id' => $school_id,
                        'student_id' => $personId,
                        'fail_ips' => $fail_ips,
                        'fail_msg' => $fail_msg,
                    ],
                ]);
                $res = json_decode($response->getBody(), true);
                info($personId . 'OPERATE FACE_IMG 结果', $res);
                info('失败ips ', $fail_ips);
            } else {
                $response = $client->request('POST', env('CURL_URL') . '/update_faceimgs_operations', [
                    'form_params' => [
                        'school_id' => $school_id,
                        'student_id' => $personId,
                    ],
                ]);
                $res = json_decode($response->getBody(), true);
                info($personId . 'OPERATE FACE_IMG DEL 结果', $res);
            }
        }
    }


	public function excuteFaceImgUpload() {
		$school_id = env('SCHOOL_ID', '');
		if (!$school_id) {
			info('env中的学校id不存在');
			return false;
		}
		//获取10条记录所有url
		$count = intval(60 / (env('DOOR_NUM') * 2 * 1)); //一秒一台 一分钟一次
		$client = new Client();
		$response = $client->request('POST', env('CURL_URL') . '/getFaceImgUploads', [
			'form_params' => [
				'school_id' => $school_id,
				'count' => $count,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info($count . '同步上传的照片res', $res);
		if (!$res['data']) {
			info('获取face_imgs-数据为空', [$res['data']]);
			return false;
		}
		//url注册人脸
		$terminal_ips = $this->getIPsByDoorNum();
		$success_arr = [];

		foreach ($res['data'] as $faceimg) {
			$suc = true;
			$fail_ips = [];
			$fail_msg = '';
			$student_id = $faceimg['student']['id'];
			$index=$faceimg['photo_index'];
			//先下载
			$url = 'https://app.clovedu.cn' . json_decode($faceimg['student']['photo_url'], true)[$index];
			info('test   ' . $faceimg['origin']);
			$base64 = $this->getBase64ByURL($url);
			if (!$base64) {
				$fail_msg = '获取线上base64失败！';
				info('三叶草照片访问失败，跳过该人员' . $faceimg['student']['id'],['url'=>$url]);
				continue;
			}
			$face_ids=['','1','2'];
			foreach ($terminal_ips as $ip) {
				if (!$suc) {
					$fail_ips[] = $ip;
					continue;}
                $res = $this->searchFaceImg($ip, $faceimg['student']['id']);
				if ($faceimg['origin'] == 'FACE_TAKE') {

                    if(count($res['data'])==3){
                        $this->delFaceByFid($ip, $res['data'][2]['faceId']);
                        info('FACE_TAKE  face_id:'.$res['data'][2]['faceId'] . '删除');
                    }
				} else {
				    $isdel='start';
				    if(count($res['data'])==3){
				        $isdel='';
				        foreach($res['data'] as $k=>$face){
				            if($face['faceId']==$student_id.$face_ids[$index]){
                                $this->delFaceByFid($ip, $face['faceId']);
                                $isdel=$face['faceId'];
                            }
                        }
                        if(!$isdel){
                            $this->delFaceByFid($ip, $res['data'][2]['faceId']);
                            $isdel=$res['data'][2]['faceId'];
                        }
                    }
                    info('UPLOAD face_id:'.$isdel . '删除');

				}
				$rt = $this->createByBase64($ip, $student_id,$student_id.$face_ids[$index], $base64);
				if (!$rt['success']) {
					$suc = false;
					$fail_ips[] = $ip;
					$fail_msg = $rt['msg'];
					info($faceimg['student']['id'] . '失败' . $fail_msg);
					continue;
				}
			}
			if (!$suc) {
				$response = $client->request('POST', env('CURL_URL') . '/fail_faceimgs_operations', [
					'form_params' => [
						'school_id' => $school_id,
						'student_id' => $faceimg['student']['id'],
						'fail_ips' => $fail_ips,
						'fail_msg' => $fail_msg,
					],
				]);
				$res = json_decode($response->getBody(), true);
				info($faceimg['student']['id'] . 'OPERATE FACE_IMG 结果', $res);
				info('失败ips ', $fail_ips);
			} else {
				$response = $client->request('POST', env('CURL_URL') . '/update_faceimgs_operations', [
					'form_params' => [
						'school_id' => $school_id,
						'student_id' => $faceimg['student']['id'],
					],
				]);
				$res = json_decode($response->getBody(), true);
				info($faceimg['student']['id'] . 'OPERATE FACE_IMG 结果', $res);
			}

		}

	}

	//获取并执行face_imgs操作
	public function excuteFaceImgOperation() {
		$school_id = env('SCHOOL_ID', '');
		info('school_id', [$school_id]);
		if (!$school_id) {
			info('env中的学校id不存在');
			return false;
		}
		$client = new Client();
		$response = $client->request('POST', env('CURL_URL') . '/get_faceimgs_operations', [
			'form_params' => [
				'school_id' => $school_id,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info('res', $res);
		if (!$res['data']) {
			info('获取face_imgs-数据为空', [$res['data']]);
			return false;
		}
		foreach ($res['data'] as $item) {
			switch ($item['origin']) {
			case 'FACE_TAKE': //拍照注册记录 上传照片至三叶草并注册照片到所有设备
				//注册到所有设备
				$terminal_ips = $this->getIPsByDoorNum();
				$total_bool = true;
				foreach ($terminal_ips as $ip) {
					$rt = $this->delPersonFace($ip, $item['student_id']);
					if (!$rt['success']) {
						$total_bool = false;
						break;}
					info($item['student_id']);
					$rt = $this->createBase64($ip, $item['student_id']);
					if (!$rt['success']) {
						$total_bool = false;
						break;}
				}
				if ($total_bool) {
					//上传到三叶草
					$this->uploadImg($item['student_id']);
					//更新face_imgs表
					$response = $client->request('POST', env('CURL_URL') . '/update_faceimgs_operations', [
						'form_params' => [
							'school_id' => $school_id,
							'student_id' => $item['student_id'],
						],
					]);
					$res = json_decode($response->getBody(), true);
					info('OPERATE FACE_TAKE 结果', $res);
				} else {
					info('OPERATE FACE_TAKE 失败 ' . $item['id']);
				}
				break;
			case 'FACE_DEL': //照片删除记录 删除所有设备上的照片
				//                    $terminal_ips=$this->getIPsByDoorNum();
				//                    $total_bool=true;
				//                    foreach($terminal_ips as $ip){
				//                        $rt=$this->delPersonFace($ip,$item['student_id']);
				//                        if(!$rt['success']){$total_bool=false;break;}
				//                    }
				//                    if($total_bool){
				//                        //更新face_imgs表
				//                        $response=$client->request('POST',env('CURL_URL').'/update_faceimgs_operations',[
				//                            'form_params'=>[
				//                                'school_id'=>$school_id,
				//                                'student_id'=>$item['student_id']
				//                            ]
				//                        ]);
				//                        $res=json_decode($response->getBody(),true);
				//                        info('OPERATE FACE_DEL 结果',$res);
				//                    }else{
				//                        info('OPERATE FACE_DEL 失败 '.$item['id']);
				//                    }
				break;
			case 'FACE_UPLOAD': //照片上传记录 从三叶草下载照片并注册到所有设备
				break;
			}
		}
	}

	/*
		     * 序列号
	*/
	public function getDeviceKey($ip) {
		$url = $ip . ':8090/getDeviceKey';
		$client = new Client();
		$response = $client->request('POST', $url, ['form_params' => []]);

		$rt = json_decode($response->getBody(), true);
		echo $response->getBody() . PHP_EOL;
		info($url . '获取序列号-', $rt);
		if(!$rt['success']){
		    return false;
        }
		return ['ip'=>$ip,'terminal_num'=>$rt];

	}
	/*
		     * 重启设备
		     * @param IP pwd
		     * @return bool
	*/
	public function restart($ip, $pwd = "spdc") {
		if (!$ip) {
			return [
				'success' => false,
				'msg' => 'ip不能为空!',
			];
		}
		//设备IP:8090/restartDevice
		$url = $ip . ':8090/restartDevice';
		$client = new Client();
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => $pwd,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info('重启设备', $res);
		echo ($response->getBody() . PHP_EOL);

	}

	public function personDeleteByReids() {
		$redis = Redis::connection();
		$del_str = $redis->get('PERSON_DEL:');
		if (!$del_str) {
			info('redis人员删除-没有可删除的人员');
			return;
		}
		$terminal_ips = [];
		$door = env('DOOR_NUM');
		if (env('SCHOOL_ID') == 735) {
			array_push($terminal_ips, '192.168.30.9');
		} else {
			for ($i = 1; $i <= $door; $i++) {
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 3));
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 4));
			}
		}
		$fail_arr = [];
		foreach ($terminal_ips as $ip) {
			$re = $this->delPerson($ip, $del_str);
			$fail = $re['data']['invalid'];
			$fail = explode(',', $fail);
			$fail_arr = array_keys(array_flip($fail_arr) + array_flip($fail));
		}
		$fail_str = implode(',', $fail_arr);
		$redis->set('PERSON_DEL:', $fail_str);
		info('redis人员删除-完成-剩余删除失败-' . $fail_str);
	}

	public function personUpdateByRedis() {
		$redis = Redis::connection();
		$redis_infos = $redis->get('PERSON_UPD:');
		if (!$redis_infos) {
			info('redis更新人员-没有更新的人员');
			return;
		}
		$terminal_ips = [];
		$door = env('DOOR_NUM');
		if (env('SCHOOL_ID') == 735) {
			array_push($terminal_ips, '192.168.30.9');
		} else {
			for ($i = 1; $i <= $door; $i++) {
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 3));
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 4));
			}
		}
		$info_arr = explode('#', $redis_infos);
		$fail_str = '';
		foreach ($info_arr as $item) {
			if (!$item) {
				info('redis更新人员-json内容为空-continue');
				continue;
			}
			$tbool = false;
			foreach ($terminal_ips as $ip) {
//                $bool=$this->personCreate($ip ,$item);
				$bool = $this->personUpdate($ip, $item);
				if ($bool) {
					$tbool = true;
				} else {
					$tbool = false;
					info($ip . '-' . $item . '-redis更新人员-失败');
					$fail_str .= $item . '#';
					break;
				}
			}
			if ($tbool) {
				info($ip . '-' . $item . '-redis更新人员-成功');
			}
		}
		$redis->set('PERSON_UPD:', $fail_str);
	}

	public function personCreateByRedis() {
		$redis = Redis::connection();
		$add_list = $redis->get('PERSON_ADD:');
		if (!$add_list) {
			info('redis添加人员-没有可添加的人员');
			return;
		}
		$add_list = explode(',', $add_list);
		$terminal_ips = [];
		$door = env('DOOR_NUM');
		if (env('SCHOOL_ID') == 735) {
			array_push($terminal_ips, '192.168.30.9');
		} else {

			for ($i = 1; $i <= $door; $i++) {
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 3));
				array_push($terminal_ips, '192.168.30.' . ($i * 5 + 4));
			}
		}
		$info_arr = $redis->pipeline(function ($pipe) use ($add_list) {
			foreach ($add_list as $stuid) {
				$pipe->get('PERSON:' . $stuid);
			}
		});
		$fail_str = '';
		foreach ($info_arr as $info) {
			if (!$info) {
				continue;
			}

			$arr = json_decode($info, true);
			$v = str_replace('card_id', 'idcardNum', $info);
			$v = str_replace('stu_name', 'name', $v);
			$tbool = false;
			foreach ($terminal_ips as $ip) {
				$hasPerson = $this->personFind($ip, $arr['id']);
				if ($hasPerson['success']) {
					$bool = $this->personUpdate($ip, $v);
				} else {
					$bool = $this->personCreate($ip, $v);
				}
				if ($bool) {

					$tbool = true;
				} else {
					info($ip . '-' . $arr['id'] . '-redis添加人员-失败');
					$tbool = false;
					$fail_str .= $arr['id'] . ',';
					break;
				}
			}
			if ($tbool) {
				info($arr['id'] . '-redis所有设备添加人员-成功');
			}
		}
		usleep(500000);
		$redis->set('PERSON_ADD:', $fail_str);

	}

	public function personCreate769_2019($ip) {
		$handle = new ClientHandler();
		$client = new Client();
		try {
			$res = $client->request('POST', env('CURL_URL') . '/get_769_2019_stus', [
				'form_params' => [
					'school_id' => env('SCHOOL_ID'),
				],
			]);
			$res = json_decode($res->getBody(), true);
			$success_count = 0;
			$failed_count = 0;
			$starttime = microtime(true);
			foreach ($res['data'] as $item) {
				$data = [
					"idcardNum" => $item['card_id'],
					"id" => $item['id'],
					"name" => $item['stu_name'],
				];
				$data = json_encode($data);
				$r = $handle->person_create($ip, $data, 'spdc');
				if ($r['success']) {
					$success_count++;
				} else {
					$failed_count++;
				}
//                echo  ($success_count+$failed_count).'/'.count($res['data']).PHP_EOL;
				//str_repeat 函数的作用是重复这个符号多少次
				$hp = intval((($success_count + $failed_count) / count($res['data'])) * 100);
				$equalStr = str_repeat("=", $hp);
				$space = str_repeat(" ", 100 - $hp);
				print_r("\r [$equalStr>$space]($hp/100%)");
//                str_repeat()
				//                printf("\r [%-".count($res['data'])."s] (%2d%%/%2d%%)", str_repeat("=", ($success_count+$failed_count)) . ">", ($success_count+$failed_count) , count($res['data']));
			}
			info('仁德一中特殊-注册人员-人数', ['total' => count($res['data']), 'success' => $success_count, 'fail' => $failed_count]);
			echo json_encode(['total' => count($res['data']), 'success' => $success_count, 'fail' => $failed_count]) . PHP_EOL;
			echo '耗时' . (microtime(true) - $starttime) . PHP_EOL;
		} catch (\Exception $e) {
			info('仁德一中特殊-异常' . $e->getMessage());
			echo $e->getMessage() . PHP_EOL;
		}
	}

	public function personCreateAll($ip) {
		$handle = new ClientHandler();
		$client = new Client();
		try {
			$res = $client->request('POST', env('CURL_URL') . '/get_all_stus', [
				'form_params' => [
					'school_id' => env('SCHOOL_ID'),
				],
			]);
			$res = json_decode($res->getBody(), true);
			$success_count = 0;
			$failed_count = 0;
			$starttime = microtime(true);
			foreach ($res['data'] as $item) {
				$data = [
					"idcardNum" => $item['card_id'],
					"id" => $item['id'],
					"name" => $item['stu_name'],
				];
				$data = json_encode($data);
				$r = $handle->person_create($ip, $data, 'spdc');
				if ($r['success']) {
					$success_count++;
				} else {
					$failed_count++;
				}
//                echo  ($success_count+$failed_count).'/'.count($res['data']).PHP_EOL;
				//str_repeat 函数的作用是重复这个符号多少次
				$hp = intval((($success_count + $failed_count) / count($res['data'])) * 100);
				$equalStr = str_repeat("=", $hp);
				$space = str_repeat(" ", 100 - $hp);
				print_r("\r [$equalStr>$space]($hp/100%)");
//                str_repeat()
				//                printf("\r [%-".count($res['data'])."s] (%2d%%/%2d%%)", str_repeat("=", ($success_count+$failed_count)) . ">", ($success_count+$failed_count) , count($res['data']));
			}
			info('仁德一中特殊-注册人员-人数', ['total' => count($res['data']), 'success' => $success_count, 'fail' => $failed_count]);
			echo json_encode(['total' => count($res['data']), 'success' => $success_count, 'fail' => $failed_count]) . PHP_EOL;
			echo '耗时' . (microtime(true) - $starttime) . PHP_EOL;
		} catch (\Exception $e) {
			info('仁德一中特殊-异常' . $e->getMessage());
			echo $e->getMessage() . PHP_EOL;
		}

	}

	public function personFind($ip, $personId) {
		$client = new Client();
		$url = $ip . ':8090/person/find';
		try {
			$response = $client->request('POST', $url, [
				'form_params' => [
					'pass' => 'spdc',
					'id' => $personId,
				],
			]);
			$res = json_decode($response->getBody(), true);
			return $res;
		} catch (\Exception $e) {
			info('人员查询-异常' . $e->getMessage());
			return false;
		}
	}

	public function personUpdate($ip, $personJson) {
		$client = new Client();
		$url = $ip . ':8090/person/update';
		try {
			$response = $client->request('POST', $url, [
				'form_params' => [
					'person' => $personJson,
					'pass' => 'spdc',
				],
			]);
			$res = json_decode($response->getBody(), true);
			if ($res['success']) {
				return true;
			} else {
				info('更新人员-' . $personJson . '-失败-', $res);
				return false;
			}
		} catch (\Exception $e) {
			info('更新人员-' . $personJson . '-异常-' . $e->getMessage());
			return false;
		}
	}

	public function personCreate($ip, $personJson) {
		$client = new Client();
		$url = $ip . ':8090/person/create';

		try {
			$response = $client->request('POST', $url, [
				'form_params' => [
					'person' => $personJson,
					'pass' => 'spdc',
				],
			]);
			$res = json_decode($response->getBody(), true);
			if ($res['success']) {
				return true;
			} else {
				info('注册人员-' . $personJson . '-失败-', $res);
				return false;
			}
		} catch (\Exception $e) {
			info('注册人员-' . $personJson . '-异常-' . $e->getMessage());
			return false;
		}
	}

	public function faceCreateUrl($ip) {
		$handle = new ClientHandler();
		$client = new Client();
		try {
			$res = $client->request('POST', env('CURL_URL') . '/get_all_photo', [
				'form_params' => [
					'school_id' => env('SCHOOL_ID'),
				],
			]);
			$res = json_decode($res->getBody(), true);

			foreach ($res['data'] as $item) {
//                $arr=json_decode($item['photo_url'],true);
				//                $url='https://app.clovedu.cn'.$arr[0];
				//                file_put_contents(env('PATH_ATT').'/storage/app/public/face/'.$item['id'].'.jpg',file_get_contents($url));
				//                $file = fopen(env('PATH_ATT').'/storage/app/public/face/'.$item['id'].'.jpg', 'r');
				//                info($item['id']);

				$handle->delPersonFace($ip, $item['id']);
				$handle->face_create($ip, $item['id'], 'spdc', '');
			}
		} catch (\Exception $e) {
			info('仁德一中特殊-异常' . $e->getMessage());
		}
	}

	public function downloadPhoto($idarr) {
		$handle = new ClientHandler();
		$client = new Client();
		try {
			$res = $client->request('POST', env('CURL_URL') . '/get_all_photo', [
				'form_params' => [
					'school_id' => env('SCHOOL_ID'),
				],
			]);
			$res = json_decode($res->getBody(), true);
			$fail_arr = [];
			$suc_arr = [];
			foreach ($res['data'] as $item) {
				if (!in_array($item['id'], $idarr)) {
					continue;
				}

				$url = 'https://app.clovedu.cn' . (json_decode($item['photo_url'], true)[0]);
				$path = env('PATH_ATT') . '/storage/app/public/face/';
				$bool = $this->down_images($url, $path, $item['id'] . '.jpg');
				if (!$bool) {
					$fail_arr[] = $item['id'];
				}
			}
			info('下载照片到树莓派-', ['total' => count($res['data']), 'fail_arr' => $fail_arr]);
			echo json_encode(['total' => count($res['data']), 'fail_arr' => $fail_arr]) . PHP_EOL;
		} catch (\Exception $e) {
			info('仁德一中特殊-异常' . $e->getMessage());
			echo $e->getMessage() . PHP_EOL;
		}
	}

	public function faceCreateBase64All($ip) {
		$clientH = new ClientHandler();
		try {
			$client = new Client();
//            $res = $client->request('POST', env('CURL_URL') . '/get_all_photo', [
			//                'form_params' => [
			//                    'school_id' => env('SCHOOL_ID'),
			//                ]
			//            ]);
			//            $res = json_decode($res->getBody(), true);
			$success = 0;
			$fail = [];
			$starttime = microtime(true);
			$fs = new \Illuminate\Filesystem\Filesystem();

			$arr = $fs->allFiles(env('PATH_ATT') . '/storage/app/public/face');
			info('fails ', [$arr[0]->getPathname()]);
			foreach ($arr as $item) {
				$path = $item->getPathname();
				$tarr = explode('/', $path);
				$stuid = $tarr[count($tarr) - 1];
				$tarr = explode('.', $stuid);
				$id = $tarr[0];
				$bool1 = $this->delPersonFace($ip, $id);
				if (!$bool1['success']) {
					$fail[] = $id;
					break;
				}
				$bool = $this->createBase64($ip, $id);
				if (!$bool['success']) {
					$fail[] = $id;
				} else {
					$success++;
				}
//                echo  ($success+count($fail)).'/'.count($res['data']).PHP_EOL;
				//str_repeat 函数的作用是重复这个符号多少次
				$hp = intval(((count($fail) + $success) / count($arr)) * 100);
				$equalStr = str_repeat("=", $hp);
				$space = str_repeat(" ", 100 - $hp);
				print_r("\r [$equalStr>$space](" . ((count($fail) + $success)) . "/" . count($arr) . "%)");
			}
			info('base64全部注册结果-', ['success' => $success, 'total' => count($arr), 'fail_ids' => $fail]);
			echo json_encode(['success' => $success, 'total' => count($arr), 'fail_ids' => $fail]) . PHP_EOL;
			echo '耗时' . (microtime(true) - $starttime) . PHP_EOL;
		} catch (\Exception $e) {
			info('仁德一中特殊-异常' . $e->getMessage());
			echo $e->getMessage() . PHP_EOL;
		}
	}

	public function getSchoolId() {
		return env('SCHOOL_ID');
	}

	public function delPerson($ip, $ids) {
		$client = new Client();
		$url = $ip . ':8090/person/delete';
		$res1 = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'id' => $ids,
			],
		]);
		$res = json_decode($res1->getBody(), true);
		echo $res1->getBody() . PHP_EOL;
		return $res;
	}

	public function setPassword($ip, $oldPass, $newPass) {
		$client = new Client();
		$url = $ip . ':8090/setPassword';
		$response = $client->request('POST', $url, [
			'form_params' => [
				'newPass' => $newPass,
				'oldPass' => $oldPass,
			],
		]);
		$res = json_decode($response->getBody(), true);
		echo $response->getBody() . PHP_EOL;
		info('设置密码-', $res);
	}

	public function openDoor($ip) {
		$client = new Client();
		$url = $ip . ':8090/device/openDoorControl';
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
			],
		]);
		$res = json_decode($response->getBody(), true);
		echo $response->getBody() . PHP_EOL;
		info('人脸识别远程开门-', $res);
	}

	public function setIdCallback($ip, $url) {
		$client = new Client();
		$url = $ip . ':8090/setIdentifyCallBack';
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'callbackUrl' => $url,
			],
		]);
		$res = json_decode($response->getBody(), true);
		echo $response->getBody() . PHP_EOL;
		info('设置识别回调-', $res);
	}

	public function delFaceByFid($ip,$faceId){
        $url=$ip.':8090/face/delete';
        info($url.'-删除照片-开始');
        if(!$ip|| !$faceId){
            return [
                'success'=>false,
                'msg'=>'参数错误',
                'data'=>''
            ];
        }
        $client=new Client();
        $response=$client->request('POST',$url,[
            'form_params' => [
                'pass' => 'spdc',
                'faceId' => $faceId,
            ]
        ]);
        $rt = json_decode($response->getBody(),true);

        info($url.'-删除照片-返回',$rt);
        if($rt['success']){
            return [
                'success'=>true,
                'msg'=>'操作成功',
                'data'=>''
            ];
        }else{
            return [
                'success'=>false,
                'msg'=>'操作失败',
                'data'=>$rt
            ];
        }
    }

	/*
		     * 清空照片
		     * @param IP personId pwd
	*/
	public function delPersonFace($ip, $personId) {
		$url = $ip . ':8090/face/deletePerson';
		$client = new Client();
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $personId,
			],
		]);
		$rt = json_decode($response->getBody(), true);

		info($url . '-清空照片-返回', $rt);
//        echo $response->getBody().PHP_EOL;
		if ($rt['success']) {
			return [
				'success' => true,
				'msg' => '操作成功',
				'data' => '',
			];
		} else {
			return [
				'success' => false,
				'msg' => '操作失败',
				'data' => $rt,
			];
		}
	}
	public function createByBase64($ip, $personId,$faceId, $base64) {
		$client = new Client();
		$url = $ip . ':8090/face/create';
		$response = $client->request("post", $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $personId,
				'faceId' => $faceId,
				'imgBase64' => $base64,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info($url . '-注册照片base64-', $res);
//        echo $response->getBody().PHP_EOL;
		if ($res['success']) {
			if ($res['msg'] != 'success') {
				return [
					'success' => false,
					'msg' => $res['msg'],
					'data' => '',
				];
			}
			return [
				'success' => true,
				'msg' => '操作成功',
				'data' => $res['data'],
			];
		} else {
			return [
				'success' => false,
				'msg' => '操作失败',
				'data' => $res,
			];

		}
	}

	public function createBase64($ip, $person_id) {
		$client = new Client();
		$url = $ip . ':8090/face/create';
		$file_path = env('PATH_ATT') . '/storage/app/public/face/' . $person_id . '.jpg';
		if (!file_exists($file_path)) {
			return [
				'success' => false,
				'msg' => '三叶草有照片但是设备上没有照片',
				'data' => '',
			];
		}
		$base64 = Helper::imgToBase64($file_path, '');
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $person_id,
				'faceId' => $person_id,
				'imgBase64' => $base64,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info($url . '-注册照片base64-', $res);
//        echo $response->getBody().PHP_EOL;
		if ($res['success']) {
			if ($res['msg'] != 'success') {
				return [
					'success' => false,
					'msg' => $res['msg'],
					'data' => '',
				];
			}
			return [
				'success' => true,
				'msg' => '操作成功',
				'data' => $res['data'],
			];
		} else {
			return [
				'success' => false,
				'msg' => '操作失败',
				'data' => $res,
			];

		}
	}
	//照片注册url
	public function createByurl($ip, $personId, $faceId, $imgUrl) {
		$url = $ip . ':8090/face/createByUrl';
		$client = new Client();
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $personId,
				'faceId' => $faceId,
				'imgUrl' => $imgUrl,
			],
		]);

		$rt = json_decode($response->getBody(), true);
		info($url . '-照片注册url-', $rt);
		echo $response->getBody() . PHP_EOL;
		if ($rt['success']) {
			return [
				'success' => true,
				'msg' => '照片操作成功',
				'data' => $rt['data'],
			];
		} else {
			return [
				'success' => false,
				'msg' => $rt['msg'],
				'data' => '',
			];
		}
	}

	//拍照注册
	public function faceTakeImg($ip, $personId) {
		$client = new Client();
		$url = $ip . ':8090/face/takeImg';
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $personId,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info('拍照注册-', $res);
		return $res;
	}

	//照片查询
	public function searchFaceImg($ip, $personId) {
		$client = new Client();
		$url = $ip . ':8090/face/find';
		$response = $client->request('POST', $url, [
			'form_params' => [
				'pass' => 'spdc',
				'personId' => $personId,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info('test_face_find',$res);
		$data=[];
		foreach ($res['data'] as $item){
		    $data[]=[
		        'faceId'=>$item['faceId'],
                'personId'=>$item['personId'],
                'path'=>$item['path']
            ];
        }
		info('照片查询-', $data);
		return $res;
	}

	//从树莓派上传拍照注册的照片到三叶草
	public function uploadImg($id) {
		$client = new Client();
		$file = fopen(env('PATH_ATT') . '/storage/app/public/face/' . $id . '/0.jpg', 'r');
		try {
			$response = $client->request('POST', env('CURL_URL') . '/upload_takedface_img', [
				'verify' => false,
				'multipart' => [
					[
						'name' => 'image',
						'contents' => $file,
					],
					[
						'name' => 'student_id',
						'contents' => $id,
					],
				],
			]);
			$res = json_decode($response->getBody(), true);
			info('拍照注册照片上传成功' . $id, $res);
		} catch (\Exception $e) {
			info($e->getMessage());
		}
	}

	//读取redis记录中的照片注册信息 保存照片到树莓派  关键字 ip personid
	public function downFaceFromTerminalById() {
		info('读取redis记录中的照片注册信息-开始');
		$keys = Redis::keys('TAKEFACE_RECORD:*');
		$v_arr = [];
		$url_arr = [];
		$id_arr = [];
		//获取redis中的信息
		foreach ($keys as $redisk) {
			$v = Redis::get($redisk);
			$v_arr[] = $v;
		}
		//查询照片 获取url数组
		foreach ($v_arr as $item) {
			$item = json_decode($item, true);
			if (!$item) {
				continue;
			}

			$res = $this->searchFaceImg($item['ip'], $item['id']);
			if (!$res['data']) {
				continue;
			}

			$url_arr[$item['id']] = $res['data'][0]['path'];
		}
		//下载到树莓派
		foreach ($url_arr as $id => $url) {
			$path = env('PATH_ATT') . '/storage/app/public/face';
			if (!is_dir($path)) {
				mkdir($path, 0777, true);
			}
			$bool = $this->down_images($url, $path . '/' . $id . '/', '0.jpg');
			if (!$bool) {
				$fail_arr[] = $id;
				continue;
			}
			$id_arr[] = $id;
			info('读取redis记录中的照片注册信息-保存图片' . $v);
		}
		//上传到三叶草
		foreach ($id_arr as $id) {
			$this->uploadImg($id);
		}
		foreach ($keys as $k) {
			Redis::del($k);
		}
		info('处理redis记录中的照片注册信息-结束');
	}

	//把指定设备上所有照片下载到树莓派
	public function downFaceFromTerminal($ip) {
		$client = new Client();
		$url = $ip . ':8090/face/find';
		// $res = $client->request('POST', env('CURL_URL') . '/get_all_stus', [
		// 	'form_params' => [
		// 		'school_id' => env('SCHOOL_ID'),
		// 	],
		// ]);
		// $res = json_decode($res->getBody(), true);
		$res=[
     [
       "card_id" => "3968839740",
       "id" => "522121",
       "stu_name" => "王如贤",
     ],
     [
       "card_id" => "3968726348",
       "id" => "522125",
       "stu_name" => "袁杨铭鑫",
     ],
     [
       "card_id" => "3967844204",
       "id" => "522127",
       "stu_name" => "李涵",
     ],
     [
       "card_id" => "3968163932",
       "id" => "522153",
       "stu_name" => "赵茹",
     ],
     [
       "card_id" => "3968006284",
       "id" => "522155",
       "stu_name" => "杨欣璇",
     ],
     [
       "card_id" => "3967706572",
       "id" => "522178",
       "stu_name" => "朱梓媛",
     ],
     [
       "card_id" => "3968938012",
       "id" => "522197",
       "stu_name" => "汤舒媛",
     ],
     [
       "card_id" => "3967712364",
       "id" => "522203",
       "stu_name" => "李萌",
     ],
     [
       "card_id" => "3968426588",
       "id" => "522307",
       "stu_name" => "李瑶钰",
     ],
     [
       "card_id" => "894792812",
       "id" => "523158",
       "stu_name" => "招娜",
     ],
     [
       "card_id" => "3967926604",
       "id" => "523392",
       "stu_name" => "孙铨",
     ],
     [
       "card_id" => "3980382124",
       "id" => "523569",
       "stu_name" => "李星奇",
     ],
     [
       "card_id" => "3967586140",
       "id" => "523926",
       "stu_name" => "陈宇辉",
     ],
     [
       "card_id" => "3967971516",
       "id" => "526388",
       "stu_name" => "董浩",
     ],
     [
       "card_id" => "698808887",
       "id" => "526910",
       "stu_name" => "魏鲡霆",
     ],
     [
       "card_id" => "3968623228",
       "id" => "528213",
       "stu_name" => "土培焱",
     ],
     [
       "card_id" => "3968859788",
       "id" => "528215",
       "stu_name" => "郑皓阳",
     ],
     [
       "card_id" => "894502844",
       "id" => "528243",
       "stu_name" => "王光明",
     ],
     [
       "card_id" => "3968500620",
       "id" => "528259",
       "stu_name" => "汤伊",
     ],
     [
       "card_id" => "3968385452",
       "id" => "528265",
       "stu_name" => "张坤",
     ],
     [
       "card_id" => "3968783980",
       "id" => "528278",
       "stu_name" => "李慧",
     ],
     [
       "card_id" => "3967866860",
       "id" => "528280",
       "stu_name" => "董怡菁",
     ],
     [
       "card_id" => "3968926924",
       "id" => "528285",
       "stu_name" => "杨玉蓉",
     ],
     [
       "card_id" => "3968829564",
       "id" => "530912",
       "stu_name" => "丁康",
     ],
     [
       "card_id" => "3967911708",
       "id" => "530913",
       "stu_name" => "计然",
     ],
     [
       "card_id" => "1698795511",
       "id" => "530914",
       "stu_name" => "张源",
     ],
     [
       "card_id" => "3968222700",
       "id" => "530933",
       "stu_name" => "宋楷",
     ],
     [
       "card_id" => "3967544188",
       "id" => "530946",
       "stu_name" => "黄川洪",
     ],
     [
       "card_id" => "3968948716",
       "id" => "530947",
       "stu_name" => "张曼",
     ],
     [
       "card_id" => "1696042919",
       "id" => "530955",
       "stu_name" => "李洪艳",
     ],
     [
       "card_id" => "3968873772",
       "id" => "530965",
       "stu_name" => "杨德月",
     ],
     [
       "card_id" => "3968913692",
       "id" => "530994",
       "stu_name" => "方晓兰",
     ],
     [
       "card_id" => "3968471596",
       "id" => "531002",
       "stu_name" => "施永玲",
     ],
     [
       "card_id" => "3968624380",
       "id" => "531014",
       "stu_name" => "赵杨泽",
     ],
     [
       "card_id" => "1695767175",
       "id" => "531021",
       "stu_name" => "付朝高",
     ],
     [
       "card_id" => "1695032903",
       "id" => "531051",
       "stu_name" => "李晨希",
     ],
     [
       "card_id" => "3968959868",
       "id" => "531056",
       "stu_name" => "李松",
     ],
     [
       "card_id" => "3968034348",
       "id" => "531063",
       "stu_name" => "洪泽珊",
     ],
     [
       "card_id" => "3978077164",
       "id" => "531073",
       "stu_name" => "赵勇馨",
     ],
     [
       "card_id" => "1816493767",
       "id" => "531080",
       "stu_name" => "鲁昕",
     ],
     [
       "card_id" => "3967648268",
       "id" => "531081",
       "stu_name" => "李书璇",
     ],
     [
       "card_id" => "3968937036",
       "id" => "531097",
       "stu_name" => "李婷",
     ],
     [
       "card_id" => "3968247788",
       "id" => "531114",
       "stu_name" => "赵晶颖",
     ],
     [
       "card_id" => "3967613868",
       "id" => "531135",
       "stu_name" => "郭易函",
     ],
     [
       "card_id" => "3968806028",
       "id" => "531140",
       "stu_name" => "普宁星",
     ],
     [
       "card_id" => "3968767532",
       "id" => "531147",
       "stu_name" => "李悦",
     ],
     [
       "card_id" => "3967592284",
       "id" => "531148",
       "stu_name" => "李源莹",
     ],
     [
       "card_id" => "3967879068",
       "id" => "531159",
       "stu_name" => "谢景妃",
     ],
     [
       "card_id" => "3968777708",
       "id" => "531210",
       "stu_name" => "陈贵源",
     ],
     [
       "card_id" => "3968594364",
       "id" => "531233",
       "stu_name" => "余海欣",
     ],
     [
       "card_id" => "3968007548",
       "id" => "531248",
       "stu_name" => "李钰琳",
     ],
     [
       "card_id" => "1680336119",
       "id" => "531263",
       "stu_name" => "肖兴瑞",
     ],
     [
       "card_id" => "3967578348",
       "id" => "531277",
       "stu_name" => "王喆",
     ],
     [
       "card_id" => "3967827836",
       "id" => "531283",
       "stu_name" => "王莹艳",
     ],
     [
       "card_id" => "3968219132",
       "id" => "531315",
       "stu_name" => "李俊宇",
     ],
     [
       "card_id" => "3967706300",
       "id" => "531320",
       "stu_name" => "赵洁",
     ],
     [
       "card_id" => "3968260508",
       "id" => "531325",
       "stu_name" => "杨敏艳",
     ],
     [
       "card_id" => "3968366572",
       "id" => "531379",
       "stu_name" => "刘芳",
     ],
     [
       "card_id" => "3968801116",
       "id" => "531388",
       "stu_name" => "田文",
     ],
     [
       "card_id" => "3967863676",
       "id" => "531435",
       "stu_name" => "何燕芳",
     ],
     [
       "card_id" => "3967640204",
       "id" => "531441",
       "stu_name" => "段文星",
     ],
   ];

		$fail_arr = [];
		$url_arr = [];
		foreach ($res as $item) {
			$response = $client->request('POST', $url, [
				'form_params' => [
					'pass' => 'spdc',
					'personId' => $item['id'],
				],
			]);
			$resp = json_decode($response->getBody(), true);
			if (!$resp['data']) {continue;}
//            info($item['id'].'照片查询结果'.$response->getBody());
			//            $url=$resp['data'][0]['path'];
			//                info($url) ;
			$url_arr[$item['id']] = $resp['data'][0]['path'];
//                usleep(60000);
		}
		info('res', $url_arr);
		$success_count=0;
		$failed_count=0;
		foreach ($url_arr as $k => $v) {
			$path = env('PATH_ATT') . '/storage/app/public/face/' . $k;
			$bool = $this->down_images($v, $path . '/', '0.jpg');
			if (!$bool) {
				$fail_arr[] = $v;
				$failed_count++;
				continue;
			}else{
				info('保存图片' . $v);
				$this->uploadImg($k);
				$success_count++;
			}

		$hp = intval((($success_count + $failed_count) / count($url_arr)) * 100);
				$equalStr = str_repeat("=", $hp);
				$space = str_repeat(" ", 100 - $hp);
				print_r("\r [$equalStr>$space]($hp/100%)");
		}

		info('下载照片到树莓派-', ['total' => count($res['data']), 'fail_arr' => $fail_arr]);
		echo $item['id'] . '.img,';
	}

	/*
		     * 通用测试接口
		     * @param ip params api_name
	*/
	public function TestCommonAPI($ip, $params, $api_name) {
		$url = $ip . ':8090' . $api_name;
		$client = new Client();
		info($params);
		$response = $client->request('POST', $url, [
			'form_params' => $params,
		]);
		$rt = json_decode($response->getBody(), true);
		info($rt);
		echo $response->getBody() . PHP_EOL;
	}

	//url to base64
	function getBase64ByURL($url) {
		$header = [
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:45.0) Gecko/20100101 Firefox/45.0',
			'Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate',
		];
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		$data = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($code == 200) { //把URL格式的图片转成base64_encode格式的！
			$imgBase64Code = base64_encode($data); //没有"data:image/jpeg;base64,"头部
			return $imgBase64Code; //图片内容
		} else {
			return false;
		}
	}



	/*
		     *下载图片 CURL
		     * @param url save_path
	*/

	function down_images($url, $folder, $filename) {

		if (!$folder || !$filename) {
			info('save_path不能为空');
			return false;
		}
		$header = array("Connection: Keep-Alive", "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", "Pragma: no-cache", "Accept-Language: zh-Hans-CN,zh-Hans;q=0.8,en-US;q=0.5,en;q=0.3", "User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:29.0) Gecko/20100101 Firefox/29.0");

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);

		//curl_setopt($ch, CURLOPT_HEADER, $v);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

		$content = curl_exec($ch);

		$curlinfo = curl_getinfo($ch);

		//print_r($curlinfo);

		//关闭连接

		curl_close($ch);

		if ($curlinfo['http_code'] == 200) {

			if ($curlinfo['content_type'] == 'image/jpeg') {

				$exf = '.jpg';

			} else if ($curlinfo['content_type'] == 'image/png') {

				$exf = '.png';

			} else if ($curlinfo['content_type'] == 'image/gif') {

				$exf = '.gif';

			}

			//存放图片的路径及图片名称  *****这里注意 你的文件夹是否有创建文件的权限 chomd -R 777 mywenjian

			if (!is_dir($folder)) {
				mkdir($folder, 0777, true);
			}
			$res = file_put_contents($folder . $filename, $content);
			info('保存图片', ['result' => $res, 'save_path' => $folder . $filename]);
			//$res = file_put_contents($filename, $content);//同样这里就可以改为$res = file_put_contents($filepath, $content);
			//echo $filepath;
			//            echo $res;
			return true;
		}

	}

}