<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/26
 * Time: 10:29
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_downFaceFromTernimal extends Command
{

    protected $signature = 'FACE:down_from {ip}';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从某台设备下载照片到树莓派face文件夹';

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

        $ip=$this->argument('ip') ;
        $this->drip->downFaceFromTerminal($ip);
    }
}