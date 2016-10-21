<?php
require './vendor/autoload.php';
require_once './lib/DataDir.php';
date_default_timezone_set('asia/tokyo');

header('Content-Type: application/json');

/**
 * 期待するパラメータ
 * date  : yyyy.mm.dd 形式の日付 -> その日のデータだけ取得
 *         yyyy.mm -> その月のデータ全部取得
 * count : 取得する最大ノード数 (Default 200)
 * hr    : 時間 00 〜 23 | all (Default all)
 */
$dateParam = '';
if(isset($_GET['date'])) {
    $dateParam = $_GET['date'];
    if(preg_match('/^\d\d\d\d\.\d\d(\.\d\d)?/', $dateParam)) {
        echo json_encode([]);
        exit(0);
    }
}
$countParam = 0;
if(isset($_GET['count'])) {
    $countParam = intval($_GET['count']);
}
$hrParam = "all";
if(isset($_GET['hr'])) {
    $hrParam = $_GET['hr'];
}

if(empty($dateParam) || empty($countParam)) {
    echo json_encode([]);
    exit(0);
}
$DataDir = new DataDir(__DIR__, true);
$dir = $DataDir->getBaseDir($argv[1]);
if (!file_exists($dir)) {
    echo 'data directory not found';
    exit(0);
}

$minNodeAceessCount = 1;
$minElementAccessCount = 1;

/**
 * ベースのArrayをそのままSaveする
 */
$sites = [];
$links = [];
$sitesJson = file_get_contents($dir . 'original_sites.json');
$linksJson = file_get_contents($dir . 'original_links.json');

$readSites = json_decode($sitesJson, true);
$readLinks = json_decode($linksJson, true);

foreach($readSites as $keyHr => $readSitesHr) {
    foreach($readSitesHr as $keyDomain => $readSite) {
        if(!isset($sites[$keyHr][$keyDomain])) {
            $sites[$keyHr][$keyDomain] = 0;
        }
        $sites[$keyHr][$keyDomain] += $readSite;
    }
}

foreach($readLinks as $keyHr => $readLinkHr) {
    foreach($readLinkHr as $keyLink => $readLink) {
        if(!isset($links[$keyHr][$keyLink])){
            $links[$keyHr][$keyLink] = $readLink;
        }
        else {
            $links[$keyHr][$keyLink]['count'] = $readLink['count'];
        }
    }
}

/**
 * 全件出力
 */
arsort($sites);
foreach ($sites as $hr => $siteHr) {
    echo "hr : " . $hr . "\n";
    if(!isset($links[$hr])) { continue; }
    $linkHr = $links[$hr];

    makeNodesFile($siteHr, 'all_' . $hr . '_nodes', $minNodeAceessCount);
    makeElementFile($linkHr, 'all_' . $hr . '_elements', $minElementAccessCount);

    saveUpperLevelData($siteHr, $hr, $linkHr, 500);
    saveUpperLevelData($siteHr, $hr, $linkHr, 200);
    saveUpperLevelData($siteHr, $hr, $linkHr, 100);
}

/**
 * @param array $sites
 * @param string $fn
 * @param int $mincount
 */
function makeNodesFile($sites, $fn, $mincount) {
    /**
     * Node を作る
     */
    global $dataDir;
    $nodes = [];
    $maxSiteCount = max($sites);
    $minSiteCount = min($sites);
    $baseSiteCount = ($maxSiteCount - $minSiteCount) < 1 ? 1 : ($maxSiteCount - $minSiteCount);
    foreach ($sites as $key => $site) {
        if($site < $mincount) {
            continue;
        }
        $weight = (($site - $minSiteCount) / $baseSiteCount) * 100;
        $data = (object)[
            'data' => (object)[
                'id'     => $key,
                'name'   => $key,
                'weight' => $weight < 1 ? 1 : $weight,
                'count'  => $site,
            ]
        ];

        $nodes[] = $data;
    }
    echo $fn . " : " . count($nodes) . "\n";
    file_put_contents($dataDir . $fn . '.json', json_encode($nodes, JSON_PRETTY_PRINT));
}

/**
 * @param $links
 * @param $fn
 * @param $mincount
 */
function makeElementFile($links, $fn, $mincount) {
    /**
     * Element を作る
     */
    global $dataDir;
    $elements = [];
    $maxLinkCount = 0;
    $minLinkCount = 999999999;
    foreach ($links as $link) {
        if ($maxLinkCount < $link['count']) {
            $maxLinkCount = $link['count'];
        }
        if ($minLinkCount > $link['count']) {
            $minLinkCount = $link['count'];
        }
    }

    $baseLinkCount = ($maxLinkCount - $minLinkCount) < 1 ? 1 : ($maxLinkCount - $minLinkCount);
    foreach ($links as $key => $link) {
        if($link['count'] < $mincount) {
            continue;
        }
        $weight = (($link['count'] - $minLinkCount) / $baseLinkCount) * 10;
        $data = (object)[
            'data' => (object)[
                'id'     => $key,
                'source' => $link['from'],
                'target' => $link['to'],
                'weight' => $weight < 1 ? 1 : $weight,
                'count'  => $link['count'],
            ]
        ];

        $elements[] = $data;
    }
    echo $fn . " : " . count($elements) . "\n";
    file_put_contents($dataDir . $fn . '.json', json_encode($elements, JSON_PRETTY_PRINT));
}

/**
 * @param array  $siteHr
 * @param string $hr
 * @param array  $linkHr
 * @param int    $count
 */
function saveUpperLevelData($siteHr, $hr, $linkHr, $count) {
    global $minNodeAceessCount, $minElementAccessCount;
    $sites = array_slice($siteHr, 0, $count);
    makeNodesFile($sites, $count . '_' . $hr . '_nodes', $minNodeAceessCount);

    $links = [];
    foreach ($linkHr as $link) {
        if (array_key_exists($link['to'], $sites) && array_key_exists($link['from'], $sites)) {
            $links[] = $link;
        }
    }
    makeElementFile($links, $count . '_' . $hr . '_elements', $minElementAccessCount);
}
