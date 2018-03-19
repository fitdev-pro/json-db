<?php

namespace FitdevPro\JsonDb;

/**
 * Class Database
 * @package Infrastructure\JsonDb
 */
class Database
{
    protected $fileSystem;

    private $tablse = [];


    public function __construct(IFileSystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    public function getTable(string $name): Table
    {
        if (!isset($this->tablse[$name])) {
            $this->tablse[$name] = new Table($this->fileSystem, $name);
        }

        return $this->tablse[$name];
    }
}
