<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/24
 * Time: 17:31
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class PI_GetSchoolId extends Command
{

    protected $signature = 'PI:school_id';
    //示例 php artisan TEST:CommonAPI 192.168.30.8 /person/findByPage personId^-1#length^1#index^0#pass^spdc
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取树莓派env文件school_id';

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
        echo $this->drip->getSchoolId().PHP_EOL;
    }
}