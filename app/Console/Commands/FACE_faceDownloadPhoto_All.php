<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/6/11
 * Time: 11:28
 */

namespace App\Console\Commands;


use App\Handler\FaceDetectController;
use Illuminate\Console\Command;

class FACE_faceDownloadPhoto_All extends Command
{
    protected $signature = 'FACE:downlad_photo {personIds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '仁德一中强制同步_下载照片到树莓派';

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
        $idarr=$this->argument('personIds');
        $idarr=explode(',',$idarr);
        $this->drip->downloadPhoto($idarr);
    }
}