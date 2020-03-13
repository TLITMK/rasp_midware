<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/7/5
 * Time: 12:13
 */

namespace App\Console\Commands;


use App\HttpServer\HttpServer;
use Illuminate\Console\Command;

class PI_startHttp extends Command
{
    protected $signature = 'PI:start_http';
    //示例 php artisan TEST:CommonAPI 192.168.30.8 /person/findByPage personId^-1#length^1#index^0#pass^spdc
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '打开swoole http';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HttpServer $drip)
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
         $this->drip->http_serv();
    }
}