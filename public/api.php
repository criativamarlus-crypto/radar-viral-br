<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public,max-age=180');
require_once dirname(__DIR__).'/app/feeds.php';
$category=is_string($_GET['categoria']??null)?$_GET['categoria']:'Geral';
$response=radar_fetch($category);
if($response['error']!==null) http_response_code(503);
echo json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
