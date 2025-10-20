<?php
namespace LuxAI\Utils;
class Cost_Guard {
    public static function allow_send($bucket){
        $opts = \LuxAI\Plugin::instance()->opts;
        if (empty($opts['cost_guardrail']['enabled'])) return true;
        $cap = floatval($opts['cost_guardrail']['daily_usd_cap'] ?? 3.0);
        $spent = floatval(get_transient('luxai_cost_today_usd') ?: 0.0);
        return ($spent < $cap);
    }
    public static function add_usage($provider, $tokens){
        $opts = \LuxAI\Plugin::instance()->opts;
        $price = 0.001;
        if ($provider==='openai') $price = floatval($opts['providers']['openai']['cost_per_1k'] ?? 0.15)/1000.0;
        if ($provider==='anthropic') $price = floatval($opts['providers']['anthropic']['cost_per_1k'] ?? 0.20)/1000.0;
        $delta = $tokens * $price;
        $k = 'luxai_cost_today_usd';
        $spent = floatval(get_transient($k) ?: 0.0);
        $spent += $delta;
        set_transient($k, $spent, DAY_IN_SECONDS);
    }
}
