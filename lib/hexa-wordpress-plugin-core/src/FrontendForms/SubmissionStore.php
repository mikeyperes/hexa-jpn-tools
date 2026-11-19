<?php
namespace Hexa\PluginCore\FrontendForms;

/** Host-neutral collection storage. Unique collection/email index is the concurrency boundary. */
final class SubmissionStore {
    public function table(): string { global $wpdb; return $wpdb->prefix . 'hexa_form_submissions'; }
    public function deletions_table(): string {global $wpdb;return $wpdb->prefix . 'hexa_form_deletions';}
    public function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$this->table()} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            collection varchar(80) NOT NULL,
            email varchar(254) NOT NULL,
            name varchar(200) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            ip varchar(45) NOT NULL DEFAULT '',
            headers text NOT NULL,
            source varchar(120) NOT NULL DEFAULT '',
            consent tinyint NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY collection_email (collection,email),
            KEY email (email),
            KEY collection_id (collection,id)
        ) $collate;");
        dbDelta("CREATE TABLE {$this->deletions_table()} (
            deletion_id bigint unsigned NOT NULL AUTO_INCREMENT,
            id bigint unsigned NOT NULL,
            collection varchar(80) NOT NULL,
            deleted_at datetime NOT NULL,
            PRIMARY KEY  (deletion_id),
            KEY collection (collection)
        ) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE {$wpdb->prefix}hexa_form_rates (
            rate_key varchar(64) NOT NULL,
            attempts int unsigned NOT NULL DEFAULT 0,
            expires bigint unsigned NOT NULL,
            PRIMARY KEY  (rate_key),
            KEY expires (expires)
        ) $collate;");
    }
    public static function email(string $email): string { return strtolower(trim($email)); }
    public static function collection(string $list): string {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/D', $list)) throw new \InvalidArgumentException('Invalid list.');
        return $list;
    }
    public static function in_cidr(string $ip, string $cidr): bool {
        [$net,$bits] = explode('/', $cidr); $a=@inet_pton($ip); $b=@inet_pton($net);
        if ($a===false || $b===false || strlen($a)!==strlen($b)) return false;
        $bytes=intdiv((int)$bits,8); $rest=(int)$bits%8;
        return substr($a,0,$bytes)===substr($b,0,$bytes) && (!$rest || ((ord($a[$bytes]) ^ ord($b[$bytes])) & (255 << (8-$rest)))===0);
    }
    public static function client_ip(array $server): string {
        $peer=(string)($server['REMOTE_ADDR']??'');
        if (!filter_var($peer,FILTER_VALIDATE_IP)) return '';
        // Only Cloudflare edge peers may supply CF-Connecting-IP. Never trust arbitrary XFF.
        $ranges=['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];
        foreach ($ranges as $range) if (self::in_cidr($peer,$range) && filter_var($server['HTTP_CF_CONNECTING_IP']??'',FILTER_VALIDATE_IP)) return $server['HTTP_CF_CONNECTING_IP'];
        // Explicitly configured trusted reverse proxies can supply the already-resolved client IP.
        $resolved=apply_filters('hexa_form_submission_client_ip',$peer,$server);
        return filter_var($resolved,FILTER_VALIDATE_IP)?$resolved:$peer;
    }
    public static function headers(array $server, string $page=''): array {
        $out=[];
        foreach (['HTTP_USER_AGENT'=>'user_agent','HTTP_REFERER'=>'referer','HTTP_ACCEPT_LANGUAGE'=>'accept_language'] as $key=>$name) $out[$name]=substr(sanitize_text_field((string)($server[$key]??'')),0,2048);
        $out['page_url']=substr(esc_url_raw($page,['http','https']),0,2048);
        return $out;
    }
    /** Atomic, shared across collections: all attempts count, including invalid ones. */
    public function allow(string $ip, int $limit=10, int $seconds=600): bool {
        global $wpdb; $now=time(); $key=hash('sha256',$ip.':'.intdiv($now,$seconds)); $table=$wpdb->prefix.'hexa_form_rates';
        $ok=$wpdb->query($wpdb->prepare("INSERT INTO $table (rate_key,attempts,expires) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE attempts=attempts+1",$key,$now+$seconds*2));
        if ($ok===false) return false;
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires < %d LIMIT 100",$now));
        return (int)$wpdb->get_var($wpdb->prepare("SELECT attempts FROM $table WHERE rate_key=%s",$key)) <= $limit;
    }
    public function add(string $list, string $email, array $data=[]): array {
        global $wpdb; $list=self::collection($list); $email=self::email($email);
        if (strlen($email)>254 || !is_email($email)) return ['status'=>'invalid'];
        $row=['collection'=>$list,'email'=>$email,'name'=>substr(sanitize_text_field((string)($data['name']??'')),0,200),'created_at'=>gmdate('Y-m-d H:i:s'),'ip'=>self::client_ip($data['server']??[]),'headers'=>wp_json_encode(self::headers($data['server']??[],(string)($data['page_url']??''))),'source'=>substr(sanitize_text_field((string)($data['source']??'')),0,120),'consent'=>empty($data['consent'])?0:1];
        // Avoid INSERT IGNORE: other data failures must not be reported as duplicates.
        $prior=$wpdb->suppress_errors(true); $ok=$wpdb->insert($this->table(),$row); $wpdb->suppress_errors($prior);
        if ($ok===false) {
            $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE collection=%s AND email=%s",$list,$email));
            return ['status'=>$exists?'duplicate':'error'];
        }
        return ['status'=>'created','id'=>(int)$wpdb->insert_id];
    }
    /** Hosts must authorize the exact list and row before calling. */
    public function delete(string $list, int $id): bool {
        return $id>0 && $this->remove(['collection'=>self::collection($list),'id'=>$id])===1;
    }
    public function total(string $list): int {global $wpdb; return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table()} WHERE collection=%s",self::collection($list)));}
    public function recent(string $list, int $limit=20, int $before=0): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE collection=%s AND id < %d ORDER BY id DESC LIMIT %d",self::collection($list),$before?:PHP_INT_MAX,max(1,min(500,$limit))),ARRAY_A)?:[];
    }
    /** Caller owns authorization. Ascending snapshot-bounded scan, safe across insertions and deletions. */
    public function scan(int $after=0,int $limit=100,?string $collection=null,?int $upper=null): array {
        global $wpdb;$table=$this->table();$after=max(0,$after);$limit=max(1,min(500,$limit));
        $scope=$collection!==null?$wpdb->prepare(' AND collection=%s',self::collection($collection)):'';
        if($upper===null){$upper=(int)$wpdb->get_var("SELECT MAX(id) FROM $table WHERE 1=1$scope");if($wpdb->last_error!=='')throw new \RuntimeException('Submission scan unavailable.');}
        $upper=max(0,$upper);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE id > %d AND id <= %d$scope ORDER BY id ASC LIMIT %d",$after,$upper,$limit+1),ARRAY_A);
        if($wpdb->last_error!==''||!is_array($rows))throw new \RuntimeException('Submission scan unavailable.');
        $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);
        return ['items'=>$rows,'upper_id'=>$upper,'next_after_id'=>$rows?(int)end($rows)['id']:$after,'has_more'=>$more];
    }
    public static function est(string $utc): string { return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('-05:00'))->format('Y-m-d H:i:s').' EST'; }
    public static function csv_cell($value): string { $s=(string)$value; return preg_match('/^[\s\x00-\x20]*[=+@-]/u',$s) || preg_match('/^[\t\r\n]/',$s) ? "'".$s : $s; }
    /** Caller owns HTTP authorization; this primitive also supports protected CLI exports. */
    public function csv($stream, string $list): void {
        fputcsv($stream,['id','list','email','name','timestamp_utc','timestamp_est','ip','source','consent','headers'],',','"','');
        $before=0;
        do { $rows=$this->recent($list,500,$before); foreach($rows as $r) {
            fputcsv($stream,array_map([self::class,'csv_cell'],[$r['id'],$r['collection'],$r['email'],$r['name'],$r['created_at'],self::est($r['created_at']),$r['ip'],$r['source'],$r['consent'],$r['headers']]),',','"',''); $before=(int)$r['id'];
        }} while(count($rows)===500);
    }
    public function personal_data(string $email,int $page=1): array {
        global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE email=%s ORDER BY id LIMIT 100 OFFSET %d",self::email($email),max(0,$page-1)*100),ARRAY_A)?:[];
    }
    public function erase(string $email): bool {return $this->remove(['email'=>self::email($email)])!==null;}
    /** Atomic removal plus privacy-safe journal. Serialize commits so an ascending cursor never misses a late commit. */
    private function remove(array $where): ?int {
        global $wpdb;$table=$this->table();$journal=$this->deletions_table();
        $engines=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s) AND ENGINE='InnoDB'",$table,$journal));
        if($engines!==2)return null;
        $lock='hexa_delete_'.substr(hash('sha256',DB_NAME.':'.$table),0,40);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lock))!==1)return null;
        $started=false;
        try {
            if($wpdb->query('START TRANSACTION')===false)throw new \RuntimeException('Transaction unavailable.');$started=true;
            $clauses=[];$values=[];foreach($where as $key=>$value){$clauses[]=$key.'=%s';$values[]=$value;}
            $rows=$wpdb->get_results($wpdb->prepare("SELECT id,collection FROM $table WHERE ".implode(' AND ',$clauses).' FOR UPDATE',$values),ARRAY_A);
            if($wpdb->last_error!==''||!is_array($rows))throw new \RuntimeException('Delete lookup failed.');
            foreach($rows as $row){
                if($wpdb->insert($journal,['id'=>$row['id'],'collection'=>$row['collection'],'deleted_at'=>gmdate('Y-m-d H:i:s')])!==1)throw new \RuntimeException('Deletion journal failed.');
                if($wpdb->delete($table,['id'=>$row['id'],'collection'=>$row['collection']])!==1)throw new \RuntimeException('Delete failed.');
            }
            if($wpdb->query('COMMIT')===false)throw new \RuntimeException('Commit failed.');$started=false;
            return count($rows);
        }catch(\Throwable $e){if($started)$wpdb->query('ROLLBACK');return null;}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    /** No personal fields survive deletion. Hosts authorize this journal just like live submissions. */
    public function deleted(int $after=0,int $limit=100): array {
        global $wpdb;$after=max(0,$after);$limit=max(1,min(500,$limit));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT deletion_id,id,collection,deleted_at FROM {$this->deletions_table()} WHERE deletion_id > %d ORDER BY deletion_id ASC LIMIT %d",$after,$limit+1),ARRAY_A);
        if($wpdb->last_error!==''||!is_array($rows))throw new \RuntimeException('Deletion scan unavailable.');
        $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);
        return ['items'=>$rows,'next_after_id'=>$rows?(int)end($rows)['deletion_id']:$after,'has_more'=>$more];
    }
    public function lists(): array {
        global $wpdb;$rows=$wpdb->get_results("SELECT known.collection AS list,COUNT(s.id) AS total FROM (SELECT collection FROM {$this->table()} UNION SELECT collection FROM {$this->deletions_table()}) known LEFT JOIN {$this->table()} s ON s.collection=known.collection GROUP BY known.collection ORDER BY known.collection ASC",ARRAY_A);
        if($wpdb->last_error!==''||!is_array($rows))throw new \RuntimeException('List totals unavailable.');
        foreach($rows as &$row)$row['total']=(int)$row['total'];unset($row);return $rows;
    }
}
