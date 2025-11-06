<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('cr_is_numeric_column')) {
    function cr_is_numeric_column($field_name){
        $patterns = ['total','amount','subtotal','tax','price','cost','rate','hours','qty','quantity','progress'];
        foreach($patterns as $p){ if (strpos($field_name, $p) !== false) return true; }
        return false;
    }
}
