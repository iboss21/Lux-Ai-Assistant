<?php
namespace LuxAI\Utils;


class Rate_Limit {
public static function check($bucket,$user_id,$opts){
$pm = max(1,intval($opts['per_minute']??10));
$ph = max(1,intval($opts['per_hour']??200));
$k1 = "luxai_rl_{$bucket}_m_{$user_id}"; $k2 = "luxai_rl_{$bucket}_h_{$user_id}";
$m = intval(get_transient($k1)); $h = intval(get_transient($k2));
if ($m >= $pm || $h >= $ph) return false;
set_transient($k1,$m+1, MINUTE_IN_SECONDS);
set_transient($k2,$h+1, HOUR_IN_SECONDS);
return true;
}
}
