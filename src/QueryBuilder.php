<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\DocumentPloder;
use DovStone\MyNoSQL\QueryBuilder;
use DovStone\MyNoSQL\SQLHandler;

class QueryBuilder
{
    protected $pdo;
    protected $collection;
    protected $criteria;
    protected $orderBy;
    protected $limit;
    protected $offset;
    protected $offsetQuery;
    protected $_getMethod;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        //parent::__construct($pdo);
    }

    public function collection($collection)
    {
        $this->collection = $this->getValidCollectionName($collection);
        return $this;
    }
    
    public function insert($document, $pid = null)
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $documentExploded = (new DocumentPloder())->explode($document);
        $SQLHandler = new SQLHandler($this->pdo, $this->collection);
        if( $documentExploded ){
            $pid = $pid ?? substr(crc32(uniqid()), 0, 8);
            $SQLHandler
                ->createCollectionIfNotExists()
                    ->thenCreateColumnsIfNotExists($documentExploded)
                        ->thenInsertValue($documentExploded, $pid);
            return $this->find($pid)->fetch();
        }
        return false;
    }
    
    public function update($pid, $newDocument)
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->delete($pid);
        return $this->insert($newDocument, $pid);
    }
    
    public function delete($pid)
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        (new SQLHandler($this->pdo, $this->collection))->delete($pid);
        return true;
    }

    public function find($pid)
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindSQLData';
        $this->_findType = 'findOneBy';
        //
        $this->pid = $pid;
        //
        return $this;
    }
    
    public function findBy(array $criteria = [], array $orderBy = ['_createdAt' => 'desc'], int $limit = 15, int $offset = null, $offsetQuery = 'page')
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindBySQLData';
        $this->_findType = 'findBy';
        //
        $this->criteria = $criteria;
        $this->orderBy = $orderBy;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->offsetQuery = $offsetQuery;
        //
        return $this;
    }
    
    public function findOneBy(array $criteria = [])
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindBySQLData';
        $this->_findType = 'findOneBy';
        //
        $this->criteria = $criteria;
        //
        return $this;
    }
    
    public function findAllBy(array $criteria = [], array $orderBy = ['_createdAt' => 'desc'])
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindBySQLData';
        $this->_findType = 'findBy';
        //
        $this->criteria = $criteria;
        $this->limit = -1;
        //
        return $this;
    }
    
    public function findAll(array $orderBy = ['_createdAt' => 'desc'])
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindBySQLData';
        $this->_findType = 'findBy';
        //
        $this->criteria = null;
        $this->limit = -1;
        //
        return $this;
    }
    
    public function count()
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $sql = "SELECT COUNT(id) as cnt FROM `$this->collection`";
        $row = (new SQLHandler($this->pdo, $this->collection))->_fetch($sql);
        //
        return (int) $row['cnt'];
    }
    
    public function countBy(array $criteria = [])
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        $this->_getMethod = 'getFindBySQLData';
        $this->_findType = 'findBy';
        //
        $this->isCountBy = true;
        $this->criteria = $criteria;
        //
        return $this;
    }

    public function orderBy(array $orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function offset(int $offset = null, string $pageQuery = 'page')
    {
        $this->offset = $offset;
        $this->pageQuery = $pageQuery;
        return $this;
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function getOffset(int $limit, string $pageQuery = 'page')
    {
        $page = $_GET[$pageQuery] ?? 1;
        $page = $page <= 0 ? 1 : $page;
        $offset = ((int)$page - 1) * $limit;
        $this->limit = $limit;
        $this->pageQuery = $pageQuery;
        return $offset;
    }

    public function getSQLData()
    {
        if( !isset($this->_getMethod) ){
            throw new Exception('No collection was defined.');
        }
        else {
            switch($this->_getMethod){
                case 'getFindSQLData';
                    return (new SQLHandler($this->pdo, $this->collection))->getFindSQLData(
                        $this->pid
                    );
                break;
                case 'getFindBySQLData';
                    return (new SQLHandler($this->pdo, $this->collection))->getFindBySQLData(
                        $this->criteria ?? [],
                        $this->orderBy ?? [],
                        $this->limit ?? 15,
                        $this->offset ?? 0,
                        $this->isCountBy ?? null
                    );
                break;
                default: return null; break;
            }
        }
    }

    public function getSQL()
    {
        $SQLData = $this->getSQLData();
        if( !$SQLData ){
            throw new Exception('No collection was defined.');
        }
        return $SQLData->sql;
    }

    public function getSQLParams()
    {
        $SQLData = $this->getSQLData();
        if( !$SQLData ){
            throw new Exception('No collection was defined.');
        }
        return $SQLData->params;
    }

    public function fetch()
    {
        if( !isset($this->collection) ){
            throw new Exception('No collection was defined.');
        }
        try {

            $SQLData = $this->getSQLData();
            $rows = (new SQLHandler($this->pdo, $this->collection))->_fetchAll($SQLData->sql, $SQLData->params);

            if(array_column($rows, '__cnt__') && isset(array_column($rows, '__cnt__')[0])){
                return (int) array_column($rows, '__cnt__')[0];
            }
            
            if($rows){
                foreach ($rows as $row) {
                    foreach ($row as $column => $val) {
                        if(is_null($val)){ unset($rows[$column]); }
                    }
                }
                $documents = [];
                if(!isset($this->documentPloder)){
                    $this->documentPloder = (new DocumentPloder());
                }
                foreach ($rows as $row) {
                    if($document = $this->documentPloder->implode($row)){
                        $documents[] = $document;
                    }
                }
                if($this->_findType == 'findOneBy' || (isset($this->limit) && $this->limit == 1) ){
                    return $documents[0];
                }
                return $documents;
            }

        } catch (\Exception $e) {
            new Exception($e->getMessage());
            return null;
        }
        //
        return null;
    }

    private function getValidCollectionName($collection)
    {
        return strtolower($collection);
    }
}