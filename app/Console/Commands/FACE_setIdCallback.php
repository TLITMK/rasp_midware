<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/25
 * Time: 11:04
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_setIdCallback extends Command
{
    protected $signature = 'FACE:set_id_callback {ip} {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '设置识别回调';

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
        $url=$this->argument('url');

        $this->drip->setIdCallback($ip,$url);
    }

}