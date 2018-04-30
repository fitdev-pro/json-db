<?php

namespace FitdevPro\JsonDb;
use FitdevPro\JsonDb\Exceptions\NotFoundException;
use FitdevPro\JsonDb\Exceptions\WriteException;

/**
 * Class Database
 * @package Infrastructure\JsonDb
 */
class Database
{
    protected $fileSystem;

    private $tables = [];
    private $transaction = 0;
    private $transactionData = [];
    private $transactionCache = [];


    public function __construct(IFileSystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    public function getTable(string $name): Table
    {
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = new Table($this, $name);
        }

        return $this->tables[$name];
    }

    public function begin()
    {
        $this->transaction++;
    }

    public function commit()
    {
        if($this->transaction > 1){
            $this->transaction--;
        }else{
            foreach ($this->transactionData as $data){
                if($data['type'] == 'put'){
                    $this->fileSystem->put($data['path'], $data['value']);
                }elseif($data['type'] == 'delete'){
                    $this->fileSystem->delete($data['path']);
                }
            }
        }
    }

    public function rollback()
    {
        $this->transaction = 0;
        $this->transactionData = [];
    }

    public function createTableIfNotExists($path)
    {
        if (!$this->fileSystem->has($path)) {
            $this->fileSystem->createDir($path);
        }
    }

    /**
     * @param $path
     * @return mixed
     * @throws NotFoundException
     */
    public function read($path){
        if(isset($this->transactionCache[$path])){
            $data = $this->transactionData[$path];

            if(is_null($data)){
                throw new NotFoundException($path);
            }
        }
        elseif ($this->fileSystem->has($path)) {
            $data = $this->fileSystem->read($path);
        }
        else{
            throw new NotFoundException($path);
        }

        return json_decode($data, true);
    }

    /**
     * @param $path
     * @return array
     * @throws NotFoundException
     */
    public function readAll($path)
    {
        $out = [];

        if(!$this->fileSystem->isDir($path)){
            throw new NotFoundException($path);
        }

        foreach($this->fileSystem->dirContent($path) as $fileInfo){
            if($fileInfo['type'] != 'file' || $fileInfo['basename'] == '.auto'){
                continue;
            }

            $out[] = $fileInfo['basename'];
        }

        return $out;
    }

    /**
     * @param $path
     * @return int
     * @throws WriteException
     */
    public function getNextId($path){
        $path = $path. '.auto';

        if($this->transaction > 0 && isset($this->transactionCache[$path])){
            $id = $this->transactionData[$path];
        }
        elseif($this->fileSystem->has($path)){
            $id = $this->fileSystem->read($path);
        }
        else{
            $id = 0;
        }

        $id++;

        if($this->transaction > 0){
            $this->transactionData[] = ['type'=>'put', 'path'=>$path, 'value'=>$id];
            $this->transactionCache[$path] = $id;
        }else{
            $writeAutoIncrementResult = $this->fileSystem->put($path, $id);

            if(!$writeAutoIncrementResult){
                throw new WriteException('Failed write autoincrement.');
            }
        }

        return $id;
    }

    public function save($path, $data)
    {
        if($this->transaction > 0){
            $this->transactionData[] = ['type'=>'put', 'path'=>$path, 'value'=>$data];
            $this->transactionCache[$path] = $data;
            return true;
        }else{
            return $this->fileSystem->put($path, $data);
        }
    }

    public function delete($path)
    {
        if($this->transaction > 0){
            $this->transactionData[] = ['type'=>'delete', 'path'=>$path];
            $this->transactionCache[$path] = null;
            return true;
        }else{
            return $this->fileSystem->delete($path);
        }
    }
}
