<?php

function array_flatten(array $array)
{
    return iterator_to_array(
        new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array)));
}