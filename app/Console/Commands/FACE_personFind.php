<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/25
 * Time: 10:59
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class FACE_personFind extends Command
{
    protected $signature = 'FACE:person_find {ip} {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '远程开门';

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
        //
        // $this->drip->sync_face();
        $ip=$this->argument('ip');
        $id=$this->argument('id');

        $client = new Client();
        $url = $ip . ':8090/person/find';
        try {
            $response = $client->request('GET', $url, [
                'query' => [
                    'pass' => 'spdc',
                    'id' => $id,
                ],
            ]);
            $res = json_decode($response->getBody(), true);
            echo ($response->getBody()) ;
        } catch (\Exception $e) {
            echo ('人员查询-异常' . $e->getMessage());
            return ['success'=>false,'data'=>false];
        }

    }
}