<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HeartSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heartSync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '树莓派心跳上报';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @param HeartSync $drip
     */
    public function __construct(\App\Handler\HeartSync $drip)
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
        //
        $this->drip->sync();
    }
}
