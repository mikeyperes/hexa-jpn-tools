<?php
// Usage: php tests/pdf-print-test.php /path/to/host/vendor/autoload.php
require $argv[1];
require dirname(__DIR__) . '/src/PdfPrint/PageSize.php';
require dirname(__DIR__) . '/src/PdfPrint/HtmlDocument.php';
use Hexa\PluginCore\PdfPrint\PageSize;
use Hexa\PluginCore\PdfPrint\HtmlDocument;
function verify($ok,$name){if(!$ok){throw new RuntimeException($name);}}
$s=new PageSize(2.25,1.25);verify($s->points()===[0.0,0.0,162.0,90.0],'Physical dimensions');
foreach ([[0,1],[INF,1],[1,NAN],[49,1]] as [$w,$h]){try{new PageSize($w,$h);throw new RuntimeException('Bad size accepted');}catch(InvalidArgumentException $e){}}
$d=new HtmlDocument($s);$html=$d->html(['<b>Example</b>'],'',true);verify(str_contains($html,'size:2.250000in 1.250000in'),'Print CSS');
$pdf=$d->pdf(['<b>Example</b>','Second page']);verify(str_starts_with($pdf,'%PDF-'),'Real PDF');verify((bool)preg_match('~/MediaBox\s*\[0\.000\s+0\.000\s+162\.000\s+90\.000\]~',$pdf),'Exact PDF MediaBox');verify((bool)preg_match('~/Count\s+2\b~',$pdf),'Two pages');
try{$d->html([]);throw new RuntimeException('Empty accepted');}catch(InvalidArgumentException $e){}
echo "PASS: PDF dimensions, bounds, print HTML, and two-page PDF\n";
