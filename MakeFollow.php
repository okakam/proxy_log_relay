<?php
require './vendor/autoload.php';
require './lib/DataDir.php';
date_default_timezone_set('asia/tokyo');

/**  elasticsearch hosts */
$hosts = [
    'localhost',
];

$logger = Elasticsearch\ClientBuilder::defaultLogger('/tmp/make_follow.log');
$client = Elasticsearch\ClientBuilder::create()->setHosts($hosts)->setLogger($logger)->build();

$aliases = $client->indices()->getAliases();
$aliasesName = [];
foreach ($aliases as $key => $aliase) {
    if(preg_match('/^access_log/', $key)) {
        array_push($aliasesName, $key);
    }
}

/** 最新のIndexを取得 */
arsort($aliasesName);
foreach($aliasesName as $indexName) { echo "index list : " . $indexName . "\n"; }

if(isset($argv[1]) && $argv[1] === 'index') { exit(0); }

if(isset($argv[1])) {
    $accessLogIndex = $argv[1];
}
else {
    $accessLogIndex = array_shift($aliasesName);
}
$accessLogDate = preg_replace('/^[^-]*-/', '', $accessLogIndex);
echo "index : " . $accessLogIndex . "\n";

$DataDir = new DataDir(__DIR__);
$dataDir = $DataDir->getBaseDir($accessLogDate);
echo 'base dir : ' . $dataDir . "\n";
if(!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

/** 訪れたサイトのリストと、遷移情報を取得 カウントする */
/** 一旦全体 */
$pslManager = new Pdp\PublicSuffixListManager();
$parser = new Pdp\Parser($pslManager->getList());

$sites = [];
$links = [];
$param = [
    'index' => $accessLogIndex,
    'type' => 'access_log',
    'scroll' => '60m',
    'body' => [
        'size' => '100000',
        'fields' => ['@timestamp', 'userid', 'domain', 'referer_domain', 'path'],
    ],
];

$result = $client->search($param);
$docsTotal = $result['hits']['total'];
$docsCount = 0;
$skipCount = 0;
$domainDic = [];
while (true) {
    /** @var array $hits */
    $hits = $result['hits']['hits'];
    $scrollId = $result['_scroll_id'];

    if(count($hits) < 1) { break; }

    $docsCount += count($hits);
    echo $docsCount . " / " . $docsTotal . " (" . ($docsCount / $docsTotal * 100) . ")";

    foreach ($hits as $hit) {
        $fields = $hit['fields'];

        $timestamp = strtotime($fields['@timestamp'][0]);
        $hr = date('H', $timestamp);
        if(!isset($fields['domain'])) { $skipCount++; continue; }

        $domain = $fields['domain'][0];
        if(strpos($domain, ':') !== false) {
            $domain = preg_replace('/:\d*$/', '', $domain);
        }
        $regDomain = isset($domainDic[$domain]) ? $domainDic[$domain] : $domainDic[$domain] = $parser->getRegisterableDomain($domain);
        isset($sites[$hr][$regDomain]) ? $sites[$hr][$regDomain]++ : $sites[$hr][$regDomain] = 1;
        isset($sites['all'][$regDomain]) ? $sites['all'][$regDomain]++ : $sites['all'][$regDomain] = 1;


        if(!isset($fields['referer_domain'])) { continue; }
        $refDomain  = $fields['referer_domain'][0];
        if(strpos($refDomain, ':') !== false) {
            $refDomain = preg_replace('/:\d*$/', '', $refDomain);
        }
        $regRefDomain = isset($domainDic[$refDomain]) ? $domainDic[$refDomain] : $domainDic[$refDomain] = $parser->getRegisterableDomain($refDomain);
        if($regDomain !== $regRefDomain) {
            setLinks($regRefDomain, $regDomain, $hr);
            setLinks($regRefDomain, $regDomain, 'all');
        }
    }

    echo ", skip : " . $skipCount . "\n";

    $result = $client->scroll(['scroll_id' => $scrollId, 'scroll' => '60m']);
}

/**
 * ベースのArrayをそのままSaveする
 */
echo "save sites count : " . count($sites) . "\n";
echo "save links count : " . count($links) . "\n";
file_put_contents($dataDir . 'original_sites.json', json_encode($sites, JSON_PRETTY_PRINT));
file_put_contents($dataDir . 'original_links.json', json_encode($links, JSON_PRETTY_PRINT));

/**
 * @param $regRefDomain
 * @param $regDomain
 * @param $hr
 * @return array
 */
function setLinks($regRefDomain, $regDomain, $hr){
    global $links;
    $linkKey = $regRefDomain . '_to_' . $regDomain;
    if (isset($links[$hr][$linkKey])) {
        $links[$hr][$linkKey]['count']++;
    } else {
        $links[$hr][$linkKey]['count'] = 1;
        $links[$hr][$linkKey]['from'] = $regRefDomain;
        $links[$hr][$linkKey]['to'] = $regDomain;
    }
}
