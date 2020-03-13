<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/10/17
 * Time: 15:54
 */

namespace App\Console\Commands;


use GuzzleHttp\Client;
use Illuminate\Console\Command;

class SnapTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test_cam_snap {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试sqlserver访问';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ip=$this->argument('ip');
        $time_str=date('Y-m-d_H:i:s',time());
        //http 抓拍
        $client = new Client();
        $rt = $client->request('GET', $ip,
            ['auth' => [env('CAMERA_USER_NAME','admin'), env('CAMERA_PWD','admin123')]]);


        file_put_contents(env('PATH_ATT').'/storage/app/public/att_images/'.'test'.$time_str.'.jpg',$rt->getBody());

    }
}