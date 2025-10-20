<?php
namespace LuxAI;


class Plugin {
private static $instance; public $opts;
public static function instance(){ return self::$instance ?? (self::$instance = new self()); }
private function __construct(){ $this->opts = get_option(\LUXAI_OPTION_KEY, []); }


public function cap_manage(){ return $this->opts['permissions']['manage_cap'] ?? 'manage_options'; }
public function cap_use(){ return $this->opts['permissions']['use_assistant_cap'] ?? 'edit_posts'; }


public function register_rest(){
register_rest_route('luxai/v1','/chat',[
'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_use());},
'callback'=>[Chat::instance(),'rest_chat'],
'args'=>[
'message'=>['type'=>'string','required'=>true],
'provider'=>['type'=>'string','required'=>false,'enum'=>['openai','anthropic','auto']],
'context'=>['type'=>'object','required'=>false],
]
]);
register_rest_route('luxai/v1','/audit/run',[
'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_manage());},
'callback'=>[Audit::instance(),'rest_run']
]);
register_rest_route('luxai/v1','/fix/apply',[
'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_manage());},
'callback'=>[Fixer::instance(),'rest_apply'],
'args'=>['plan_id'=>['type'=>'string','required'=>true]]
]);
}


public function register_site_health($tests){
$tests['direct']['luxai_audit_summary'] = [
'label'=>__('LuxAI Audit Summary','luxai'),
'test'=>[Audit::instance(),'site_health_test']
];
return $tests;
}
}
