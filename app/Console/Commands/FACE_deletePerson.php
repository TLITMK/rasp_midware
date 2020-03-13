<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/7/1
 * Time: 9:46
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_deletePerson extends Command
{
    protected $signature = 'FACE:person_del {ip} {id_str}';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '删除指定人员';

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
        $ids_str=$this->argument('id_str');
        $this->drip->delPerson($ip,$ids_str);
    }
}