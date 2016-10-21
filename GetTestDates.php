<?php
require_once './lib/DataDir.php';

/**
 * Created by PhpStorm.
 * User: usr1600123
 * Date: 2016/09/30
 * Time: 10:48
 */
// Content-TypeをJSONに指定する
header('Content-Type: application/json');

$DataDir = new DataDir(__DIR__, true);
$dataDir = $DataDir->getDataDir();

$dirHandle = opendir($dataDir);
$filenames = [];
while (($filename = readdir($dirHandle)) !== false) {
    if (preg_match('/^\d\d\d\d\.\d\d\.\d\d/', $filename)) {
        $filenames[] = $filename;
    }
}
sort($filenames);

echo json_encode(compact('filenames'));
