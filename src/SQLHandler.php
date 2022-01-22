<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\Exception;
use DovStone\MyNoSQL\SQLWhereBuilder;

class SQLHandler extends SQLWhereBuilder
{
    protected $pdo;
    protected $collection;

    public function __construct($pdo, $collection)
    {
        $this->pdo = $pdo;
        $this->collection = $collection;
    }
    
    public function createCollectionIfNotExists()
    {
        try {
            $sql = "CREATE TABLE 
                        IF NOT EXISTS `$this->collection`
                            ( `id` INT NOT NULL AUTO_INCREMENT, `pid` INT (8) NOT NULL, PRIMARY KEY (`id`), INDEX (`pid`))
                                ENGINE = InnoDB;"."\n";
            //
            $this->__commit($sql);
        } catch (\Throwable $th) {
            $this->pdo->rollBack();
        }
        return $this;
    }
    
    public function thenCreateColumnsIfNotExists($documentExploded)
    {
        foreach ($documentExploded as $column => $value) {
            try {
                $sql = "ALTER TABLE `$this->collection`
                            ADD `$column` LONGTEXT NULL DEFAULT NULL";
                //
                $this->__commit($sql);
            } catch (\Throwable $th) {
                $this->pdo->rollBack();
            }
        }
        return $this;
    }

    public function thenInsertValue($documentExploded, $pid)
    {
        $columns = "`pid`,";
        $valuesPlaceholder = "?,";
        $values = [$pid];
        //
        foreach ($documentExploded as $column => $value) {
            $columns .= "`$column`,";
            $valuesPlaceholder .= "?,";
            $values[] = $value;
        }
        $values[] = $pid;
        //
        $columns = trim($columns, ',');
        $valuesPlaceholder = trim($valuesPlaceholder, ',');
        //
        $sql = "INSERT INTO `$this->collection` ($columns)
                    VALUES ($valuesPlaceholder)
                        ON DUPLICATE
                            KEY UPDATE pid=?";
        //
        $this->__commit($sql, $values);
        $this->__purgeCollection();
        //
        return $this;
    }

    public function delete($pid)
    {
        return $this->__commit("DELETE FROM `$this->collection` WHERE pid=?", [$pid]);
    }

    public function getFindSQLData($pid)
    {
        return (object)[
            'sql' => "SELECT * FROM `$this->collection` WHERE pid=?",
            'params' => [$pid]
        ];
    }

    public function getFindBySQLData($criteria, $orderBy, $limit, $offset, $isCountBy = null)
    {
        $data = (new SQLWhereBuilder($this->pdo, $this->collection))->getWhereData($criteria, $orderBy, $limit, $offset, $isCountBy);
        return $data;
    }

    public function _fetch($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function _fetchAll($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function _getColumns()
    {
        $columns = $this->_fetchAll("SHOW COLUMNS FROM `$this->collection`");
        return array_column($columns, 'Field');
    }

    private function __purgeCollection()
    {
        if($columns = $this->_getColumns()){
            foreach ($columns as $column) {
                $res = $this->_fetch("SELECT COUNT(DISTINCT `$column`) cnt FROM `$this->collection`");
                if( $res && isset($res['cnt']) && $res['cnt'] == 0 ){
                    $sql = "ALTER TABLE `$this->collection` DROP COLUMN `$column`";
                    $commit = $this->__commit($sql);
                }
            }
        }
        return $this;
    }

    private function __commit($sql, $params = [])
    {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $commit = $this->pdo->commit();
            return $this;
        } catch (\Throwable $th) {
            //dump([$sql, $th]);
            $this->pdo->rollBack();
        }
        return $this;
    }
}