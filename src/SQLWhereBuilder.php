<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\Exception;
use DovStone\MyNoSQL\SQLBuilder;
use DovStone\MyNoSQL\SQLHandler;

class SQLWhereBuilder
{
    protected $pdo;
    protected $collection;
    protected $sql;
    protected $params = [];

    public function __construct($pdo, $collection)
    {
        $this->pdo = $pdo;
        $this->collection = $collection;
    }

    public function getWhereData($criteria, $orderBy, $limit, $offset, $isCountBy = null)
    {
        if($isCountBy){
            $this->sql = "SELECT COUNT(id) as __cnt__ FROM `$this->collection`";
        }
        else {
            $this->sql = "SELECT * FROM `$this->collection`";
        }

        //   if false then countBy()
        if( $criteria === false ){
            $this->sql = "SELECT COUNT(id) as __cnt__ FROM `$this->collection`";
        }
        //  if ! then not findAll()
        elseif( $criteria ){
            $this->sql .= " WHERE (";
            $this->_loopOverCriteria($criteria);
            $this->sql .= ")";
            $this->sql .= ($limit >= 0) ? " LIMIT $offset, $limit" : "";
        }
        
        return (object)[
            'sql' => $this->sql,
            'params' => $this->params
        ];
    }
    
    private function _getWhereClause($column, $op, $val): string
    {
        $column = ($column == '_id') ? 'pid' : $column;
        $op_o = $op;
        $op = strtolower($op);

        switch ($op) {
            case '=':
            case '==':
            case '!=':
            case '!==':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'regexp':
            case 'not regexp':
            case 'like':
            case 'not like':

            /* path against val */
            /* array against non-array */
            case 'contains':
            case 'not contains':

                if( !is_numeric($val) && !is_string($val) && !is_null($val) ){
                    throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"numeric\" or \"string\" or \"null\". \"".gettype($val)."\" given. Path:\"$column\". SQLWhereBuilder.php", 1);
                }

                if( $op == '!==' ){ $op = '!='; }
                if( $op == '==' ){ $op = '='; }

                if( in_array($op, ['contains', 'not contains']) ){

                    $columns = (new SQLHandler($this->pdo, $this->collection))->_getColumns();
                    if($columns){
                        $op = ($op == 'contains') ? '=' : '!=';
                        $where = "(";
                        foreach ($columns as $col) {
                            if( strpos($col, $column) !== false ){
                                $where .= "`$col` $op ? ".($op == '=' ? 'or' : 'and')." ";
                                $this->params[] = $val;
                            }
                        }
                        $where .= ")";
                    }
                    $where = str_replace(' or )', ')', $where);
                    $where = str_replace(' and )', ')', $where);
                }

                elseif( in_array($op, ['like', 'not like']) ){
                    $where = "(`$column` $op ?)";
                    $this->params[] = "%$val%";
                }

                elseif( $this->_isValidDate($val) ){
                    $where = "(`$column` $op cast(? as datetime))";
                    $this->params[] = $val;
                }

                else {
                    $where = "(`$column` $op ?)";
                    $this->params[] = $val;
                }
            break;

            /* path against val */
            /* non-array against array */
            case 'in':
            case 'not in':
                
                if( !is_array($val) ){
                    throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"array\". \"".gettype($val)."\ given. Path:\"$column\". SQLWhereBuilder.php", 1);
                }
                $where = "(`$column` $op (";
                $where .= "'".implode("','", $val)."'";
                $where .= "))";
            break;
                
            /* path against val */
            /* array against array */
            case 'exists in':
            case 'not exists in':

                if( !is_array($val) ){
                    throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"array\". \"".gettype($val)."\ given. Path:\"$column\". SQLWhereBuilder.php", 1);
                }

                $columns = (new SQLHandler($this->pdo, $this->collection))->_getColumns();
                if($columns){
                    $op = ($op == 'exists in') ? 'in' : 'not in';
                    $where = "(";
                    foreach ($columns as $col) {
                        if( strpos($col, $column) !== false ){
                            
                            /*$where .= "(`$col` $op ? ".($op == 'exists in' ? 'or' : 'and')." (";
                            $where .= "'".implode("','", $val)."'";
                            $where .= "))";*/
                            
                            $where .= "(`$col` $op (";
                            $where .= "'".implode("','", $val)."'";
                            $where .= "))";
                            $where .= ($op == 'in') ? ' or ' : ' and ';

                            //$this->params = array_merge($this->params, $val);
                        }
                    }
                    $where .= ")";
                }

                $where = str_replace(' or )', ')', $where);
                $where = str_replace(' and )', ')', $where);
            break;
            
            default:
                    throw new Exception("Condition operator \"$op\" is unknown. SQLWhereBuilder.php", 1);
                break;
        }

        return $where;
    }
    
    private function _loopOverCriteria($criteria)
    {
        $sql = '';
        $lastJoined = false;

        foreach ($criteria as $k => $cri) {
            if( is_string($cri) ){
                if( in_array($cri, ['and', 'or', 'AND', 'OR']) ){
                    $sql .= $k > 0 ? strtolower($cri) : "";
                    $lastJoined = "condition";
                }
                else {
                    if( is_array($criteria) && count($criteria) === 3 ){
                        return $this->_concatSql($criteria);
                    }
                    throw new Exception(sprintf('Malformed $criteria. Use only "and" or "or" logical. Case insensitive, "%s" given', $cri), 1);
                }
            }
            else {
                $sql .= $lastJoined == "array" ? 'and' : '';
                $sql .= '(';
                $sql .= isset($cri[0]) && is_array($cri[0]) ? $this->loopOverCriteria($cri) : $this->_concatSql($cri);
                $sql .= ')';
                $lastJoined = "array";
            }
        }
        if( !empty($sql) ){
            $this->sql .= $sql;
        }
        return null;
    }

    private function _concatSql($criteria)
    {
        if( is_countable($criteria) ){
            if( count($criteria) !== 3 ){
                throw new Exception(sprintf('Malformed $criteria. Exactly 3 parameters expected, %s given', count($criteria)), 1);
            }
            $column = $criteria[0];
            $op = $criteria[1];
            $val = $criteria[2];
            return $this->_getWhereClause($column, $op, $val);
        }
        throw new Exception('Malformed $criteria', 1);
    }

    private function _isValidDate($date, $format='Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}