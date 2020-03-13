<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/9/17
 * Time: 15:10
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceCreateBase64_allTerminal extends Command
{
    protected $signature = 'FACE:face_create_base64_all_terminal {personIds}';

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
        $personIds=$this->argument('personIds');
        $personIds=explode(',',$personIds);
        //
        // $this->drip->sync_face();
        $doornum=env('DOOR_NUM');
        $ips=[];
        for ($i=0;$i<$doornum;$i++){
            $ip_str='192.168.30.'.(($i+1)*5+3);
            $ips[]=$ip_str;
            $ip_str='192.168.30.'.(($i+1)*5+4);
            $ips[]=$ip_str;
        }
        $fail_ips=[];
        foreach($personIds as $personId){
            foreach($ips as $ip){
                $bool1=$this->drip->delPersonFace($ip,$personId);
                if(!$bool1['success']){
                    echo $ip.'-'.$personId.'-清除照片失败1'.PHP_EOL;
                    $fail_ips[]=$ip.'-'.$personId;
                    break;
                }
                $bool=$this->drip->createBase64($ip,$personId);
                if(!$bool['success']){
                    echo $ip.'-'.$personId.'-注册照片失败2'.PHP_EOL;
                    $fail_ips[]=$ip.'-'.$personId;
                    break;
                }else{
                    echo $ip.'-'.$personId.'-操作成功'.PHP_EOL;
                }
            }

        }
        echo '失败ip: '.implode(PHP_EOL,$fail_ips);
    }
}