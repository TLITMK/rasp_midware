<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/25
 * Time: 10:59
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_openDoor extends Command
{
    protected $signature = 'FACE:open_door {ip}';

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

        $this->drip->openDoor($ip);

    }
}