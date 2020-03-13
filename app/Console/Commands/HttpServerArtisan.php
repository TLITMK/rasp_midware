<?php

namespace App\Console\Commands;

use App\HttpServer\HttpServer;
use Illuminate\Console\Command;

class HttpServerArtisan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'http swoole start';

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
        $this->drip->http_start();
    }
}
