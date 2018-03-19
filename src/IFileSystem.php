<?php

namespace FitdevPro\JsonDb;


interface IFileSystem
{
    function has($path);
    function read($path);
    function put($path, $data);
    function delete($path);
    function createDir($path);
    function dirContent($path);
}