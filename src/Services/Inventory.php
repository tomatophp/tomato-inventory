<?php

namespace TomatoPHP\TomatoInventory\Services;

use Illuminate\Support\Str;
use TomatoPHP\TomatoProducts\Models\Product;

class Inventory
{
    public function checkInventory(Product $product,float $qty,array $options=[], bool $isQty=false): bool|float
    {
        if(count($options)){
            if($product->meta('qty')){
                foreach($product->meta('qty') as $key=>$item){
                    if(Str::of($key)->containsAll(array_merge($options, ['qty']))){
                        if((float)$item >= $qty){
                            if($isQty){
                                return (float)$item;
                            }
                            else {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        if($product->has_unlimited_stock){
            if($isQty){
                return 1000000000.00;
            }
            else {
                return true;
            }
        }

        if((float)$product->meta('stock') >= $qty){
            if($isQty){
                return (float)$product->meta('stock');
            }
            else {
                return true;
            }
        }

        if($isQty){
            return 0;
        }
        else {
            return false;
        }

    }
}
