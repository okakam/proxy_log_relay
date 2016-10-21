<?php

/**
 * Created by PhpStorm.
 * User: usr1600123
 * Date: 2016/09/30
 * Time: 10:20
 */
class DataDir {
    const DATA_DIR_NAME = 'data/';
    const DATA_DIR_NAME_TEST = 'data_test/';

    public $projectDataDir = "";

    /**
     * DataDir constructor.
     *
     * @param $baseDir
     */
    public function __construct($baseDir, $isTest = false) {
        $this->projectDataDir = $baseDir;
        if (substr($this->projectDataDir, -1) !== '/') {
            $this->projectDataDir .= '/';
        }

        if($isTest) {
            $this->projectDataDir .= DataDir::DATA_DIR_NAME_TEST;
        }
        else {
            $this->projectDataDir .= DataDir::DATA_DIR_NAME;
        }
    }

    /**
     * @param $dateStr
     * @return string
     */
    public function getBaseDir($dateStr) {
        $dataDir = $this->projectDataDir . $dateStr . "/";
        return $dataDir;
    }

    /**
     * @return string
     */
    public function getDataDir() {
        return $this->projectDataDir;
    }
}
