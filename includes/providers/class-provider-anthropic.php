<?php
namespace LuxAI;
use WP_Error;


class Provider_Anthropic extends Provider {
public function chat($message,$args=[]){
$opts = Plugin::instance()->opts['providers']['anthropic'];
$body = [
'model'=>$opts['model']??'claude-3-5-sonnet-latest',
'max_tokens'=>$args['max_tokens']??1200,
'temperature'=>$args['temperature']??0.2,
'messages'=>[['role'=>'user','content'=>$message]],
'system'=>$args['system']??''
];
$url = rtrim($opts['base_url'],'/').'/v1/messages';
$res = wp_remote_post($url,[
'headers'=>[
'X-API-Key'=>$opts['api_key'],
'Content-Type'=>'application/json',
'Anthropic-Version'=>'2023-06-01'
],
'body'=>wp_json_encode($body),'timeout'=>30
]);
if (is_wp_error($res)) { Utils\Circuit_Breaker::fail('anthropic'); return $res; }
$code = wp_remote_retrieve_response_code($res);
$json = json_decode(wp_remote_retrieve_body($res),true);
if ($code>=400) { Utils\Circuit_Breaker::fail('anthropic'); return new WP_Error('luxai_anthropic_err','Anthropic error',['status'=>$code,'body'=>$json]); }
Utils\Circuit_Breaker::success('anthropic');
return $json['content'][0]['text']??'';
}
}
