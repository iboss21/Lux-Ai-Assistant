<?php
namespace LuxAI\Utils;
class Logger {
    public static function log($level,$context,$message){
        global $wpdb; $table=$wpdb->prefix . \LUXAI_LOG_TABLE;
        $wpdb->insert($table,[ 'created_at'=>current_time('mysql'),'level'=>sanitize_text_field($level),'actor'=>wp_get_current_user()->user_login?:'system','context'=>sanitize_text_field($context),'message'=>$message ]);
    }
    public static function info($ctx,$msg){ self::log('info',$ctx,$msg); }
    public static function warn($ctx,$msg){ self::log('warn',$ctx,$msg); }
    public static function error($ctx,$msg){ self::log('error',$ctx,$msg); }
}
