<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/9/17
 * Time: 16:08
 */

namespace App\Console\Commands;


use GuzzleHttp\Client;
use Illuminate\Console\Command;

class FACE_deleteRecords extends Command
{
    protected $signature = 'FACE:delete_recs {ip}';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'face接口 删除所有识别记录';

    protected $drip;

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

        $ip=$this->argument('ip') ;
        $url = $ip.':8090/deleteRecords';
        $time=date('Y-m-d H:i:s',time());
        $client=new Client();
        $response=$client->request('POST',$url,[
            'form_params' => [
                'pass' => 'spdc',
                'time'=>$time
        ]]);
        $res=json_decode($response->getBody(),true);
        if($res['success']){
            echo '删除成功';
        }else{
            echo '删除失败';
        }
        info('',$res);

    }

}