<?php

namespace FitdevPro\JsonDb\Exceptions;

class NotFoundException extends JsonDbException
{

    /**
     * NotFoundException constructor.
     * @param $table
     * @param $id
     */
    public function __construct($table, $id)
    {
        $this->message = 'Nie udało się znaleźć wyszukiwanego wpisu w tabelce ' . $table . ' o id = ' . $id;
    }
}
