<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2020-09-15
 * Time: 16:25
 */

namespace App\Handler;


use App\Http\Controllers\Controller;
use App\Services\Tools;
use Illuminate\Support\Facades\Redis;

/**
 * Class FaceSyncController
 * @package App\Handler
 * 创建时间 2020年9月15日
 * 新版同步分支 修改为以设备为单位同步 （原来为以人员为单位）
 */
class FaceSyncController extends Controller
{
    use Tools;
    public function getAllPersons() {
        info('人员查询同步REDIS-开始');
        $school_id = env('SCHOOL_ID', 0);
        $client = new Client();
        if (!$school_id) {
            info('请设置env文件中的SCHOOL_ID！！');
            return;
        }
        try {

            $res = $client->request('POST', env('CURL_URL') . '/get_all_stus', [
                'form_params' => [
                    'school_id' => env('SCHOOL_ID'),
                ],
            ]);
            $res = json_decode($res->getBody(), true);
            $old_key_list = Redis::keys('PERSON:*');
//            Redis::del('OLD_PERSON:*');
            foreach ($old_key_list as $k) {
                $t = Redis::get($k);
                // OLD_PERSON: 记录同步前的旧数据
                Redis::set('OLD_' . $k, $t);
            }
//            if(count($old_key_list)){
//                $count=Redis::del(Redis::keys('PERSON:*'));
//                info('redis 删除person:*  数量'.$count);
//            }
            $unioncardkeys=Redis::keys('UNION_CARD:*');
            if(count($unioncardkeys)){
                $del_union_card=Redis::del($unioncardkeys);
                info('redis 删除UNION_CARD:*  数量'.$del_union_card);
            }
            info('rec_' . count($res['data'] )."+".count($res['tdata']). '-old list-' . count($old_key_list));
            $new_keys_for_del=[];//这个用于del 不包含被删除人员
            foreach ($res['data'] as $item) {
                $test_arr_json = [
                    "card_id" => sprintf('%010s', $item['card_id']),
                    "id" => $item['id'],
                    "stu_name" => $item['stu_name'],
                    "union_cards"=>$item['union_cards']?$item['union_cards']['card_id']:'',
                    "union_time"=>$item['union_cards']?$item['union_cards']['union_time']:'',
                    'all_cards'=>$item['all_cards']
                ];
                Redis::set('PERSON:' . $item['id'], json_encode($test_arr_json, true));

                //联动卡
                if($item['union_cards']){
                    $card_arr=explode(',',$item['union_cards']['card_id']);
                    foreach ($card_arr as $card){
                        if($card){
                            Redis::set('UNION_CARD:'.$card,$item['id']);
                        }
                    }
                }
                $new_keys_for_del[]="PERSON:".$item['id'];
            }
            foreach ($res['tdata'] as $item){
                $test_arr_json=[
                    "card_id" => sprintf('%010s', $item['card_id']),
                    "id" => 't'.$item['id'],
                    "stu_name" => $item['name'],
                    "union_cards"=>'',
                    'all_cards'=>$item['all_cards']
                ];
                Redis::set('PERSON:t' . $item['id'], json_encode($test_arr_json, true));
                $new_keys_for_del[]="PERSON:t".$item['id'];
            }

            $new_keylist = Redis::keys('PERSON:*');//这个用于add 因为包含已删除人员。
            info('new list-' . count($new_keylist));
            $same_list = array_intersect($new_keylist, $old_key_list);

            //更新人员 添加记录 记录卡号 从PERSON获取信息
            $old_val_list = Redis::pipeline(function ($pipe) use ($same_list) {
                foreach ($same_list as $item) {
                    $pipe->get('OLD_' . $item);
                }
            });
            $new_val_list = Redis::pipeline(function ($pipe) use ($same_list) {
                foreach ($same_list as $item) {
                    $pipe->get($item);
                }
            });
            $diff_upd = array_diff($new_val_list, $old_val_list);
            $diff_del = array_diff($old_key_list, $new_keys_for_del);
            $diff_add = array_diff($new_keylist, $old_key_list);
            if (count($diff_del)) {
//删除
                //人员删除 添加删除记录 从旧PERSONI_INFO 获取信息
                $ids = '';
                foreach ($diff_del as $k) {
                    $temp = Redis::get('OLD_' . $k);
                    $person_id = json_decode($temp, true)['id'];
                    info('del_id', [$person_id]);
                    $ids .= $person_id . ',';
                }
                Redis::set('PERSON_DEL:', $ids);
                $del_list = Redis::get('PERSON_DEL:');
                info('PERSON_DEL:', [$del_list]);
            }
            if (count($diff_add)) {
                //人员注册 添加注册记录 记录卡号 从新PERSON获取信息
                $ids = '';
                foreach ($diff_add as $k) {
                    $card_id = substr($k, 7);
                    $ids .= $card_id . ',';

                }
                Redis::set('PERSON_ADD:', $ids);
                $add_list = Redis::get('PERSON_ADD:');
                info('PERSON_ADD', [$add_list]);
            }
            if (count($diff_upd)) {
                if(Redis::get("PERSON_UPD:")){
                    info('已有更新内容 暂时跳过');
                }
                else{
                    //对比更新内容 添加修改记录 从新PERSON 获取信息
                    $infos = ''; //# 分隔 已处理格式 满足注册人员接口
                    foreach ($diff_upd as $v) {
                        $v = str_replace('card_id', 'idcardNum', $v);
                        $v = str_replace('stu_name', 'name', $v);
                        $infos .= $v . '#';
                    }
                    Redis::set('PERSON_UPD:', $infos);
                    info('PERSON_UPD:', [$infos]);
                }
            }

        } catch (\Exception $e) {
            info('人员查询同步REDIS-异常-' . $e->getMessage());
        }
    }
    public function personDeleteByReids() {
        $controller=new FaceDetectController();
        $redis = Redis::connection();
        $del_str = $redis->get('PERSON_DEL:');
        if (!$del_str) {
            info('redis人员删除-没有可删除的人员');
            return;
        }
        $terminal_ips = $this->getAllFaceIps();
        $fail_arr = [];
        foreach ($terminal_ips as $ip) {
            $re = $controller->delPerson($ip, $del_str);
            if(env('FACE_FORCE_SYNC')&&!$re['success']){
                continue;
            }
            $fail = $re['data']['invalid'];
            $fail = explode(',', $fail);
            $fail_arr = array_keys(array_flip($fail_arr) + array_flip($fail));
        }
        $fail_str = implode(',', $fail_arr);
        $redis->set('PERSON_DEL:', $fail_str);
        $succ_str=$re['data']['effective'];
        $succ=explode(',',$succ_str);
        info('设备人员删除成功',$succ);
        foreach ($succ as $v){
            if(!$v)continue;
            $info_arr=$redis->get('PERSON:'.$v);
            if($info_arr){
                $info_arr=json_decode($info_arr,true);
                $card_arr=explode(',',$info_arr['union_cards']);
                foreach ($card_arr as $card){
                    $redis->del('UNION_CARD:'.$card);
                }
            }
            $redis->del('PERSON:'.$v);
            info('redis删除'.$v);
        }
        info('redis人员删除-完成-剩余删除失败-' . $fail_str);
    }
}