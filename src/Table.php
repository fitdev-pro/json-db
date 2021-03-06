<?php

namespace FitdevPro\JsonDb;

use FitdevPro\JsonDb\Exceptions\NotFoundException;
use FitdevPro\JsonDb\Exceptions\WriteException;

class Table
{
    /**
     * @var Database
     */
    protected $db;

    /**
     * @var string
     */
    protected $path;

    /**
     * Table constructor.
     * @param Database $db
     * @param string $path
     * @throws WriteException
     */
    public function __construct(Database $db, string $path)
    {
        $this->db = $db;

        $this->path = (string)rtrim($path, '/') . '/';

        $this->db->createTableIfNotExists($this->path);
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
     * Find data matching key-value map or callback
     *
     * @param array|string|callable $where
     * @param bool $first
     * @return array|object
     * @throws NotFoundException
     */
    public function find($where = [], $first = false)
    {
        if (is_callable($where)) {
            $out = $this->filterCallable($where, $first);
        } elseif (is_string($where) || is_int($where)) {
            $out = $this->findById($where);
            $out = [$out['id']=>$out];
        } else {
            $out = $this->filterArray($where, $first);
        }

        if($first){
            if(empty($out)){
                throw new NotFoundException('');
            }

            return current($out);
        }

        return $out;
    }

    /**
     * @param $where
     * @param $first
     * @return array
     * @throws NotFoundException
     */
    private function filterCallable($where, $first)
    {
        $results = [];
        foreach ($this->db->readAll($this->path) as $id) {
            $data = $this->findById($id);

            if ($where($data)) {
                if ($first) {
                    return [$data['id']=>$data];
                }
                $results[$id] = $data;
            }
        }

        return $results;
    }

    /**
     * @param $id
     * @return array
     * @throws NotFoundException
     */
    private function findById($id)
    {
        return $this->db->read($this->path . $id);
    }

    /**
     * @param array $where
     * @param bool $first
     * @return array
     * @throws NotFoundException
     */
    private function filterArray($where, $first)
    {
        $results = [];

        foreach ($this->db->readAll($this->path) as $id)
        {
            $data = $this->findById($id);

            $match = true;
            foreach ($where as $key => $value) {
                if (!isset($data[$key]) || (isset($data[$key]) && $data[$key] !== $value)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                if ($first) {
                    return [$data['id']=>$data];
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
            $data['id'] = $this->db->getNextId($this->path);
        }

        return $this->db->save($this->path . $data['id'], json_encode($data));
    }

    /**
     * @param $where
     * @return bool
     * @throws NotFoundException
     * @throws WriteException
     */
    public function delete($where)
    {
        $data = $this->find($where);

        if(isset($data['id'])){
            $data = [$data['id'] => $data];
        }

        $results = false;
        foreach ($data as $row){
            $results[$row['id']] = $this->db->delete($this->path . $row['id']);
        }

        return $results;
    }

    /**
     * @param $repairCallable
     * @throws NotFoundException
     */
    public function repair($repairCallable)
    {
        foreach ($this->db->readAll($this->path) as $id){
            $data = $this->findById($id);

            $repairCallable($this, $data);
        }
    }
}
