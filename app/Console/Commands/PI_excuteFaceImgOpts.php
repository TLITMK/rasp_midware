<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/7/17
 * Time: 14:41
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class PI_excuteFaceImgOpts extends Command
{
    protected $signature = 'PI:excute_faceimg_upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手动同步上传的照片 一般5分钟自动执行';

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
        $this->drip->excuteFaceImgUpload();
    }
}