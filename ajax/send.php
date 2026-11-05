<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
require_auth($config);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
function fail(string $m, int $code = 422): void { out(['ok' => false, 'error' => $m], $code); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('Método no permitido', 405);
if (!hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Token CSRF inválido. Recarga la página.', 403);
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) fail('JSON de entrada inválido', 400);

// Variables {{uuid}}, {{timestamp}}, {{iso}}, {{random}}
$vars = [
    '{{uuid}}' => sprintf('%08x-%04x-4%03x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xfff), random_int(0x8000, 0xbfff), random_int(0, 0xffffffffffff)),
    '{{timestamp}}' => (string)time(),
    '{{iso}}' => gmdate('Y-m-d\TH:i:s\Z'),
    '{{random}}' => bin2hex(random_bytes(4)),
];
$tpl = fn($s) => strtr((string)$s, $vars);

$method = strtoupper((string)($in['method'] ?? 'POST'));
if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) fail('Método no soportado');

$url = $tpl(trim((string)($in['url'] ?? '')));
$p = parse_url($url);
if (!$url || !$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) fail('URL inválida (debe empezar con http:// o https://)');
if ($config['allowed_hosts'] && !in_array($p['host'], $config['allowed_hosts'], true)) fail('Host no permitido por config.php');
if ($config['block_private_hosts']) {
    $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : (gethostbynamel($p['host']) ?: []);
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) fail('Host privado bloqueado por config.php');
    }
}

$o = $in['options'] ?? [];
$timeout = max(1, min((int)($o['timeout'] ?? 15), (int)$config['max_timeout']));

// Cuerpo
$hasBody = !in_array($method, ['GET', 'HEAD'], true);
$body = $hasBody ? $tpl($in['body'] ?? '') : '';
$ctype = trim((string)($in['content_type'] ?? ''));
if ($body !== '' && stripos($ctype, 'json') !== false && empty($o['allow_invalid_json'])) {
    json_decode($body);
    if (json_last_error() !== JSON_ERROR_NONE) fail('El cuerpo no es JSON válido: ' . json_last_error_msg());
}

// Headers (clave en minúsculas para evitar duplicados; los últimos ganan)
$H = [];
$set = function ($n, $v) use (&$H) {
    $n = trim((string)$n);
    $v = str_replace(["\r", "\n"], '', (string)$v);
    if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $n)) $H[strtolower($n)] = [$n, $v];
};
$set('User-Agent', 'Webhook-Tester/1.0');
if ($body !== '' && $ctype !== '') $set('Content-Type', $ctype);

$a = $in['auth'] ?? [];
switch ($a['type'] ?? 'none') {
    case 'bearer': $set('Authorization', 'Bearer ' . $tpl($a['token'] ?? '')); break;
    case 'basic':  $set('Authorization', 'Basic ' . base64_encode(($a['user'] ?? '') . ':' . ($a['pass'] ?? ''))); break;
    case 'apikey': if (($a['key_name'] ?? '') !== '') $set($a['key_name'], $tpl($a['key_value'] ?? '')); break;
}

foreach ((array)($in['headers'] ?? []) as $h) {
    if (($h['enabled'] ?? true) && ($h['name'] ?? '') !== '') $set($h['name'], $tpl($h['value'] ?? ''));
}

// Firma HMAC sobre el cuerpo exacto que se envía
$s = $in['signature'] ?? [];
if (!empty($s['enabled']) && ($s['secret'] ?? '') !== '') {
    $algo = in_array($s['algo'] ?? '', ['sha1', 'sha256', 'sha384', 'sha512'], true) ? $s['algo'] : 'sha256';
    $ts = (string)time();
    $payload = ($s['payload'] ?? 'body') === 'timestamp_body' ? $ts . '.' . $body : $body;
    $raw = hash_hmac($algo, $payload, (string)$s['secret'], true);
    $sig = ($s['encoding'] ?? 'hex') === 'base64' ? base64_encode($raw) : bin2hex($raw);
    $set(($s['header'] ?? '') ?: 'X-Signature', ($s['prefix'] ?? '') . $sig);
    if (($s['timestamp_header'] ?? '') !== '') $set($s['timestamp_header'], $ts);
}

$lines = array_map(fn($x) => $x[0] . ': ' . $x[1], array_values($H));
$sent = ['method' => $method, 'url' => $url, 'headers' => $lines, 'body' => $body];

if (!empty($in['preview'])) out(['ok' => true, 'preview' => true, 'request' => $sent]);

// Envío
$rh = []; $rb = ''; $trunc = false; $max = (int)$config['max_response_bytes'];
$ch = curl_init($url);
$opts = [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => array_merge($lines, ['Expect:']),
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
    CURLOPT_FOLLOWLOCATION => !empty($o['follow']),
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_SSL_VERIFYPEER => !isset($o['verify_ssl']) || !empty($o['verify_ssl']),
    CURLOPT_SSL_VERIFYHOST => (!isset($o['verify_ssl']) || !empty($o['verify_ssl'])) ? 2 : 0,
    CURLINFO_HEADER_OUT    => true,
    CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$rh) {
        $t = trim($l);
        if (stripos($t, 'HTTP/') === 0) $rh = [];
        elseif (strpos($t, ':') !== false) { [$k, $v] = explode(':', $t, 2); $rh[] = [trim($k), trim($v)]; }
        return strlen($l);
    },
    CURLOPT_WRITEFUNCTION  => function ($c, $d) use (&$rb, &$trunc, $max) {
        if (strlen($rb) + strlen($d) > $max) { $trunc = true; $rb .= substr($d, 0, max(0, $max - strlen($rb))); return 0; }
        $rb .= $d;
        return strlen($d);
    },
];
if ($method === 'HEAD') $opts[CURLOPT_NOBODY] = true;
elseif ($body !== '' || in_array($method, ['POST', 'PUT', 'PATCH'], true)) $opts[CURLOPT_POSTFIELDS] = $body;
curl_setopt_array($ch, $opts);

curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$i = curl_getinfo($ch);
if ($trunc && $errno === 23) { $errno = 0; $err = ''; }
$ms = fn($k) => round(($i[$k] ?? 0) * 1000, 1);

out([
    'ok'       => $errno === 0,
    'error'    => $errno ? "cURL ($errno): $err" : null,
    'request'  => $sent + ['raw_headers' => $i['request_header'] ?? ''],
    'response' => [
        'status'    => (int)$i['http_code'],
        'headers'   => $rh,
        'body'      => $rb,
        'truncated' => $trunc,
        'size'      => (int)$i['size_download'],
        'final_url' => $i['url'] ?? $url,
        'ip'        => $i['primary_ip'] ?? '',
    ],
    'timing'   => ['total' => $ms('total_time'), 'dns' => $ms('namelookup_time'), 'connect' => $ms('connect_time'), 'ttfb' => $ms('starttransfer_time')],
]);
