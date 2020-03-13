<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SwooleClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tcp client start';


    protected $drip;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(\App\SwooleClient\SwooleClient $drip)
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
        $this->drip->start();
    }
}
