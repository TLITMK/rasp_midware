<?php

namespace App\Console\Commands;

use App\Handler\DCRechargeController;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DC_Sync_CardInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DC:sync_card_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步东川一中cardinfo到三叶草';

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
        $con->sync_card_info();
    }
}
