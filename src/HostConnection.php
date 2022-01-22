<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\QueryBuilder;
use DovStone\MyNoSQL\Exception;

class HostConnection extends QueryBuilder
{
    protected $pdo;

    public function __construct($mysql = 'mysql:host=localhost;dbname=test', $user='root', $pass='')
    {
        try {
            $pdo = new \PDO( $mysql, $user, $pass, [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ]);
            $pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
            parent::__construct($pdo);
        } catch (PDOException $e) {
            throw new Exception($e);
        }
    }
}