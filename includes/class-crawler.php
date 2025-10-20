<?php
namespace LuxAI;

class Crawler {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }

    public function rest_run(\WP_REST_Request $req){ $limit=intval($req->get_param('limit')?:200); return $this->run(['limit'=>$limit]); }
    public function run_scheduled_crawl(){ $this->run(['limit'=>300]); }

    public function run($args=[]){
        $limit = max(50, intval($args['limit'] ?? 200));
        $checked=0; $broken=0; $samples=[];
        $urls = [ home_url('/'), home_url('/sitemap.xml') ];
        $q = new \WP_Query(['post_type'=>['post','page'],'post_status'=>'publish','posts_per_page'=>50,'orderby'=>'date','order'=>'DESC']);
        while($q->have_posts()){ $q->the_post(); $c=get_the_content(); preg_match_all('/href=\"([^\"]+)\"/i',$c,$m); foreach($m[1] as $u){ $urls[] = esc_url_raw($u); } }
        wp_reset_postdata();
        $urls = array_values(array_unique(array_filter($urls)));

        foreach($urls as $u){
            if ($checked >= $limit) break;
            $checked++;
            $resp = wp_remote_head($u, ['timeout'=>10,'redirection'=>3]);
            if (is_wp_error($resp)) { $broken++; if(count($samples)<10) $samples[]=['url'=>$u,'error'=>$resp->get_error_message()]; continue; }
            $code = intval(wp_remote_retrieve_response_code($resp));
            if ($code>=400){ $broken++; if(count($samples)<10) $samples[]=['url'=>$u,'status'=>$code]; }
        }
        $summary = ['checked'=>$checked,'broken'=>$broken,'samples'=>$samples,'ts'=>current_time('mysql')];
        set_transient('luxai_crawl_summary',$summary, DAY_IN_SECONDS);
        \LuxAI\Utils\Logger::info('crawler','run', 'checked='.$checked.' broken='.$broken);
        return $summary;
    }

    public function peek_summary(){ return get_transient('luxai_crawl_summary'); }
}
