<?php
namespace LuxAI;

class Audit {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }

    public function run_scheduled_audit(){ $rep = $this->run(); \LuxAI\Utils\Logger::info('audit','cron','Scheduled audit with '.count($rep['issues']).' issues'); }
    public function rest_run(){ return $this->run(); }

    public function run(){
        $opts = Plugin::instance()->opts; $issues = [];
        include_once ABSPATH . 'wp-admin/includes/update.php';
        wp_version_check();
        $updates = [
            'core'=> get_site_transient('update_core'),
            'plugins'=> get_site_transient('update_plugins'),
            'themes'=> get_site_transient('update_themes')
        ];
        if (!empty($updates['core']->updates))   $issues[]=['type'=>'update','severity'=>'medium','message'=>'Core updates available'];
        if (!empty($updates['plugins']->response)) $issues[]=['type'=>'update','severity'=>'low','message'=>count($updates['plugins']->response).' plugin updates available'];
        if (!empty($updates['themes']->response))  $issues[]=['type'=>'update','severity'=>'low','message'=>count($updates['themes']->response).' theme updates available'];

        if (!empty($opts['audit']['seo'])){
            $q = new \WP_Query(['post_type'=>['post','page'],'post_status'=>'publish','posts_per_page'=>20,'orderby'=>'date','order'=>'DESC']);
            while($q->have_posts()){ $q->the_post(); $title=get_the_title(); if(strlen($title)<20) $issues[]=['type'=>'seo','severity'=>'low','post_id'=>get_the_ID(),'message'=>'Short title (aim 20–60 chars)']; }
            wp_reset_postdata();
            if (false !== stripos(get_bloginfo('name'),'wordpress')) $issues[]=['type'=>'seo','severity'=>'low','message'=>'Site name appears generic; improve branding'];
        }

        if (!empty($opts['audit']['accessibility'])){
            $q = new \WP_Query(['post_type'=>['post','page'],'post_status'=>'publish','posts_per_page'=>10,'orderby'=>'date','order'=>'DESC']);
            while($q->have_posts()){ $q->the_post(); $c=get_the_content(); preg_match_all('/<img [^>]*>/i',$c,$m); foreach($m[0] as $img){ if(!preg_match('/alt\s*=\s*\"[^\"]+\"/i',$img)) $issues[]=['type'=>'a11y','severity'=>'medium','post_id'=>get_the_ID(),'message'=>'Image missing alt text']; } }
            wp_reset_postdata();
        }

        if (!empty($opts['audit']['performance'])){
            global $wpdb; $autoload=(int)$wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'");
            if ($autoload>1000000) $issues[]=['type'=>'perf','severity'=>'high','message'=>'High autoloaded options size (' . size_format($autoload) . ')'];
            $cron=_get_cron_array(); if(is_array($cron) && count($cron)>50) $issues[]=['type'=>'perf','severity'=>'low','message'=>'Large number of scheduled cron events'];
        }

        if (!empty($opts['audit']['database'])){
            global $wpdb; $orph=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL");
            if ($orph>0) $issues[]=['type'=>'db','severity'=>'medium','message'=>"Orphaned postmeta rows: {$orph}"];
            $t_orph=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id=tm.term_id WHERE t.term_id IS NULL");
            if ($t_orph>0) $issues[]=['type'=>'db','severity'=>'low','message'=>"Orphaned termmeta rows: {$t_orph}"];
        }

        if (!empty($opts['audit']['crawler'])){
            $crawl_summary = Crawler::instance()->peek_summary();
            if ($crawl_summary && $crawl_summary['broken']>0){
                $issues[]=['type'=>'crawler','severity'=>'medium','message'=>"Broken links detected: {$crawl_summary['broken']}"];
            }
        }

        $plan = Fixer::instance()->propose_plan($issues);
        return ['issues'=>$issues,'plan'=>$plan];
    }

    public function site_health_test(){ $sum=$this->run(); $sev=empty($sum['issues'])?'good':'recommended'; return ['status'=>$sev,'label'=>__('LuxAI audit status','luxai'),'description'=>sprintf(__('Found %d issue(s). Review in Lux AI → Audits & Fixes.','luxai'), count($sum['issues']))]; }
}
