<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/9/17
 * Time: 16:24
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FACE_deleteAllFace extends Command
{
    protected $signature = 'FACE:delete_all_face {ip}';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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
        $suc_count=0;
        foreach($info_arr as $info){
            $info=json_decode($info);
            $personId=$info->id;
            $bool1=$this->drip->delPersonFace($ip,$personId);
            if(!$bool1['success']){
                echo $ip.'-'.$personId.'-清除照片失败1'.PHP_EOL;
                continue;
            }else{
                echo $ip.'-'.$personId.'-清除照片成功'.PHP_EOL;
                $suc_count++;
            }
        }
        echo $suc_count.'/'.count($info_arr).' 个人员清楚成功'.PHP_EOL;
    }
}