<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use GuzzleHttp\Client;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('face_config');
})->name('face.configs');

Route::get('/photo',function(Request $request){
    $id=$request->input('id');
    $ip=$request->input('ip');
    $client=new Client();
    $url=$ip.':8090/face/find';
    $ips=env('FACE_IPS');
    if(!$ips){
        $doornum=env('DOOR_NUM');
        for($i=1;$i<=$doornum;$i++){
            $ip_arr[]='192.168.30.'.($i*5+3);
            $ip_arr[]='192.168.30.'.($i*5+4);
        }
    }else{
        $ip_arr=explode(',',$ips);
    }
//    return $ip_arr;
    if($ip){
        try{
            $response=$client->request('post',$url,[
                'timeout'=>1,
                'form_params'=>[
                    'pass'=>'spdc',
                    'personId'=>$id,
                ]
            ]);
            $res=json_decode($response->getBody(),true);
            if(!array_get($res,'data')){
                $res['data']=[];
            }
//        return $res;
            foreach ($res['data'] as &$item){
                if(!array_get($item,'imgBase64')){
                    $base64 = "" . chunk_split(base64_encode(file_get_contents($item['path'])));
                    $item['imgBase64']=$base64;
                }
            }
            return view('photo',['photo_arr'=>$res,'ip_arr'=>$ip_arr,'id'=>$id]);
        }catch (Throwable $e){
            return $e->getMessage();
        }
    }
    else{
        return view('photo',['photo_arr'=>['data'=>[]],'ip_arr'=>$ip_arr,'id'=>$id]);
    }

});

Route::get('/test', function () {
    info('test sssss');
    return 1;
});
Route::post('/face_union_auth',"UnionAttController@face_union_auth");
Route::post('/setConfig',"UnionAttController@setConfig")->name('face.setConfig');
