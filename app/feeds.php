<?php
declare(strict_types=1);
function radar_categories(): array {
 return [
 'Geral'=>'https://news.google.com/rss?hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Brasil'=>'https://news.google.com/rss/headlines/section/topic/NATION?hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Futebol'=>'https://news.google.com/rss/search?q=futebol&hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Política'=>'https://news.google.com/rss/headlines/section/topic/POLITICS?hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Famosos'=>'https://news.google.com/rss/search?q=celebridades&hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Economia'=>'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Mundo'=>'https://news.google.com/rss/headlines/section/topic/WORLD?hl=pt-BR&gl=BR&ceid=BR:pt-419',
 'Tecnologia'=>'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=pt-BR&gl=BR&ceid=BR:pt-419'
 ];
}
function radar_fetch(string $category='Geral',int $limit=24): array {
 $feeds=radar_categories();
 if (!array_key_exists($category,$feeds)) $category='Geral';
 if (!function_exists('curl_init') || !function_exists('simplexml_load_string')) return ['items'=>[],'error'=>'Instale cURL e SimpleXML no PHP.'];
 $ch=curl_init($feeds[$category]);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_USERAGENT=>'RadarViralBR/1.0']);
 $data=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if (!is_string($data)||$status!==200||strlen($data)>2000000) return ['items'=>[],'error'=>'Não foi possível consultar o feed agora.'];
 libxml_use_internal_errors(true);
 $xml=simplexml_load_string($data,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
 if ($xml===false) return ['items'=>[],'error'=>'Feed inválido'];
 $items=[];
 foreach($xml->channel->item??[] as $entry){
  $title=trim((string)$entry->title);$url=trim((string)$entry->link);
  if ($title===''||!str_starts_with($url,'https://')) continue;
  $timestamp=strtotime((string)$entry->pubDate)?:time();
  $source=trim((string)$entry->source);
  $items[]=['title'=>$title,'url'=>$url,'published'=>date(DATE_ATOM,$timestamp),'source'=>$source?:'Google Notícias','category'=>$category];
  if(count($items)>=$limit) break;
 }
 return ['items'=>$items,'error'=>null,'category'=>$category,'updated'=>date(DATE_ATOM)];
}
