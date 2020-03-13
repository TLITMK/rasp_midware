<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/8/12
 * Time: 16:22
 */

namespace App\Handler;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;

class SwooleHandler24g {
	protected $camera_status;
	//连接
	public function onConnect($server, $fd, $from_id) {
		echo "2.4g连接成功" . $fd . PHP_EOL;
		//获取设备基本信息
		$body = pack('vA2Ch', dechex(5), '24', 0x01, 0);
		$send_data = $this->send_data($body);
		$server->send($fd, $send_data);
	}

	//关闭连接
	public function onClose($server, $fd, $from_id) {
		echo "connection close: {$fd}\n";
		$client = new Client();
		$response = $client->request('POST', env('CURL_URL') . '/24g/offline', [
			"form_params" => [
				"fd" => $fd,
			],
		]);
		$res = json_decode($response->getBody(), true);
		info('24g-上报离线', $res);
	}

	//接收数据
	public function onReceive($server, $fd, $from_id, $data) {

		$head = unpack('A2', $data, 0);
		info('2.4g收到控制板消息：fd=' . $fd);
		//包头验证
		if ($head[1] != 'gy') {
			info('请求头错误', $head);
			return;
		}

		$len = unpack('v', $data, 2);
		$terminal = unpack('A2', $data, 4);
		$cmd = unpack('H2', $data, 6);
		switch ($cmd[1]) {
			case '81': //D->S当服务端主动询问设备序列号时，设备回应命令0x81
			break;
			case '01': //D->S设备主动上报设备系列号
				$terminal_num = unpack('V', $data, 7);
				$hver1 = unpack('C1', $data, 11);
				$hver2 = unpack('C1', $data, 12);
				$sver1 = unpack('C1', $data, 13);
				$sver2 = unpack('C1', $data, 14);
				$terminal_type = unpack('H2', $data, 15);
				$sum = unpack('C1', $data, 17);
				info('01-上报设备基础信息', [
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'设备编号' => $terminal_num[1],
					'硬件版本' => $hver1[1] . '.' . $hver2[1],
					'软件版本1' => $sver1[1] . '.' . $sver2[1],
					'硬件类型' => $terminal_type[1],
					'校验和' => $sum[1],
				]);

				//更新设备表
				$info = [
					'terminal_num' => $this->uint32val($terminal_num[1]),
					'terminal_type_id' => 6,
					'school_id' => env('SCHOOL_ID'),
					'fd' => $fd,
					'status' => 1,
					'volume' => 0,
					'break_voice' => '',
					'start_voice' => '',
					'set_time' => '',
					'template_id' => 0,
					'attendances_address_id' => 0,
				];
				$client = new Client();
				$response = $client->request('POST', env('CURL_URL') . '/24g/update', [
					'form_params' => $info,
				]);
				$res = json_decode($response->getBody(), true);
				info('更新设备成功', $res);

				$body = pack('vA2Ch', dechex(5), 'rf', 0x81, 1);
				$send_data = $this->send_data($body);
				$server->send($fd, $send_data);
				break;
			case '02': //D->S设备主动向服务端请求时间
				$timestamp = time();
				info('02-时间同步', [
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'时间戳' => $timestamp,
				]
				);
				$body = pack('vA2CV', dechex(8), 'rf', 0x82, $timestamp);
				$send_data = $this->send_data($body);
				$server->send($fd, $send_data);
				break;
			case '82': //D->S当服务端主动设置设备时间的时候，设置成功设备回应命令0x82
				break;
			case '03'://D->S设备请求设置上报标准 20秒内，（n/20）% n为卡片识别次数,达到分比才会上报
				$body=pack('vA2CC',dechex(5), 'rf', 0x83,50);//此处默认90% 18次
				$send_data=$this->send_data($body);
				$server->send($fd,$send_data);
				info('03-设置标准', [
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'百分比' => 50,
				]
				);
				break;
			case '03dd': //D->S设备主动向服务器上报2.4G卡号信息
				//每个卡信息11字节
				$cardcount = ($len[1] - 4) / 11;
				info('03-卡信息', [
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'卡数量' => $cardcount]);
				for ($index = 0; $index < $cardcount; $index++) {
					$cardData = unpack('H22', $data, 7 + $index * 11);
					$cardid = unpack('V', $data, 7 + $index * 11);
					$power = unpack('C1', $data, 11 + $index * 11);
					$y = unpack('C1', $data, 12 + $index * 11);
					$m = unpack('C1', $data, 13 + $index * 11);
					$d = unpack('C1', $data, 14 + $index * 11);
					$h = unpack('C1', $data, 15 + $index * 11);
					$i = unpack('C1', $data, 16 + $index * 11);
					$s = unpack('C1', $data, 17 + $index * 11);

					$datetime = $this->getDateTime($y[1], $m[1], $d[1], $h[1], $i[1], $s[1]);
					info('03-卡信息', [
						'$i' => $index,
						'卡号H' => $cardData[1],
						'卡号V' => $this->uint32val($cardid[1]),
						'电量' => $power[1],
						'日期' => $datetime,
					]);
					Redis::setex('24gCardInfo:' . $this->uint32val($cardid[1]) . ':' . $fd . ':' . strtotime($datetime), 120, $datetime);
				}
				$sum = unpack('C1', $data, $len[1] + 4 - 1);
				info('校验和:' . $sum[1]);

				//返回
				$body = pack('vA2Ch', dechex(5), '24', 0x83, 0);
				$send_data = $this->send_data($body);
				$server->send($fd, $send_data);
				break;
			case '04': //D->S设备主动上报心跳包
				$timestamp=unpack('V', $data,7);//
				$temperature=unpack('v', $data,11);
				info('04-心跳温度', [
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'时间戳'=>$timestamp[1],
					'温度'=>$temperature[1]
				]);
				//返回
				$body = pack('vA2Ch', dechex(5), 'rf', 0x84, 1);
				$send_data = $this->send_data($body);
				$server->send($fd, $send_data);
				break;
				case '05':
				$timestamp=unpack('V', $data , 7);
				$card=unpack('N', $data ,12);
				$percent=unpack('C',$data,21);
				info('05-达标卡片上报',[
					'请求头' => $head[1],
					'数据长度' => $len[1],
					'设备类型' => $terminal[1],
					'指令' => $cmd[1],
					'时间戳'=>$timestamp[1],
					'卡号'=>$this->uint32val($card[1]),
					'标准比例'=>$percent[1]
				]);
				$body=pack('vA2Ch',dechex(5),'rf',0x85,1);
				$send_data=$this->send_data($body);
				$server->send($fd,$send_data);
				break;
		}


	}

	//type1 摄像头抓拍照片  type2 非法闯入7秒视频抓拍
	public function onTask($server, $task_id, $src_worker_id, $data) {

	}

	public function onFinish($serv, $task_id, $data) {

	}

	public function send_data($body) {
		$head = pack('A2', 'gy');
		$sum = dechex(array_sum(unpack('C*', $body)) % 256);
		$send_data = $head . $body . pack('C', $sum);

		return $send_data;
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

	protected function getDateTime($y, $m, $d, $h, $i, $s) {
		$y = sprintf("%02d", $y);
		$m = sprintf("%02d", $m);
		$d = sprintf("%02d", $d);
		$h = sprintf("%02d", $h);
		$i = sprintf("%02d", $i);
		$s = sprintf("%02d", $s);
		$datetime = '20' . $y . '-' . $m . '-' . $d . ' ' . $h . ':' . $i . ':' . $s;
		return $datetime;
	}
}