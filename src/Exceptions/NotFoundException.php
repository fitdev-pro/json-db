<?php

namespace FitdevPro\JsonDb\Exceptions;

class NotFoundException extends JsonDbException
{

    /**
     * NotFoundException constructor.
     * @param $path
     */
    public function __construct($path)
    {
        $this->message = 'Nie udało się znaleźć wyszukiwanego wpisu ' . $path;
    }
}
