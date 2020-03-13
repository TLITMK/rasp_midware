<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/24
 * Time: 18:06
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FACE_deleteAllPerson extends Command
{
    protected $signature = 'FACE:person_del_all {ip}';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接口测试-通用api';

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
        $keys=Redis::keys('PERSON:*');
        $ids_str='';
        foreach($keys as $key){
            $v=Redis::get($key);
            $v=json_decode($v,true);
            $ids_str.=$v['id'].',';
        }
        $this->drip->delPerson($ip,$ids_str);
        foreach($keys as $key){
            Redis::del($key);
        }
        Redis::set('PERSON_ADD:','');
        Redis::set('PERSON_DEL:','');
    }
}