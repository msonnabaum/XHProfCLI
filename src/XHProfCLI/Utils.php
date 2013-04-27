<?php

namespace XHProfCLI;

class Utils {
  static function quantile($values, $p) {
    sort($values);
    $H = (count($values) - 1) * $p + 1;
    $h = floor($H);
    $v = $values[$h - 1];
    $e = $H - $h;
    return $e ? $v + $e * ($values[$h] - $v) : $v;
  }

  static function quartile($array, $quartile) {
    sort($array);
    $ninety_fifth = $array[round(($quartile/100) * count($array) - .5)];
    return $ninety_fifth;
  }

}

