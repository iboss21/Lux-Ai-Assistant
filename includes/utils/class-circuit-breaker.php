<?php
namespace LuxAI\Utils;


class Circuit_Breaker {
// Simple circuit breaker with rolling failure count and 5-minute open state
const FAIL_LIMIT = 3; // open after 3 consecutive failures
const OPEN_TTL = 300; // seconds


public static function key($provider,$suffix){ return "luxai_cb_{$provider}_{$suffix}"; }


public static function is_open($provider){
$open = get_transient(self::key($provider,'open'));
return !empty($open);
}


public static function fail($provider){
$k = self::key($provider,'fail'); $fails = intval(get_transient($k))+1; set_transient($k,$fails, self::OPEN_TTL);
if ($fails >= self::FAIL_LIMIT){ set_transient(self::key($provider,'open'), 1, self::OPEN_TTL); }
}


public static function success($provider){
delete_transient(self::key($provider,'fail')); delete_transient(self::key($provider,'open'));
}
}
