<?php
namespace Hexa\PluginCore\Sitemaps;

/** Shared recommendation engine. Recommendations ignore Rank Math's noindex; reading never changes Rank Math. */
final class RecommendationRegistry {
    public const FILTER = 'hexa_plugin_core_sitemap_recommendations';
    public const OPTION = 'hexa_plugin_core_sitemap_recommendations';
    public const EXCLUDED_POST_TYPES_FILTER = 'hexa_plugin_core_sitemap_excluded_post_types';
    public const SYSTEM_PREFIXES_FILTER = 'hexa_plugin_core_sitemap_system_post_type_prefixes';
    public const EXCLUDED_POST_TYPES = ['elementor_library','e-landing-page','e-floating-buttons','elementor_font','elementor_icons','elementor_snippet','wp_block','wp_template','wp_template_part','wp_navigation','wp_global_styles','custom_css','customize_changeset','revision','nav_menu_item','user_request','oembed_cache','acf-field','acf-field-group','jet-engine','jet-smart-filters','jet-theme-core','jet-popup','jet-form-builder','et_pb_layout','fusion_template','fusion_element','attachment','product_variation','shop_order','shop_coupon','wpcf7_contact_form','wpforms','mc4wp-form','popup','elementor-hf','ct_template','fl-builder-template','astra-advanced-hook','ae_global_templates','oceanwp_library','tablepress_table','ocean_portfolio'];
    /** Taxonomies whose sitemaps are almost always junk (builder, theme and shop internals). */
    public const JUNK_TAXONOMIES = ['post_format','elementor_library_type','elementor_library_category','elementor_font_type','product_shipping_class','product_visibility','product_type','nav_menu','link_category','wp_theme','wp_template_part_area','wp_pattern_category','wp_block_category'];
    public const JUNK_TAXONOMIES_FILTER = 'hexa_plugin_core_sitemap_junk_taxonomies';
    /** Junk rows an administrator moved back to the main list. */
    public const PROMOTED = 'hexa_plugin_core_sitemap_promoted';
    public const SYSTEM_PREFIXES = ['elementor_','e-','et_pb_'];
    public const CONDITIONS = 'hexa_plugin_core_sitemap_conditions';

    /** Providers extend these declared lists; post-provider policy cannot recommend helper types on. */
    public static function excluded_post_types(): array {
        return array_values(array_unique(array_map('strval',(array)apply_filters(self::EXCLUDED_POST_TYPES_FILTER,self::EXCLUDED_POST_TYPES))));
    }
    public static function is_system_type(string $name): bool {
        if (in_array($name,self::excluded_post_types(),true)) return true;
        foreach ((array)apply_filters(self::SYSTEM_PREFIXES_FILTER,self::SYSTEM_PREFIXES) as $prefix) {
            if (is_string($prefix) && ''!==$prefix && str_starts_with($name,$prefix)) return true;
        }
        return false;
    }
    public static function is_junk_taxonomy(string $name): bool {
        if (in_array($name,array_map('strval',(array)apply_filters(self::JUNK_TAXONOMIES_FILTER,self::JUNK_TAXONOMIES)),true)) return true;
        foreach ((array)apply_filters(self::SYSTEM_PREFIXES_FILTER,self::SYSTEM_PREFIXES) as $prefix) {
            if (is_string($prefix) && ''!==$prefix && str_starts_with($name,$prefix)) return true;
        }
        return false;
    }
    /** A row is junk when its type is on the hardcoded lists, unless an administrator moved it back. */
    public static function is_junk_type(array $row): bool {
        $name=(string)($row['name']??'');
        return ('post_type'===($row['kind']??'') && self::is_system_type($name)) || ('taxonomy'===($row['kind']??'') && self::is_junk_taxonomy($name));
    }
    public static function is_promoted(string $id): bool {
        return in_array($id,(array)get_option(self::PROMOTED,[]),true);
    }
    public static function apply_policy(array $rows): array {
        $content_urls=[];
        foreach($rows as $id=>$row) if('post_type'===($row['kind']??'') && !empty($row['url'])) $content_urls[rtrim($row['url'],'/')]=['id'=>$id,'name'=>$row['name']??$id];
        foreach($rows as $id=>&$row){
            $row['policy_warnings']=[];
            unset($row['collision_with']);
            if('post_type'===($row['kind']??'') && self::is_system_type((string)($row['name']??''))){
                $row['recommended']=false;
                $row['recommended_by']=['Hexa WP Core (system type exclusion)'];
                $row['policy_warnings'][]='Builder or system helper content type is excluded from recommendations.';
            }
            if('taxonomy'===($row['kind']??'') && self::is_junk_taxonomy((string)($row['name']??''))){
                $row['recommended']=false;
                $row['recommended_by']=['Hexa WP Core (junk list)'];
            }
            $row['promoted']=self::is_junk_type($row) && self::is_promoted((string)$id);
            $row['junk']=self::is_junk_type($row) && !$row['promoted'];
            $winner=$content_urls[rtrim((string)($row['url']??''),'/')]??null;
            if('taxonomy'===($row['kind']??'') && $winner){
                $row['recommended']=false;
                $row['collision_with']=$winner['id'];
                $row['policy_warnings'][]='Sitemap address collision: taxonomy "'.($row['name']??$id).'" shares '.$row['url'].' with content type "'.$winner['name'].'". The content type wins; this taxonomy must remain off.';
            }
        }
        unset($row);
        return $rows;
    }

