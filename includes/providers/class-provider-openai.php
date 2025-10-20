<?php
namespace LuxAI;
use WP_Error;

class Provider_OpenAI extends Provider {
    public function chat($message,$args=[]){
        $opts = Plugin::instance()->opts['providers']['openai'];
        $body = [
            'model'=>$opts['model']??'gpt-4o-mini',
            'messages'=>[['role'=>'system','content'=>$args['system']??''],['role'=>'user','content'=>$message]],
            'temperature'=>$args['temperature']??0.2,
            'max_tokens'=>$args['max_tokens']??1200,
        ];
        $url = rtrim($opts['base_url'],'/').'/chat/completions';
        $res = wp_remote_post($url,[ 'headers'=>['Authorization'=>'Bearer '.$opts['api_key'],'Content-Type'=>'application/json'], 'body'=>wp_json_encode($body), 'timeout'=>30 ]);
        if (is_wp_error($res)) { \LuxAI\Utils\Circuit_Breaker::fail('openai'); return $res; }
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res),true);
        if ($code>=400) { \LuxAI\Utils\Circuit_Breaker::fail('openai'); return new WP_Error('luxai_openai_err','OpenAI error',['status'=>$code,'body'=>$json]); }
        \LuxAI\Utils\Circuit_Breaker::success('openai');
        return $json['choices'][0]['message']['content']??'';
    }
}
