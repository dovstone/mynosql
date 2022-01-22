<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\SQLHandler;
use DovStone\MyNoSQL\Exception;

class FinalQueryBuilder
{
    protected $pdo;
    private $sqlHanlder;
    private $pids = '';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->sqlHanlder = new SQLHandler($pdo);
    }

    public function getSql($criteria, $orderBy, $limit, $offset)
    {
        $overCriteria = $this->loopOverCriteria($criteria);
        $overCriteria = str_replace('and([])', '', $overCriteria);

        $sql = 'array_values(array_unique('."\n";
        $sql .= $overCriteria."\n";
        $sql .= '))';
        return $sql;
    }

    private function loopOverCriteria($criteria)
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
                        return $sql .= '(' . $this->concatSql($criteria) . ')';
                    }
                    throw new Exception(sprintf('Malformed $criteria. Use only "and" or "or" logical. Case insensitive, "%s" given', $cri), 1);
                }
            }
            else {
        
                $loop = isset($cri[0]) && is_array($cri[0]);

                $sql .= ($lastJoined == "array" ? 'and' : '');
                $sql .= '(';
                $sql .= $loop ? $this->loopOverCriteria($cri) : $this->concatSql($cri);
                $sql .= ')';
                $lastJoined = "array";
            }
        }

        return empty($sql) ? null : $sql;
    }

    private function concatSql($criteria)
    {
        if( is_countable($criteria) ){
            if( count($criteria) !== 3 ){
                throw new Exception(sprintf('Malformed $criteria. Exactly 3 parameters expected, %s given', count($criteria)), 1);
            }
            $table = $criteria[0];
            $op = $criteria[1];
            $val = $criteria[2];
            //
            $sql = $this->buildSql($table, $op, $val);
            return $sql;
        }
        throw new Exception('Malformed $criteria', 1);
    }
    
    private function buildSql($table, $op, $val)
    {
        $this->pids = '';
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

            /* array against non-array */
            case 'contains':
            case 'not contains':
                    if( !is_numeric($val) && !is_string($val) && !is_null($val) ){
                        throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"numeric\" or \"string\" or \"null\". ".gettype($val)." given. SQLhanlder.php", 1);
                    }
                    if( $op == '!==' ){ $op = '!='; }
                    if( $op == '==' ){ $op = '='; }

                    if( in_array($op, ['contains', 'not contains']) ){
                        //$where = "(table like '$table.%' && val ".($op=='contains'?'=':'!=')." " . (is_int($val) ? $val : "'".$val."'") . ")";
                        $sql = "select pid from `$table` where val ".($op=='contains'?'=':'!=')." " . (is_int($val) ? $val : "'".$val."'");
                    }
                    elseif( in_array($op, ['like', 'not like']) ){
                        $sql = "select pid from `$table` where val $op '%$val%'";
                    }
                    elseif( $this->isValidDate($val) ){
                        //cast(val AS signed)
                        $sql = "select pid from `$table` where val $op cast('".$val."' as datetime)";
                    }
                    else {
                        $sql = "select pid from `$table` where val $op " . (is_int($val) ? $val : "'".$val."'");
                    }

                    $rows = $this->sqlHanlder->fetchAll($sql);
                    if($rows){
                        $this->pids .= '[';
                        foreach ($rows as $i => $row) {
                            $this->pids .= $row['pid'];
                            if($i < count($rows)-1){
                                $this->pids .= $row['pid'] . ',';
                            }
                        }
                        $this->pids .= ']';
                    }
                    else {
                        $this->pids .= '[]';
                    }
            break;

            /* non-array against array */
            case 'in':
            case 'not in':
                    if( !is_array($val) ){
                        throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"array\". ".gettype($val)." given. Path:\"$table\". SQLhanlder.php", 1);
                    }
                    $sql = "select pid from `$table` where val $op (";
                    $sql .= "'".implode("','", $val)."'";
                    $sql .= ")";
            break;
                
            /* array against array */
            case 'exists in':
            case 'not exists in':

                    if( !is_array($val) ){
                        throw new Exception("Value of the condition operator \"$op_o\" must be of the type \"array\". ".gettype($table)." given. SQLhanlder.php", 1);
                    }

                    $sql = "select pid from `$table` where val ".($op_o=='exists in'?'in':'not in')." (";
                    $sql .= "'".implode("','", $val)."'";
                    $sql .= ")";
            break;
            
            default:
                    throw new Exception("Condition operator \"$op\" is unknown. SQLhanlder.php", 1);
                break;
        }

        return $this->pids;
        return $sql;
    }
    
    private function isValidDate($date, $format='Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}