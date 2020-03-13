<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/25
 * Time: 10:51
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_setPassword extends Command
{
    protected $signature = 'FACE:set_password {ip} {oldPass} {newPass}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '设置密码';

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
        $oldPass=$this->argument('oldPass');
        $newPass=$this->argument('newPass');

        $this->drip->setPassword($ip,$oldPass,$newPass);

    }
}