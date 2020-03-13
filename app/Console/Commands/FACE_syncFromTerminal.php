<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/10/17
 * Time: 17:44
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class FACE_syncFromTerminal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FACE:sync_from_terminal {fromip} {toip} {ids}' ;

    protected $drip;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从一台设备同步,fromip toip ids逗号分隔';

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
        $client=new Client();
        $fromip=$this->argument('fromip');
        $toip=$this->argument('toip');
        $ids=$this->argument('ids');
        $ids=explode(',',$ids);
        $url=$fromip.':8090/face/find';
        foreach ($ids as $k=>$v){
            //查询照片
            $response=$client->request('POST',$url,[
                'form_params' => [
                    'pass' => 'spdc',
                    'personId' => $v,
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            info('照片查询结果',$res);
            if(!count($res['data']))continue;
            $img_url=$res['data'][0]['path'];
            $delinfo=$this->drip->delPersonFace($toip,$v);
            if(!$delinfo['success']){info($v.'失败');continue;}
            $this->drip->createByurl($toip,$v,$v,$img_url);
        }
    }
}