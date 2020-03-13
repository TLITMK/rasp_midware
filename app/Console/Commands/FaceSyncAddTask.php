<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/5/9
 * Time: 14:59
 */

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class FaceSyncAddTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'face_sync:add_task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '执行face_syncs表,写入task';


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
        // $this->drip->sync_face();
        try{
            $client = new Client;
            info('---------------------------');
            info('执行face_syncs表-获取并写入task-开始');
            $rt = $client->request('POST',env('CURL_URL').'/face/not_sync',[
                'form_params' => [
                    'school_id' => env('SCHOOL_ID',0)
                ]
            ]);
            info('执行face_syncs表-获取并写入task-返回',json_decode($rt->getBody(),true));
        }catch (\Exception $e){
            info(''.$e->getMessage());
        }

    }
}