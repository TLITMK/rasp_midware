<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestSnap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test_snap {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test snap from camera';

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
        try {
            $ip = $this->argument('ip');
            $client = new Client();
            echo env('CAMERA_USER_NAME','admin').':'.env('CAMERA_PWD','admin123').PHP_EOL;
            $rt = $client->request('GET', $ip.'/ISAPI/Streaming/channels/1/picture',
                ['auth' => [env('CAMERA_USER_NAME','admin'), env('CAMERA_PWD','admin123')]]);

            file_put_contents(env('PATH_ATT').'/template/images/test_snap.jpg',$rt->getBody());

            $this->info('get snap image ok!');
        }catch (\Exception $e) {
            $this->error('snap camera error: '.$e->getMessage());

        }
    }
}
