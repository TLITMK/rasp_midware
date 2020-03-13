<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/11
 * Time: 15:59
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceCreateUrl extends Command
{
    protected $signature = 'FACE:face_create_url {ip} {personId} {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接口测试-url照片注册';

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
        $url=$this->argument('url');

        $bool1=$this->drip->delPersonFace($ip,$personId);
        if(!$bool1){
            echo $ip.'-'.$personId.'-清除照片失败1'.PHP_EOL;
            return;
        }
        $bool=$this->drip->createByurl($ip,$personId,$personId,$url);
        if(!$bool){
            echo $ip.'-'.$personId.'-注册照片失败1'.PHP_EOL;
        }else{
            echo $ip.'-'.$personId.'-操作成功'.PHP_EOL;
        }
    }

}