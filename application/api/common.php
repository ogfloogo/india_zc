<?php

function buildMobile()
{
    $my_array = array("70", "80", "81", "90");
    $length = count($my_array) - 1;
    $hd = rand(0, $length);
    $begin = $my_array[$hd];
    $a = rand(10, 99);
    $b = rand(100, 999);
    return $begin . $a . '****' . $b;
}