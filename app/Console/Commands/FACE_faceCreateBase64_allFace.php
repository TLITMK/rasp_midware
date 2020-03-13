<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/9/17
 * Time: 15:20
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FACE_faceCreateBase64_allFace extends Command
{
    protected $signature = 'FACE:face_create_base64_all_face {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接口测试-照片注册base64 一个人员照片，注册到所有设备';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceDetectController $drip)
    {
        parent::__construct();
        $this->drip = $drip;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ip=$this->argument('ip');
        //
        // $this->drip->sync_face();
        $redis=Redis::connection();
        $keys=$redis->keys('PERSON:*');
        $info_arr=$redis->pipeline(function($pipe)use($keys){
            foreach($keys as $k){
                $pipe->get($k);
            }
        });
        $count_suc=0;
        foreach($info_arr as $info){
            $info=json_decode($info);
            $personId=$info->id;
            $bool1=$this->drip->delPersonFace($ip,$personId);
            if(!$bool1['success']){
                echo $ip.'-'.$personId.'-清除照片失败1'.PHP_EOL;
                return;
            }
            $bool=$this->drip->createBase64($ip,$personId);
            if(!$bool['success']){
                echo $ip.'-'.$personId.'-注册照片失败2'.PHP_EOL;
            }else{
                echo $ip.'-'.$personId.'-操作成功'.PHP_EOL;
                $count_suc++;
            }
        }
        echo '全部'.count($keys).'个人员,其中'.$count_suc.'注册成功';

    }
}