<?php

namespace FitdevPro\JsonDb;


interface IFileSystem
{
    function has($path) : bool;
    function read($path);
    function put($path, $data);
    function delete($path);
    function createDir($path);
    function isDir($path) : bool;
    function dirContent($path);
}