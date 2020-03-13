<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/10
 * Time: 16:39
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceCreateUrl_All extends Command
{

    protected $signature = 'FACE:face_create_url_all {ip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '仁德一中强制同步_照片url注册';

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
        $this->drip->faceCreateUrl($ip);
    }
}