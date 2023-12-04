<?php

namespace TomatoPHP\TomatoInventory\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool|float checkInventory(\TomatoPHP\TomatoProducts\Models\Product $product, float $qty, array $options = [], bool $isQty = false)
 */
class TomatoInventory extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'tomato-inventory';
    }
}
