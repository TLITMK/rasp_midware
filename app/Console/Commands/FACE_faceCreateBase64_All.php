<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/21
 * Time: 11:09
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceCreateBase64_All extends Command
{
    protected $signature = 'FACE:face_create_base64_all {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从树莓派执行 本地 照片注册base64';

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
        $this->drip->faceCreateBase64All($ip);
    }
}