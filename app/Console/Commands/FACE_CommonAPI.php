<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/18
 * Time: 17:23
 */

namespace App\Console\Commands;

use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_CommonAPI extends Command {
	protected $signature = 'FACE:CommonAPI {ip} {api_name} {params}';
	//示例 php artisan FACE:CommonAPI 192.168.30.8 /person/findByPage personId^-1*length^1*index^0*pass^spdc

	//php artisan FACE:CommonAPI 192.168.30.9 /setImgRegCallBack pass^spdc*url^http://192.168.30.2:8088
	//params 参数之间#分割  参数名和参数值^分割

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '接口测试-通用api';

	protected $drip;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(FaceDetectController $drip) {
		parent::__construct();
		$this->drip = $drip;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		//
		// $this->drip->sync_face();
		$ip = $this->argument('ip');
		$params = $this->argument('params');
		$api_name = $this->argument('api_name');
		info('params', [$params]);
		$arr = [];
		$params = explode('*', $params);
		foreach ($params as $v) {
			$v = explode('^', $v);
			$arr[$v[0]] = $v[1];
		}
		info('params_arr', array_values($arr));

		$this->drip->TestCommonAPI($ip, $arr, $api_name);
	}
}