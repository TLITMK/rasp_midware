<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/8/12
 * Time: 16:17
 */

namespace App\Console\Commands;


use App\Handler\SwooleHandler24g;
use App\HttpServer\HttpServer;
use App\SwooleServer\SwooleServer;
use Illuminate\Console\Command;

class PI_startTCP2_4 extends Command
{
    protected $signature = 'PI:start_2.4g_tcp';
    //示例 php artisan TEST:CommonAPI 192.168.30.8 /person/findByPage personId^-1#length^1#index^0#pass^spdc
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '打开swoole 2.4g tcp 舰艇';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SwooleServer $drip)
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
        $this->drip->start_tcp_24g();
    }
}