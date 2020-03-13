<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TEST_pic_compose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TEST_PIC_COMPOSE';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试图片组合功能';

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

        $imgs    = array();
        $imgs[0] = 'C:\Users\山椒鱼拌饭\Pictures\1.jpg';
        $imgs[1] = 'C:\Users\山椒鱼拌饭\Pictures\2.jpg';


        $source = array();

        echo mime_content_type($imgs[0]).PHP_EOL;
        echo mime_content_type($imgs[1]).PHP_EOL;
        foreach ($imgs as $k => $v) {

            switch (mime_content_type($v)){
                case 'image/jpeg':
                    $source[$k]['source'] = Imagecreatefromjpeg($v);
                    break;
                case 'image/png':
                    $source[$k]['source'] = imagecreatefrompng($v);
                    break;
                default:
                    return;
            }
            $source[$k]['size'] = getimagesize($v);
        }
        $percent=728/$source[0]['size'][0];
        $target_h=floor($source[0]['size'][1]*$percent) >=floor($source[1]['size'][1]*$percent)
            ?floor($source[0]['size'][1]*$percent):floor($source[1]['size'][1]*$percent);
        $target_w=floor($source[0]['size'][0]*$percent)+floor($source[1]['size'][0]*$percent);
        $target_img = imagecreatetruecolor($target_w,$target_h);

        for ($i = 0; $i < 2; $i++) {
            imagecopyresized($target_img,$source[$i]['source'],floor($source[0]['size'][0]*$percent)*$i,0,0,0,$source[$i]['size'][0]*$percent,$source[$i]['size'][1]*$percent,$source[$i]['size'][0],$source[$i]['size'][1]);

        }
        Imagejpeg($target_img, 'C:\Users\山椒鱼拌饭\Pictures\pin.jpg');
        $content= file_get_contents('C:\Users\山椒鱼拌饭\Pictures\pin.jpg');
        $base64=base64_encode($content);
//        echo $base64;
    }
}
