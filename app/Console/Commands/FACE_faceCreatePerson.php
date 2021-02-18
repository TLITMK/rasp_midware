<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/10
 * Time: 16:10
 */

namespace App\Console\Commands;


use App\Handler\ClientHandler;
use App\Handler\FaceDetectController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FACE_faceCreatePerson extends Command
{
    protected $signature = 'FACE:person_create {ip} {id} {name} {idcardNum}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从三叶草同步注册人员';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceDetectController $obj)
    {
        parent::__construct();
        $this->drip = $obj;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ip=$this->argument('ip');
        $id=$this->argument('id');
        $name=$this->argument('name');
        $idcardNum=$this->argument('idcardNum');
//        $this->drip->personCreateAll($ip);
        $person_json=json_encode([
            'id'=>$id,
            'name'=>$name,
            'idcardNum'=>$idcardNum
        ]);
        $res=$this->drip->personCreate($ip,$person_json);
        echo $res['success'].PHP_EOL;
    }
}