    public static function condition(string $key): array {
        $all = (array) get_option(self::CONDITIONS, []);
        return array_merge(['more_than' => 2, 'min_posts' => 1, 'apply' => false], (array) ($all[$key] ?? []));
    }

    /** Build and persist the complete, site-type-specific snapshot in one lookup. */
    public static function lookup(string $site_type = ''): array {
        // The site's own type when none is asked for; a plugin that records site types supplies it.
        if ('' === $site_type) $site_type = (string) apply_filters('hexa_plugin_core_site_type', '');
        $settings = (array) get_option('rank-math-options-sitemap', []);
        $modules = (array) get_option('rank_math_modules', []);
        $active = defined('RANK_MATH_VERSION');
        $enabled = $active && in_array('sitemap', $modules, true);
        $rows = [];
        $add = static function(string $id, string $label, string $kind, string $name, string $setting, string $path, bool $default) use (&$rows, $settings, $enabled) {
            $rows[$id] = ['id'=>$id,'label'=>$label,'kind'=>$kind,'name'=>$name,'url'=>home_url($path), 'setting'=>$setting,'recommended'=>$default,'recommended_by'=>['Site content'], 'current'=>$enabled && 'on' === ($settings[$setting] ?? ('attachment' === $name || ('taxonomy' === $kind && 'category' !== $name) ? 'off':'on')), 'available'=>true,'warnings'=>[]];
        };
        $add('index','Sitemap index','index','','','/sitemap_index.xml',true);
        $rows['index']['current']=$enabled;
        $types = get_post_types(['public'=>true], 'objects');
        foreach (['attachment','elementor_library','e-landing-page','e-floating-buttons'] as $name) {
            if ($object=get_post_type_object($name)) $types[$name]=$object;
        }
        foreach ($types as $name=>$object) {
            $add('post_type_'.$name, $object->labels->name, 'post_type', $name, 'pt_'.$name.'_sitemap', '/'.$name.'-sitemap.xml', 'attachment' !== $name && !self::is_system_type($name));
        }
        $taxonomies=get_taxonomies(['public'=>true], 'objects');
        foreach (self::JUNK_TAXONOMIES as $name) {
            if (($object=get_taxonomy($name)) && isset($settings['tax_'.$name.'_sitemap'])) $taxonomies[$name]=$object;
        }
        foreach ($taxonomies as $name=>$object) {
            $id='taxonomy_'.$name;
            $add($id,$object->labels->name,'taxonomy',$name,'tax_'.$name.'_sitemap','/'.$name.'-sitemap.xml',!in_array($name,['post_format','product_tag'],true));
            $condition=self::condition($id);
            $counts=get_terms(['taxonomy'=>$name,'hide_empty'=>false,'fields'=>'count']);
            $total=is_wp_error($counts)?0:(int)$counts;
            $terms=get_terms(['taxonomy'=>$name,'hide_empty'=>false]);
            $shown=0;
            if (!is_wp_error($terms)) foreach ($terms as $term) if ($term->count >= $condition['min_posts']) ++$shown;
            $rows[$id]['condition']=$condition;
            $rows[$id]['counts']=['would_show'=>$shown,'total'=>$total];
            $rows[$id]['recommended']=$rows[$id]['recommended'] && $total > $condition['more_than'];
        }
        $add('authors','Authors','author','','authors_sitemap','/author-sitemap.xml',true);
        $condition=self::condition('authors');
        global $wpdb;
        $counts=$wpdb->get_col("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' GROUP BY post_author");
        $rows['authors']['condition']=$condition;
        $rows['authors']['counts']=['would_show'=>count(array_filter($counts, static fn($n)=>(int)$n >= $condition['min_posts'])),'total'=>count($counts)];
        foreach (['news'=>false,'video'=>true,'local'=>false] as $name=>$on) {
            $module='local'===$name?'local-seo':$name.'-sitemap';
            $add($name,ucfirst($name).' sitemap','module',$module,'','/'.$name.'-sitemap.xml',$on);
            $rows[$name]['current']=$active && in_array($module,$modules,true);
            $rows[$name]['available']=defined('RANK_MATH_PRO_VERSION');
        }
        $rows['local']['url']=home_url('/locations.kml');
        $rows=self::apply_policy(SitemapProviders::apply((array) apply_filters(self::FILTER,$rows,$site_type),$site_type));
        foreach ($rows as $id=>&$row) {
            $row['id']=$id;
            $row['warnings']=$row['policy_warnings']??[];
            $checks=(array)get_option('hexa_plugin_core_sitemap_checks',[]);
            $row['live_check']=$checks[$id]??null;
            $row['hidden_from_google']=self::is_hidden_from_google($row);
            if (!empty($row['recommended']) && $row['hidden_from_google']) $row['warnings'][]='Its pages are hidden from Google, so Rank Math leaves them out of the sitemap; turning it on here makes them visible.';
            if (!empty($row['recommended']) && isset($checks[$id]['response']['code']) && 200 !== $checks[$id]['response']['code']) $row['warnings'][]='Required sitemap did not answer HTTP 200.';
            if (!empty($row['recommended']) && empty($row['current'])) $row['warnings'][]='Required sitemap is off in Rank Math.';
            if (empty($row['recommended']) && !empty($row['current'])) $row['warnings'][]='A sitemap that is not recommended is on.';
            if (!empty($row['recommended']) && empty($row['available'])) $row['warnings'][]='Required sitemap provider is unavailable.';
        }
        unset($row);
        $result=['site'=>home_url('/'),'site_type'=>$site_type,'rank_math_active'=>$active,'rows'=>array_values($rows),'generated_at'=>gmdate('c')];
        $stored=(array)get_option(self::OPTION,[]);
        $stored[$site_type ?: 'current']=$result;
        update_option(self::OPTION,$stored,false);
        return $result;
    }

