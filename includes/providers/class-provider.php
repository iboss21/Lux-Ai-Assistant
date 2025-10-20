<?php
namespace LuxAI;


abstract class Provider { abstract public function chat($message, $args=[]); }


class Providers {
public static function pick($preferred='auto'){
$opts = Plugin::instance()->opts; $o=!empty($opts['providers']['openai']['enabled']) && !empty($opts['providers']['openai']['api_key']); $a=!empty($opts['providers']['anthropic']['enabled']) && !empty($opts['providers']['anthropic']['api_key']);
// Circuit breakers
if ($preferred==='openai' && $o && !Utils\Circuit_Breaker::is_open('openai')) return new Provider_OpenAI();
if ($preferred==='anthropic' && $a && !Utils\Circuit_Breaker::is_open('anthropic')) return new Provider_Anthropic();
if ($o && !Utils\Circuit_Breaker::is_open('openai')) return new Provider_OpenAI();
if ($a && !Utils\Circuit_Breaker::is_open('anthropic')) return new Provider_Anthropic();
return new \WP_Error('luxai_no_provider','No AI provider configured or temporarily unavailable',['status'=>400]);
}
}
