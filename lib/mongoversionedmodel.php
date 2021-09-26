<?php

abstract class MongoVersionedModel extends MongoModel {

    private $version = 1;

    abstract function setCurrentVersion();

    /////////////////////////////////////////////////////////////
    //overwrite previous function
    private function getCollection($altTableName = null) {
        $ret = parent::getCollection($altTableName);

        if ($ret) {
            $ret->ensureIndex(array('_id' => 1), array(
                'unique' => 1,
                'dropDups' => 1,
                'safe' => 1
            ));
        }

        return $ret;
    }

    /////////////////////////////////////////////////////////////

    function getVersions($id, $tableName) {
        $versions = parent::read($id, $tableName, true);
        if (empty($versions)) {
            return array();
        }
        unset($versions[$this->useIndex]);
        return $versions;
    }

    /////////////////////////////////////////////////////////////

    function getReleaseVersions() {
        $vers = parent::read(null,'_versions');
        $return = array();
        
        foreach ($vers as $ver) {
            if (!empty($ver['release'])) {
                $return[$ver['id']] = $ver['id'];
            }
        }
        return $return;
    }
    
    
    function findBestVersion($versions, $aboveVersion = null) {
        if (!is_null($aboveVersion)) {
            $exVersions = $this->getReleaseVersions();
            
            $best = $aboveVersion;
            foreach ($versions as $ver => $timestamp) {
                if (($best < $ver) && isset($exVersions[$ver])) {
                    $best = $ver;
                }
            }
            return $best > 0 ? $best : false;
        }

        if (isset($versions[$this->version])) {
            return $this->version;
        }

        $best = 0;
        foreach ($versions as $ver => $timestamp) {
            if ($best < $ver) {
                $best = $ver;
            }
        }

        return $best > 0 ? $best : false;
    }

    function cutVersionFromId($id) {
        $x = strpos($id, '~');
        if ($x !== false) {
            return substr($id, 0, $x);
        }
        return $id;
    }

    //////////////////////////////////////////////////////////////////////

    function readAll($tableName = null, $aboveVersion) {
        $rows = parent::read(null, $tableName);

        $rowNames = array();

        foreach ($rows as $id => $row) {
            if (false !== strpos($id, '~')) {
                // no duplicates because we use the array-key
                $rowNames[$this->cutVersionFromId($id)] = array();
            }
        }

        foreach ($rowNames as $rowName => &$data) {
            $data = $this->read($rowName, $tableName, true, $aboveVersion);
        }

        return $rowNames;
    }

    function read($id = null, $tableName = null, $readOne = false, $aboveVersion = null) {
        $this->setCurrentVersion();

        if (is_null($id)) {
            return $this->readAll($tableName, $aboveVersion);
        }

        $versions = $this->getVersions($id, $tableName);
        $bestVersion = $this->findBestVersion($versions, $aboveVersion);

        if (false == $bestVersion) {
            return array();
        }

        $vid = $id . '~' . $bestVersion;
        if ($readOne) {
            $row = parent::read($vid, $tableName, $readOne);
            $row[$this->useIndex] = $this->cutVersionFromId($row[$this->useIndex]);
            return $row;
        }

        $rows = parent::read($vid, $tableName, $readOne);
        if (!empty($rows)) {
            foreach ($rows as $rid => $row) {
                $row[$this->useIndex] = $this->cutVersionFromId($row[$this->useIndex]);
                $rows[$this->cutVersionFromId($rid)] = $row;
                unset($rows[$rid]);
            }
        }
        return $rows;
    }

    function create($fields = null, $tableName = null) {
        $this->setCurrentVersion();

        if (empty($fields[$this->useIndex])) {
            throw new DatabaseErrorException('create: no id given');
        }

        $id = $fields[$this->useIndex];
        unset($fields[$this->useIndex]);

        $versions = $this->getVersions($id, $tableName);

        $versions[$this->version] = time();
        ksort($versions);
        $versions[$this->useIndex] = $id;
        parent::create($versions, $tableName);

        $fields[$this->useIndex] = $id . '~' . $this->version;
        parent::create($fields, $tableName);
    }

    function delete($id, $tableName = null) {
        $this->setCurrentVersion();

        $versions = $this->getVersions($id, $tableName);
        if (!isset($versions[$this->version])) {
            return;
        }
        unset($versions[$this->version]);
        
        parent::delete($id . '~' . $this->version, $tableName);

        if (0 == count($versions)) {
            parent::delete($id, $tableName);
        } else {
            $versions[$this->version] = time();
            parent::create($versions, $tableName);
        }
    }

    function update($id, $fields, $tableName = null) {
        $this->setCurrentVersion();

        $versions = $this->getVersions($id, $tableName);

        $versions[$this->version] = time();
        ksort($versions);

        $versions[$this->useIndex] = $id;
        parent::create($versions, $tableName);

        $fields[$this->useIndex] = $id . '~' . $this->version;
        parent::create($fields, $tableName);
    }

    function replace($fields = null, $tableName = null) {
        $id = $fields[$this->useIndex];

        $this->delete($id, $tableName);
        $this->create($fields, $tableName);
    }

}