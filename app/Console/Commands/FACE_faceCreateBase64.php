<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/11
 * Time: 16:16
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceCreateBase64 extends Command
{
    protected $signature = 'FACE:face_create_base64 {ip} {personId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接口测试-照片注册base64';

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
        //
        // $this->drip->sync_face();
        $ip=$this->argument('ip');
        $personId=$this->argument('personId');
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
        }
    }
}