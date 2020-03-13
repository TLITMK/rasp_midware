<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use App\Handler\WHDCHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WH_TestAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'WH:test_api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '五和大成测试接口';

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
        $handle=new WHDCHandler();
        $handle->WHDC_Recharge('3462140850',1);


    }

    
}
