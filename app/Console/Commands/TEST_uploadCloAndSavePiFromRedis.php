<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/28
 * Time: 15:34
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class TEST_uploadCloAndSavePiFromRedis extends Command
{
    protected $signature = 'TEST:upclo_savepi_redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从redis读取拍照注册的照片，上传到三叶草并保存到树莓派';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceDetectController $drip)
    {
        parent::__construct();
        $this->drip=$drip;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->drip->downFaceFromTerminalById();
    }
}