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

    public function createTableIfNotExists($path)
    {
        if (!$this->fileSystem->has($path)) {
            if (!$this->fileSystem->createDir($path)) {
                throw new WriteException('Failed to create dir for table: ' . $path);
            }
        }
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
            $this->clearTransaction();
        }
    }

    private function clearTransaction()
    {
        $this->transaction = 0;
        $this->transactionData = [];
        $this->transactionCache = [];
    }

    public function rollback()
    {
        $this->clearTransaction();
    }

    /**
     * @param $path
     * @return mixed
     * @throws NotFoundException
     */
    public function read($path): array
    {
        if ($this->transaction > 0 && (isset($this->transactionCache[$path]) && (is_string($this->transactionCache[$path]) || is_null($this->transactionCache[$path])))) {
            $data = $this->transactionCache[$path];

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
    public function readAll($path): array
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
        $path = $path . '/.auto';

        if ($this->fileSystem->has($path)) {
            $id = $this->fileSystem->read($path);
        }
        else{
            $id = 0;
        }

        $id++;

        if (!$this->fileSystem->put($path, $id)) {
            throw new WriteException('Failed write autoincrement.');
        }

        return $id;
    }

    public function save(string $path, $data)
    {
        $data = json_encode($data);

        if($this->transaction > 0){
            $this->transactionData[] = ['type'=>'put', 'path'=>$path, 'value'=>$data];
            $this->transactionCache[$path] = $data;
            return true;
        }else{
            if (!$this->fileSystem->put($path, $data)) {
                throw new WriteException('Failed write file: ' . $path . ' with data: ' . $data);
            }

            return true;
        }
    }

    public function delete($path)
    {
        if($this->transaction > 0){
            $this->transactionData[] = ['type'=>'delete', 'path'=>$path];
            $this->transactionCache[$path] = null;
            return true;
        }else{
            if (!$this->fileSystem->delete($path)) {
                throw new WriteException('Failed deleting file: ' . $path);
            }
        }
    }
}
