<?php

namespace App\Console;

use App\Handler\DCRechargeController;
use App\Handler\FaceController;
use App\Handler\FaceDetectController;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

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
		$str = 'rm -rf ' . env('PATH_ATT') . '/storage/app/public/att_images/*';
		$schedule->call(function () use ($str) {
			system($str);
		})->daily();

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
			info('NEW_SYNC:', [env('NEW_SYNC', false)]);
			if (env('NEW_SYNC', false)) {
				//8设置时段  10删除时段  11删除照片
				$schedule->call(function () {
					foreach ([8, 10] as $k => $v) {
						$face = new FaceController;
						$face->get_task($v);
					}
				})->everyMinute();

				$schedule->call(function () use ($face) {
					//同步人员
					$face->getAllPersons();
					$face->personDeleteByReids(); //注意顺序！！！！！
					$face->personCreateByRedis();
					$face->personUpdateByRedis();
					//上传同步 + test拍照注册
                    $face->excuteFaceDel();
					$face->excuteFaceImgUpload();
				})->everyMinute();

				//从redis读取拍照注册人员上传并保存到树莓派(废弃)
				//                $schedule->call(function()use($face){
				//                    $face->downFaceFromTerminalById();
				//                })->everyMinute();

			} else {
				if (env('SCHOOL_ID') == 769) {
					info('仁德一中特殊');
				} else {

					$schedule->call(function () {
						$face = new FaceController;
						$face->sync_face_serv(); //每次获取10个学去人脸设备上查询有没有照片
						//$face->sync_face();          //本地同步
					})->everyMinute();

					$schedule->call(function () {
//                $client->request('POST',env('CURL_URL').'/heart_sync',[
						$client = new Client;
						try {
							info('---------------------------');
							info('执行face_syncs表-获取并写入task-开始');
							$rt = $client->request('POST', env('CURL_URL') . '/face/not_sync', [
								'form_params' => [
									'school_id' => env('SCHOOL_ID', 0),
								],
							]);
							info('执行face_syncs表-获取并写入task-返回', json_decode($rt->getBody(), true));
						} catch (\Exception $e) {
							info('执行face_syncs表-获取并写入task-异常-' . $e->getMessage());
						}

					})->everyMinute();

					$arr_type = [1, 2, 3, 4, 6, 7, 8, 9, 10];
					$schedule->call(function () use ($arr_type) {
						foreach ($arr_type as $k => $v) {
							$face = new FaceController;
							$face->get_task($v);
						}

					})->everyMinute();
				}
			}

		}
		else if (env('DC_CONSUMER')) {
			$schedule->call(function () {
				info('测试东川一中服务主机');
				//每分钟获取pre_wxpay_order该学校未成功的固定数量条目，数量视测试性能而定
				$DC_hd = new DCRechargeController();
				$DC_hd->get_recharges();
                if(env('YKT_CONSUMER_REC',false)){
                    info('优卡特上报冲销记录');

                }
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
		else if (env('WHDC_CONSUMER')) {
			$schedule->call(function () {
				info('测试五和大成消费服务');

			});
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

	protected function getYKTRecord($timeInterval){
	    $school_id=env('SCHOOL_ID','');
	    if(!$school_id){
	        info('学校id未配置！');
	        return false;
        }
	    $list=\DB::table('lssj')->where('')
    }


}
