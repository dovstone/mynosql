# MyNoSQL
MyNoSQL is a NoSql database approach based on MySQL

require __DIR__.'/vendor/autoload.php';

# index.php
```
```use DovStone\MyNoSQL\HostConnection;
```
```$db = new HostConnection('mysql:host=localhost;dbname=mynosql');
```


# Dictionnary

$document  = $db->collection(collection_name)->insert(array $criteria);

$document  = $db->collection(collection_name)->update(int $documentId, array $newDocument);

             $db->collection(collection_name)->delete(int $documentId);

$document  = $db->collection(collection_name)->find(int $documentId)->fetch();

$document  = $db->collection(collection_name)->findOneBy(array $criteria)->fetch();

$documents = $db->collection(collection_name)->findBy(array $criteria, array $orderBy, int $limit, int $offset)->fetch();

$documents = $db->collection(collection_name)->findAllBy(array $criteria, array $orderBy)->fetch();

$documents = $db->collection(collection_name)->findAll(array $orderBy)->fetch();

$count     = $db->collection(collection_name)->count();

$count     = $db->collection(collection_name)->countBy(array $criteria);