<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2019/9/1
 * Time: 13:42
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class FACE_setConfig extends Command
{
//    protected $signature = 'FACE:setConfig {ip} {distance} {idScore}';
    protected $signature = 'FACE:setConfig {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '高新四小setconfig';


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
        $doornum=env('DOOR_NUM');
        $ip=$this->argument('ip');
//        $dist=$this->argument('distance');
//        $score=$this->argument('idScore');
        $url=$ip.':8090/setConfig';
        $client=new Client();

        $param='{
            "comModType":1,
            "comModContent":"hello",
            "companyName":"三叶草智慧校园",
            "delayTimeForCloseDoor":500,
            "displayModType":1,
            "displayModContent":"{name}",
            "identifyDistance":3,
            "saveIdentifyTime":3,
            "identifyScores":"72",
            "multiplayerDetection":2,
            "recRank":"1",
            "recStrangerTimesThreshold":3,
            "recStrangerType":"2",
            "ttsModStrangerType":1,
            "ttsModStrangerContent":"陌生人",
            "ttsModType":"2",
            "ttsModContent":"{name}",
            "wg":"#WG{id}#",
            "whitelist":1,
            "saveIdentifyMode":1,
            "onLightStartTime":0,
            "onLightEndTime":0
        }';
//        $data_pas='{
//            "identifyDistance":'.$dist.',
//            "identifyScores":'.$score.',
//            "saveIdentifyTime":0,
//            "ttsModType":100,
//            "ttsModContent":"欢迎{name}",
//            "comModType":1,
//            "comModContent":"hello",
//            "displayModType":100,
//            "displayModContent":"{name}欢迎你",
//            "slogan":"三叶草智慧校园",
//            "intro":"三叶草智慧校园",
//            "recStrangerTimesThreshold":3,
//            "recStrangerType":2,
//            "ttsModStrangerType":100,
//            "ttsModStrangerContent":"陌生人",
//            "multiplayerDetection":2,
//            "wg":"#34WG{id}#",
//            "recRank":2,
//            "delayTimeForCloseDoor":500,
//            "companyName":"维护电话：18808800781"}';

//        $param='{"comModContent":"hello","comModType":1,"companyName":"三叶草智慧校园","delayTimeForCloseDoor":500,"displayModContent":"{name}","displayModType":1,"identifyDistance":2,"identifyScores":75,"multiplayerDetection":2,"onLightEndTime":0,"onLightStartTime":0,"recRank":3,"recStrangerTimesThreshold":3,"recStrangerType":2,"saveIdentifyMode":1,"saveIdentifyTime":3,"ttsModContent":"{name}","ttsModStrangerContent":"陌生人","ttsModStrangerType":100,"ttsModType":100,"wg":"#WG{id}#","whitelist":1}';
        $response=$client->request('POST',$url,[
            'form_params'=>[
                'pass'=>'spdc',
                'config'=>$param
            ]
        ]);
        $rt = json_decode($response->getBody(),true);
        info($ip.'-设置配置选项-返回-'.$rt['msg']);
        echo $rt['msg'];

    }
}