<?php
namespace Hexa\PluginCore\Sitemaps;
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;

final class SitemapModule implements ModuleInterface {
    private static bool $registered=false;
    public function register(): void {
        if (self::$registered) return;
        self::$registered=true;
        add_filter('rank_math/sitemap/entry',[RecommendationRegistry::class,'entry'],10,3);
        add_action('rest_api_init',[$this,'routes']);
        AjaxActionRegistry::create(['nonce_action'=>'hexa_sitemaps','action_prefix'=>'hexa_sitemaps'])->register([
            'lookup'=>fn()=>RecommendationRegistry::lookup(sanitize_key((string)($_POST['site_type']??''))),
            'change'=>fn()=>RecommendationRegistry::change(sanitize_key((string)($_POST['id']??'')), '1' === (string)($_POST['on']??'')),
            'conditions'=>fn()=>RecommendationRegistry::save_condition(sanitize_key((string)($_POST['id']??'')),wp_unslash($_POST)),
            'check'=>fn()=>self::check(sanitize_key((string)($_POST['id']??''))),
            'disable_junk'=>fn()=>RecommendationRegistry::disable_junk(),
            'promote'=>fn()=>RecommendationRegistry::promote(sanitize_key((string)($_POST['id']??'')), '1' === (string)($_POST['main']??'')),
        ]);
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('hexa sitemaps',function($args,$assoc){
                $verb=$args[0]??'list';
                $id=(string)($assoc['id']??'');
                if ('list'===$verb) $result=RecommendationRegistry::lookup((string)($assoc['site-type']??''));
                elseif ('check'===$verb) $result=self::check($id);
                elseif ('change'===$verb && isset($assoc['on'])) $result=RecommendationRegistry::change($id,'1'===(string)$assoc['on']);
                elseif ('disable-junk'===$verb) $result=RecommendationRegistry::disable_junk();
                elseif ('promote'===$verb && isset($assoc['main'])) $result=RecommendationRegistry::promote($id,'1'===(string)$assoc['main']);
                elseif ('conditions'===$verb) $result=RecommendationRegistry::save_condition($id,['more_than'=>$assoc['more-than']??2,'min_posts'=>$assoc['min-posts']??1,'apply'=>isset($assoc['apply'])]);
                else \WP_CLI::error('Use list, check --id, change --id --on=0|1, disable-junk, promote --id --main=0|1, or conditions --id.');
                \WP_CLI::line(wp_json_encode($result));
            });
        }
    }
    public function routes(): void {
        $permission=static fn()=>current_user_can('manage_options');
        register_rest_route('hexa-core/v1','/sitemaps',['methods'=>'GET','permission_callback'=>$permission,'callback'=>static fn($r)=>RecommendationRegistry::lookup(sanitize_key((string)$r->get_param('site_type')))]);
        foreach (['change','conditions','check'] as $action) {
            register_rest_route('hexa-core/v1','/sitemaps/'.$action,['methods'=>'POST','permission_callback'=>$permission,'args'=>['id'=>['required'=>true,'type'=>'string']]+('change'===$action?['on'=>['required'=>true,'type'=>'boolean']]:[]),'callback'=>static function($r)use($action){
                $id=sanitize_key((string)$r['id']);
                if ('change'===$action) return RecommendationRegistry::change($id,(bool)$r['on']);
                if ('conditions'===$action) return RecommendationRegistry::save_condition($id,$r->get_params());
                return self::check($id);
            }]);
        }
    }
    public static function check(string $id): array {
        $rows=array_column(RecommendationRegistry::lookup()['rows'],null,'id');
        if (!isset($rows[$id])) return ['ok'=>false,'message'=>'Unknown sitemap.'];
        $row=$rows[$id];
        $result=apply_filters('hexa_plugin_core_sitemap_check',null,$row);
        if (!is_array($result)) {
            $start=microtime(true);
            $response=wp_safe_remote_get($row['url'],['timeout'=>20,'redirection'=>3,'limit_response_size'=>1048576]);
            $code=is_wp_error($response)?0:wp_remote_retrieve_response_code($response);
            $result=['ok'=>200===$code,'response'=>['code'=>$code,'error'=>is_wp_error($response)?$response->get_error_message():'','time_ms'=>(int)((microtime(true)-$start)*1000)],'checked_at'=>gmdate('c')];
        }
        $all=(array)get_option('hexa_plugin_core_sitemap_checks',[]);
        $all[$id]=$result;
        update_option('hexa_plugin_core_sitemap_checks',$all,false);
        return ['id'=>$id,'url'=>$row['url'],'result'=>$result];
    }
}
