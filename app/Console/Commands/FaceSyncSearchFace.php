<?php

namespace App\Console\Commands;

use App\Handler\FaceController;
use Illuminate\Console\Command;

class FaceSyncSearchFace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'face_sync:search_face';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '照片查询-找出设备上的照片写入student表和face_syncs表';

    protected $drip;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FaceController $drip)
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
        $this->drip->sync_face_serv();
    }
}
