<?php
namespace LuxAI;


class Fixer {
private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }


public function propose_plan(array $issues){
$id = wp_generate_uuid4(); $actions=[];
foreach($issues as $i){
switch($i['type']){
case 'update': $actions[]=['action'=>'run_updates','safety'=>'safe']; break;
case 'a11y': if(!empty($i['post_id'])) $actions[]=['action'=>'add_missing_alt','post_id'=>$i['post_id'],'safety'=>'review']; break;
case 'db': $actions[]=['action'=>'cleanup_orphan_postmeta','safety'=>'safe']; break;
case 'perf': $actions[]=['action'=>'analyze_autoload_bloat','safety'=>'review']; break;
}
}
set_transient('luxai_plan_'.$id,$actions,HOUR_IN_SECONDS);
return ['id'=>$id,'actions'=>$actions];
}


public function rest_apply(\WP_REST_Request $req){
$plan_id = sanitize_text_field($req->get_param('plan_id'));
$actions = get_transient('luxai_plan_'.$plan_id);
if(!$actions) return new \WP_Error('luxai_plan_missing','Plan expired or not found',['status'=>404]);
$opts = Plugin::instance()->opts; $dry=!empty($opts['safety']['dry_run']); $res=[];
foreach($actions as $a){ $m='do_'.$a['action']; if(method_exists($this,$m)) $res[]=['action'=>$a,'result'=>$dry?'DRY‑RUN':call_user_func([$this,$m],$a)]; }
return ['applied'=>!$dry,'results'=>$res];
}


private function do_run_updates(){ if(!current_user_can('update_plugins')) return 'Insufficient permissions'; include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php'; wp_version_check(); wp_update_plugins(); wp_update_themes(); return 'Updates triggered (monitor Updates screen).'; }


private function do_cleanup_orphan_postmeta(){ global $wpdb; $cnt=$wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL"); return "Deleted {$cnt} orphaned postmeta rows."; }


private function do_add_missing_alt($a){ $post=get_post(intval($a['post_id'])); if(!$post) return 'Post not found'; $c=$post->post_content; $c2=preg_replace_callback('/<img ([^>]*?)>/i',function($m){$img=$m[0]; if(preg_match('/alt\s*=\s*"[^"]+"/i',$img)) return $img; return preg_replace('/<img /i','<img alt="Image" ',$img,1);},$c); if($c2!==$c){ wp_update_post(['ID'=>$post->ID,'post_content'=>$c2]); return 'Added fallback alt attributes.';} return 'No changes'; }


private function do_analyze_autoload_bloat(){ global $wpdb; return $wpdb->get_results("SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload='yes' ORDER BY bytes DESC LIMIT 20", ARRAY_A); }
}
