<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/7/3
 * Time: 16:19
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_syncPerson extends Command
{
    protected $signature = 'FACE:sync_person';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手动从三亚草同步人员';

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
        $this->drip->delAllRedisPerson();//删除redis记录，相当于新增全部人员
//        $this->drip->delAllPersonAndReids();45555555555555555555
        $ips=$this->drip->getAllFaceIps();
        foreach ($ips as $ip){
            $this->drip->delPerson($ip,-1);
        }
        $this->drip->getAllPersons();
        $this->drip->personDeleteByReids();//注意顺序！！！！！
        $this->drip->personCreateByRedis(36000);
        $this->drip->personUpdateByRedis();

    }
}