    /** Exact explicit administrator action only; never called from recommendations. */
    public static function change(string $id, bool $on): array {
        if (!defined('RANK_MATH_VERSION')) return ['ok'=>false,'message'=>'Rank Math is inactive.'];
        $rows=array_column(self::lookup()['rows'],null,'id');
        if (!isset($rows[$id])) return ['ok'=>false,'message'=>'Unknown sitemap.'];
        $row=$rows[$id];
        if ($on && !empty($row['collision_with'])) return ['ok'=>false,'id'=>$id,'message'=>'Cannot enable this sitemap: '.$row['policy_warnings'][0]];
        if ('module'===$row['kind'] || 'index'===$row['kind']) {
            if ($on && empty($row['available'])) return ['ok'=>false,'message'=>'Rank Math Pro is required.'];
            $module='index'===$row['kind']?'sitemap':$row['name'];
            $before=(array)get_option('rank_math_modules',[]);
            $after=array_values(array_diff($before,[$module]));
            if ($on) $after[]=$module;
            update_option('rank_math_modules',$after);
        } else {
            if (empty($row['setting'])) return ['ok'=>false,'message'=>'This provider has no writable Rank Math setting.'];
            $before=(array)get_option('rank-math-options-sitemap',[]);
            $after=$before;
            $after[$row['setting']]=$on?'on':'off';
            update_option('rank-math-options-sitemap',$after);
        }
        // Rank Math drops hidden (noindex) pages from their sitemap, so a sitemap turned on also makes its pages visible.
        $shown=$on && !empty($row['hidden_from_google']) && self::show_to_google($row);
        self::clear_cache();
        return ['ok'=>true,'id'=>$id,'on'=>$on,'made_visible'=>$shown,'message'=>'Rank Math updated'.($shown?', its pages made visible to Google,':'').' and sitemap cache cleared.'];
    }
    /** Rank Math's Titles & Meta robots key for a row, or '' when the row has none. */
    public static function robots_key(array $row): string {
        $kind=(string)($row['kind']??'');
        if ('post_type'===$kind) return 'pt_'.$row['name'].'_robots';
        if ('taxonomy'===$kind) return 'tax_'.$row['name'].'_robots';
        if ('author'===$kind) return 'author_robots';
        return '';
    }
    /** The robots values Rank Math applies: the type's own when it has custom robots, else the site-wide ones. */
    public static function effective_robots(array $row): array {
        $key=self::robots_key($row);
        if (''===$key) return [];
        $titles=(array)get_option('rank-math-options-titles',[]);
        $custom='on'===($titles[substr($key,0,-6).'custom_robots']??'');
        return array_values((array)($custom?($titles[$key]??[]):($titles['robots_global']??[])));
    }
    public static function is_hidden_from_google(array $row): bool {
        return in_array('noindex',self::effective_robots($row),true);
    }
    /** Set the type's own robots to index, keeping every other robots value. */
    private static function show_to_google(array $row): bool {
        $key=self::robots_key($row);
        if (''===$key) return false;
        $titles=(array)get_option('rank-math-options-titles',[]);
        $robots=array_values(array_diff(self::effective_robots($row),['noindex','index']));
        array_unshift($robots,'index');
        $titles[$key]=$robots;
        $titles[substr($key,0,-6).'custom_robots']='on';
        update_option('rank-math-options-titles',$titles);
        return true;
    }
    /** Turn off, in one action, every junk sitemap that is on. */
    public static function disable_junk(): array {
        if (!defined('RANK_MATH_VERSION')) return ['ok'=>false,'message'=>'Rank Math is inactive.'];
        $off=[];
        foreach (self::lookup()['rows'] as $row) {
            if (empty($row['junk']) || empty($row['current']) || empty($row['setting'])) continue;
            $settings=(array)get_option('rank-math-options-sitemap',[]);
            $settings[$row['setting']]='off';
            update_option('rank-math-options-sitemap',$settings);
            $off[]=$row['id'];
        }
        self::clear_cache();
        return ['ok'=>true,'turned_off'=>$off,'message'=>$off?'Turned off '.count($off).' hidden sitemaps in Rank Math.':'No hidden sitemap was on.'];
    }
    /** Move a junk row to the main list (promote) or back to the hidden list. Changes nothing in Rank Math. */
    public static function promote(string $id,bool $main): array {
        $ids=array_values(array_diff((array)get_option(self::PROMOTED,[]),[$id]));
        if ($main) $ids[]=$id;
        update_option(self::PROMOTED,$ids,false);
        return ['ok'=>true,'id'=>$id,'main'=>$main,'message'=>$main?'Moved to the main list.':'Moved to the hidden list.'];
    }
    public static function clear_cache(): void {
        if (class_exists('RankMath\\Sitemap\\Cache')) \RankMath\Sitemap\Cache::invalidate_storage();
    }
    public static function save_condition(string $id,array $input): array {
        $rows=array_column(self::lookup()['rows'],null,'id');
        if (!isset($rows[$id]) || !in_array($rows[$id]['kind'],['taxonomy','author'],true)) return ['ok'=>false,'message'=>'Conditions require a taxonomy or author sitemap.'];
        $all=(array)get_option(self::CONDITIONS,[]);
        $all[$id]=['more_than'=>max(0,(int)($input['more_than']??2)),'min_posts'=>max(0,(int)($input['min_posts']??1)),'apply'=>!empty($input['apply'])];
        update_option(self::CONDITIONS,$all,false);
        self::clear_cache();
        return ['ok'=>true,'condition'=>$all[$id]];
    }
    /** Only explicitly enabled conditions affect visitor output. */
    public static function entry($url,string $type,$object) {
        if ('term'===$type && $object instanceof \WP_Term) {
            $c=self::condition('taxonomy_'.$object->taxonomy);
            if ($c['apply'] && $object->count < $c['min_posts']) return false;
        }
        if ('user'===$type && $object instanceof \WP_User) {
            $c=self::condition('authors');
            if ($c['apply'] && count_user_posts($object->ID,'post',true) < $c['min_posts']) return false;
        }
        return $url;
    }
}
