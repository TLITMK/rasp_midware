<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/8/14
 * Time: 15:24
 */

namespace App\Console\Commands;


use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class PI_redis_sub extends Command
{
    protected $signature = 'PI:redis_sub';
    //示例 php artisan TEST:CommonAPI 192.168.30.8 /person/findByPage personId^-1#length^1#index^0#pass^spdc
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'redis监听过期删除事件';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        #订阅消息redis
        Redis::subscribe(array('__keyevent@0__:expired'), function($key)  {
            //删除过期回调 用于2.4g设备考勤通知

            info('2.4g删除回调-'.$key.'-推送考勤消息');
            $arr=explode(':',$key);
            $cardid=$arr[1];
            $fd=$arr[2];
            $timestamp=$arr[3];
            $school_id=env('SCHOOL_ID');
            $client=new Client();
            $response=$client->request('POST',env('CURL_URL').'/24g/att_notice',[
                "form_params"=>[
                    "card_id"=>$cardid,
                    "school_id"=>$school_id,
                    "datetime"=>date('Y-m-d H:i:s',$timestamp),
                    "fd"=>$fd
                ]
            ]);

        });
    }
}