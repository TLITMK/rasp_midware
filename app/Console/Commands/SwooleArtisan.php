<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SwooleServer\SwooleServer;

class SwooleArtisan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swoole:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tcp swoole start';


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
        //
        $this->drip->start_tcp();
    }
}
