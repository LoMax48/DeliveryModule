<?php

namespace App\Utils\Transformers;

class CityTransformer
{
    public static function toPickPointFormat(string $city): string
    {
        return str_replace('г. ', '', $city);
    }
}
