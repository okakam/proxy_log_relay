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

$filenames = [];

if(!isset($_GET['date'])) {
    echo json_encode(compact('filenames'));
    exit(0);
}
$dirName = $_GET['date'];

$DataDir = new DataDir(__DIR__);
$dataDir = $DataDir->getBaseDir($dirName);

$dirHandle = opendir($dataDir);
while (($filename = readdir($dirHandle)) !== false) {
    if (preg_match('/.*\.json/', $filename)) {
        $filenames[] = $filename;
    }
}

sort($filenames);

echo json_encode(compact('filenames'));
