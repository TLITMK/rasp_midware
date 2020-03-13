<?php
/**
 * Created by PhpStorm.
 * User: 山椒鱼拌饭
 * Date: 2019/6/27
 * Time: 10:09
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class PI_getImgsFromTerminal extends Command
{
    protected $signature = 'PI:get_imgs_from_terminal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从所有设备下载照片到树莓派';

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

//        $this->drip->restart($ip,'spdc');
    }
}