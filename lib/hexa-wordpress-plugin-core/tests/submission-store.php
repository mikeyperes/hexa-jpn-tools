<?php
require dirname(__DIR__).'/src/FrontendForms/SubmissionStore.php';
use Hexa\PluginCore\FrontendForms\SubmissionStore as S;
function apply_filters($name,$value,...$args){return $value;}
function sanitize_text_field($s){return strip_tags($s);}
function esc_url_raw($s,$protocols=[]){return preg_match('~^https?://~',$s)?$s:'';}
$n=0;
function check($ok,$name){global $n;if(!$ok)throw new RuntimeException($name);$n++;echo "PASS $name\n";}
check(S::email(' A@EXAMPLE.COM ')==='a@example.com','email normalization');
foreach(['weekly','a_b-2'] as $list)check(S::collection($list)===$list,'valid list');
foreach(['','A','../bad',str_repeat('a',81)] as $list){try{S::collection($list);throw new RuntimeException('accepted invalid list');}catch(InvalidArgumentException $e){check(true,'invalid list');}}
check(S::client_ip(['REMOTE_ADDR'=>'173.245.48.1','HTTP_CF_CONNECTING_IP'=>'2001:db8::2'])==='2001:db8::2','Cloudflare edge');
check(S::client_ip(['REMOTE_ADDR'=>'192.0.2.1','HTTP_CF_CONNECTING_IP'=>'1.2.3.4'])==='192.0.2.1','spoof rejected');
check(S::client_ip(['REMOTE_ADDR'=>'bad'])==='','invalid IP rejected');
check(S::in_cidr('2606:4700::1','2606:4700::/32'),'IPv6 edge');
check(!S::in_cidr('1.2.3.4','2606:4700::/32'),'mixed family');
$h=S::headers(['HTTP_COOKIE'=>'secret','HTTP_AUTHORIZATION'=>'secret','HTTP_USER_AGENT'=>'agent'],'https://example.com/');
check(count($h)===4&&!str_contains(json_encode($h),'secret'),'header allowlist');
check(S::est('2026-07-01 12:00:00')==='2026-07-01 07:00:00 EST','fixed EST');
foreach(['=X','+X','-X','@X',"\tX",'  =X'] as $v)check(S::csv_cell($v)[0]==="'",'formula escape');
check(S::csv_cell('ordinary')==='ordinary','ordinary cell unchanged');
echo "PASS $n unit assertions\n";
