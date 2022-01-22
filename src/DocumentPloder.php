<?php
namespace DovStone\MyNoSQL;

use DovStone\MyNoSQL\DocumentPloder;

class DocumentPloder
{
    public function __construct()
    {
    }
    
    public function explode($document)
    {
        if($document){
            $ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($document));
            $newArray = [];
            foreach ($ritit as $leafValue) {
                $keys = [];
                foreach (range(0, $ritit->getDepth()) as $depth) {
                    $keys[] = $ritit->getSubIterator($depth)->key();
                }
                $newArray[ join('.', $keys) ] = $leafValue;
            }
            return $newArray;
        }
        return null;
    }
    
    public function implode($array)
    {
        if($array){
            $newArray = [];
            foreach($array as $key => $value) {
                if(is_array($value)){
                    $value = $this->implode($value);
                }
                $parts = explode(".", $key);
                $pointer = &$newArray;
                foreach ($parts as $part) {
                    $pointer = &$pointer[$part];
                }
                $pointer = $value;
            }
            $newArray['_id'] = (int) $newArray['pid'];
            unset($newArray['id']);
            unset($newArray['pid']);
            return $newArray;
        }
        return null;
    }
}