<?php
require './vendor/autoload.php';
require_once './lib/DataDir.php';
date_default_timezone_set('asia/tokyo');

if(!isset($argv[1])) {
    echo 'require date parameter ( yyyy.mm.dd )';
    exit(0);
}

$DataDir = new DataDir(__DIR__);
$dires = [];
if($argv[1] === 'all') {
    $dh = opendir($DataDir->getDataDir());
    while(($filename = readdir($dh)) !== false) {
        if (preg_match('/^\d\d\d\d\.\d\d\.\d\d/', $filename)) {
            $dires[] = $DataDir->getBaseDir($filename);
        }
    }
}
else {
    $dires[] = $DataDir->getBaseDir($argv[1]);
    if (!file_exists($dires[0])) {
        echo 'data directory not found';
        exit(0);
    }
}

$minNodeAceessCount = 1;
$minElementAccessCount = 1;

/**
 * ベースのArrayをそのままSaveする
 */
$sites = [];
$links = [];
foreach($dires as $dir) {
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
}

if(count($dires) > 1) {
    $dataDir = $DataDir->getDataDir() . 'all/';
    if(!file_exists($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
}
else {
    $dataDir = $dires[0];
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
        if(empty($key)) {
            continue;
        }
        if($site < $mincount) {
            continue;
        }
        $weight = (($site - $minSiteCount) / $baseSiteCount) * 100;
        $data = (object)[
            'data' => (object)[
                'id'     => str_replace('.', '_', $key),
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
        if(empty($link['from']) || empty($link['to'])) {
            continue;
        }
        if($link['count'] < $mincount) {
            continue;
        }
        $weight = (($link['count'] - $minLinkCount) / $baseLinkCount) * 10;
        $data = (object)[
            'data' => (object)[
                'id'     => str_replace('.', '_', $key),
                'source' => str_replace('.', '_', $link['from']),
                'target' => str_replace('.', '_', $link['to']),
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
