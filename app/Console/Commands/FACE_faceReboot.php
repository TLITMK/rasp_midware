<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/17
 * Time: 11:03
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceReboot extends Command
{
    protected $signature = 'FACE:face_reboot {ip}';

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

        $this->drip->restart($ip,'spdc');
    }
}