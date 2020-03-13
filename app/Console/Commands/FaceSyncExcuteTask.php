<?php

namespace App\Console\Commands;

use App\Handler\FaceController;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

Class FaceSyncExcuteTask extends Command{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'face_sync:excute_task';//执行task

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取10task并执行';
    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceController $drip)
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
        $arr_type = [1,2,3,4,6,7,8,9,10];
        foreach($arr_type as $k =>$v){
            $face = new FaceController;
            $face->get_task($v);
        }


    }
}
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/5/20
 * Time: 10:39
 */