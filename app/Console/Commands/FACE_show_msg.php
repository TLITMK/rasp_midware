<?php

namespace App\Console\Commands;

use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_show_msg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FACE:show_msg {ip} {msg} {speak}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '人脸识别显示消息提示';
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
        $ip=$this->argument('ip');
        $msg=$this->argument('msg');
        $speak=$this->argument('speak');

        $this->drip->show_content($ip,$msg,$speak);
    }
}
