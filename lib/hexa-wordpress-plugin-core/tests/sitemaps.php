<?php
// Focused deterministic contract tests; no live settings or requests.
$options=[];$hooks=[];$routes=[];$can=false;$invalidations=0;
define('RANK_MATH_VERSION','test');
function get_option($k,$default=[]){global $options;return $options[$k]??$default;}
function update_option($k,$v,$autoload=null){global $options;$options[$k]=$v;return true;}
function home_url($p){return 'https://example.test'.$p;}
function get_post_types($q,$format){return ['post'=>(object)['labels'=>(object)['name'=>'Posts']]];}
function get_post_type_object($n){return null;}
function get_taxonomies($q,$format){return ['category'=>(object)['labels'=>(object)['name'=>'Categories']],'post_tag'=>(object)['labels'=>(object)['name'=>'Tags']]];}
function get_terms($q){return 'count'===($q['fields']??'')?3:[(object)['count'=>1],(object)['count'=>3],(object)['count'=>5]];}
function is_wp_error($v){return false;}
function apply_filters($n,$v,...$args){global $hooks;return isset($hooks[$n])?$hooks[$n]($v,...$args):$v;}
function add_action(...$args){}
function add_filter(...$args){}
function sanitize_key($s){return $s;}
function register_rest_route($ns,$r,$config){global $routes;$routes[$r]=$config;}
function current_user_can($c){global $can;return $can;}
function wp_json_encode($v){return json_encode($v);}
class WP_Term {public $taxonomy='category';public $count=1;}
class WP_User {public $ID=1;}
function count_user_posts(...$args){return 1;}
$wpdb=new class {public $posts='wp_posts';public function get_col($q){return [1,3,5];}};
require dirname(__DIR__).'/src/CoreContracts/ModuleInterface.php';
require dirname(__DIR__).'/src/WpAdminAjax/AjaxGuard.php';
require dirname(__DIR__).'/src/WpAdminAjax/AjaxActionRegistry.php';
require dirname(__DIR__).'/src/Sitemaps/RecommendationRegistry.php';
require dirname(__DIR__).'/src/Sitemaps/SitemapModule.php';
use Hexa\PluginCore\Sitemaps\RecommendationRegistry as R;
use Hexa\PluginCore\Sitemaps\SitemapModule as M;
$tests=0;
function check($ok,$message){global $tests;++$tests;if (!$ok) throw new RuntimeException($message);}
$options['rank_math_modules']=['sitemap'];
$before=$options;
$result=R::lookup();$rows=array_column($result['rows'],null,'id');
check($rows['taxonomy_category']['recommended'],'Three categories recommended');
check(!$rows['taxonomy_post_tag']['current'],'Rank Math tags default off');
check($rows['post_type_post']['current'],'Rank Math posts default on');
check(!isset($options['rank-math-options-sitemap']),'Lookup never writes Rank Math');
check($options['rank_math_modules']===$before['rank_math_modules'],'Lookup never changes modules');
check(!$rows['news']['available'],'No Pro reported unavailable');
check(R::change('unknown',true)['ok']===false,'Unknown change rejected');
check(R::save_condition('post_type_post',[])['ok']===false,'Content type conditions rejected');
check(R::save_condition('taxonomy_category',['more_than'=>3,'min_posts'=>3,'apply'=>true])['ok'],'Condition saved');
$rows=array_column(R::lookup()['rows'],null,'id');
check(!$rows['taxonomy_category']['recommended'],'Strict more-than boundary');
check($rows['taxonomy_category']['counts']===['would_show'=>2,'total'=>3],'Live threshold count');
check(false===R::entry(['loc'=>'term'],'term',new WP_Term),'Low-count term excluded');
check(is_array(R::entry(['loc'=>'user'],'user',new WP_User)),'Unconfigured authors unchanged');
$hooks[R::FILTER]=function($rows,$type){$rows['post_type_post']['recommended']='publication'===$type;return $rows;};
R::lookup('publication');check(isset($options[R::OPTION]['publication']),'Named site-type snapshot saved');
(new M)->routes();
foreach($routes as $route){check(false===$route['permission_callback'](),'Anonymous route denied');}
$can=true;foreach($routes as $route){check(true===$route['permission_callback'](),'Admin route allowed');}
check(isset($routes['/sitemaps/change']['args']['on']['required']),'Explicit desired state required');
echo $tests." sitemap contract assertions passed\n";
