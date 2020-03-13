<?php
/**
 * Created by PhpStorm.
 * User: VMU
 * Date: 2016/9/26
 * Time: 14:46
 */
namespace App\Services;


use Intervention\Image\Facades\Image;

class Helper
{
    public static function imgToBase64($img_path,$prefix)
    {
//        $name = uniqid();
//        //$img = Image::make($img)->resize($x_size, $y_size);
//        $img = Image::make($img);
//        $img->save(env('PATH_ATT').'/public/face/'.$name.'.jpg');
        //$base_code = Helper::base64EncodeImage(env('PATH_ATT').'/public/face/'.$name.'.jpg');
        if($prefix){
            return  chunk_split(base64_encode(file_get_contents($prefix.$img_path)));
        }else{

            return Helper::base64EncodeImage($img_path);
        }


    }

    public static function base64EncodeImage($image_file) {
        $file=fopen($image_file,'r');
        $size=filesize($image_file);
        info($file);
        info($size);
        $image_data = fread($file, $size);
        $base64_image = base64_encode($image_data);
        @fclose($image_file);
        return $base64_image;
    }

    function imgtobase64_test($img='')
    {
        $imageInfo = getimagesize($img);
        info($imageInfo);
        //$base64 = "" . chunk_split(base64_encode(file_get_contents($img)));
        return  chunk_split(base64_encode(file_get_contents($img)));
    }



}