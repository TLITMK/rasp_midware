<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/10
 * Time: 16:10
 */

namespace App\Console\Commands;


use App\Handler\ClientHandler;
use App\Handler\FaceDetectController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FACE_faceCreatePerson_All extends Command
{
    protected $signature = 'FACE:person_create_all {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从三叶草同步注册人员';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceDetectController $obj)
    {
        parent::__construct();
        $this->drip = $obj;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        // $this->drip->sync_face();
        $ip=$this->argument('ip');
//        $redis=Redis::connection();
//        $keys=$redis->keys('PERSON_INFO:*');
//        $info_arr=$redis->pipeline(function($pipe)use($keys){
//            foreach($keys as $k){
//                $pipe->get($k);
//            }
//        });
//        $suc_count=0;
//        foreach($info_arr as $info){
//            if(!$info)continue;
//            $arr=json_decode($info,true);
//            $v=str_replace('card_id','idcardNum',$info);
//            $v=str_replace('stu_name','name',$v);
//            $hasPerson=$this->drip->personFind($ip,$arr['id']);
//            if($hasPerson['success']){
//                $bool=$this->drip->personUpdate($ip,$v);
//            }else{
//                $bool =$this->drip->personCreate($ip,$v);
//            }
//            if($bool ){
//                info($ip.'-'.$arr['card_id'].'-redis添加人员-成功');$suc_count++;
//            }else{
//                info($ip.'-'.$arr['card_id'].'-redis添加人员-失败');
//            }
//
//
//        }
//        echo $suc_count.'/'.count($info_arr).' 个人员注册成功'.PHP_EOL;
        $this->drip->personCreateAll($ip);
//        $this->drip->personCreate769_2019($ip);
    }
}