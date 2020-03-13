<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/7/2
 * Time: 11:37
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class PI_uploadFaceToClov extends Command
{
    protected $signature = 'PI:upload_img';
    //ids 逗号分隔
    //params 参数之间#分割  参数名和参数值^分割

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从树莓派上传照片到三叶草';

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
        $fs=new \Illuminate\Filesystem\Filesystem();

        // $arr=$fs->directories(env('PATH_ATT').'/storage/app/public/face');
        // foreach ($arr as $item){
        //     $id=$fs->name($item);
        //     $this->drip->uploadImg($id);
        // }
        $arr=[531166,530999,531338,529433,522899,531354,530958,531427,531438,531404,529434,522177,531207];
        foreach ($arr as $id){
            $this->drip->uploadImg($id);
        }
    }
}