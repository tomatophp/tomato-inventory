<?php

namespace TomatoPHP\TomatoInventory\Import;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ImportInventory implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        //
    }
}
