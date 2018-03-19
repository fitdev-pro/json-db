<?php

namespace FitdevPro\JsonDb;

use FitdevPro\JsonDb\Exceptions\NotFoundException;
use FitdevPro\JsonDb\Exceptions\WriteException;

class Table
{
    /**
     * @var IFileSystem
     */
    protected $fileSystem;

    /**
     * @var string
     */
    protected $path;

	/**
	 * Table constructor.
	 *
	 * @param IFileSystem $fileSystem
	 * @param $path
	 */
    public function __construct(IFileSystem $fileSystem, string $path)
    {
        $this->fileSystem = $fileSystem;

        $this->path = (string)rtrim($path, '/') . '/';

        $this->makeDir();
    }

	private function makeDir()
	{
		if (!$this->fileSystem->has($this->path)) {
			$this->fileSystem->createDir($this->path);
		}
	}

    /**
     * Find data matching key-value map or callback
     *
     * @param array|string|callable $where
     * @param bool $first
     * @return array|object
     * @throws NotFoundException
     */
	public function find($where = [], $first = false)
	{
        if (!is_array($where)) {
			return $this->findById($where);
		}
		else if (is_callable($where)) {
			return $this->filterCallable($where, $first);
        } else {
			return $this->filterArray($where, $first);
		}
	}

    /**
     * Find first item key-value map or callback
     *
     * @param null $where
     * @return array
     * @throws NotFoundException
     */
	public function findFirst($where = null)
	{
		return $this->find($where, true);
	}

    /**
     * @param $id
     * @return bool|mixed
     * @throws NotFoundException
     */
    private function findById($id)
    {
        if ($this->fileSystem->has($this->path . $id)) {
            $data = $this->fileSystem->read($this->path . $id);
            return json_decode($data, true);
        }

        throw new NotFoundException($this->path, $id);
    }

    /**
     * @param $where
     * @param $first
     * @return array|bool|mixed
     * @throws NotFoundException
     */
	private function filterArray($where, $first)
	{
		$results = [];

		foreach ($this->fileSystem->dirContent($this->path) as $fileInfo)
		{
		    if($fileInfo['type'] != 'file' || $fileInfo['basename'] == '.auto'){
		        continue;
            }

			$id = $fileInfo['basename'];
			$data = $this->findById($id);

			$match = true;
			foreach ($where as $key => $value) {
				if (@$data[$key] !== $value) {
					$match = false;
					break;
				}
			}
			if ($match) {
				if ($first) {
					return $data;
				}
				$results[$id] = $data;
			}
		}

		return $results;
	}

    /**
     * @param $where
     * @param $first
     * @return array|bool|mixed
     * @throws NotFoundException
     */
	private function filterCallable($where, $first)
	{
		$results = [];
		foreach ($this->fileSystem->dirContent($this->path) as $fileInfo)
		{
            if($fileInfo['type'] != 'file' || $fileInfo['basename'] == '.auto'){
                continue;
            }

            $id = $fileInfo['basename'];
			$data = $this->findById($id);
			if ($where($data)) {
				if ($first) {
					return $data;
				}
				$results[$id] = $data;
			}
		}

		return $results;
	}

    /**
     * @param $data
     * @return bool
     * @throws WriteException
     */
    public function save($data)
    {
        if (!isset($data['id']) || (!is_string($data['id']) && !is_int($data['id']))) {
            if($this->fileSystem->has($this->path . '.auto')){
                $id = $this->fileSystem->read($this->path . '.auto') + 1;
            }else{
                $id = 1;
            }

            $writeAutoIncrementResult = $this->fileSystem->put($this->path . '.auto', $id);
            if(!$writeAutoIncrementResult){
                throw new WriteException('Failed write autoincrement');
            }
            $data['id'] = $id;
        }

	    return $this->fileSystem->put($this->path . $data['id'], json_encode($data));
    }

    /**
     * @param $where
     * @return bool
     * @throws NotFoundException
     */
    public function delete($where)
    {
    	$data = $this->find($where);

    	if(isset($data['id'])){
    	    $data = [$data['id'] => $data];
        }

	    $results = false;
    	foreach ($data as $row){
		    $results[$row['id']] = $this->fileSystem->delete($this->path . $row['id']);
	    }

	    return $results;
    }

    /**
     * @param $repairCallable
     * @throws NotFoundException
     */
	public function repair($repairCallable)
	{
		foreach ($this->fileSystem->dirContent($this->path) as $fileInfo){
            if($fileInfo['type'] != 'file' || $fileInfo['basename'] == '.auto'){
                continue;
            }

            $id = $fileInfo['basename'];
			$data = $this->findById($id);

			$repairCallable($this, $data);
		}
	}
}
