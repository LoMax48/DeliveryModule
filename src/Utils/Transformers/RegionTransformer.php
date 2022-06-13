<?php

namespace App\Utils\Transformers;

class RegionTransformer
{
    public static function toPickPointFormat(string $region): string
    {
        if ($region === 'Москва город') {
            $region = 'Московская обл.';
        }
        if ($region === 'Севастополь город') {
            $region = 'Крым респ.';
        }
        if ($region === 'Санкт-Петербург город') {
            $region = 'Ленинградская обл.';
        }

        $region = str_replace(array('область', 'Республика'), array('обл.', 'респ.'), $region);
        $region = ucfirst(strtolower($region));

        return $region;
    }
}
