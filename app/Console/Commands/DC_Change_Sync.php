<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2019/12/20
 * Time: 4:15
 */

namespace App\Console\Commands;


use App\Handler\DCRechargeController;
use Illuminate\Console\Command;

class DC_Change_Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DC:change_sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新换卡人员';

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
        //

        $con=new DCRechargeController();
        $con->change_sync();
    }
}