<?php

namespace TomatoPHP\TomatoInventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ProtoneMedia\Splade\Facades\Toast;
use TomatoPHP\TomatoAdmin\Facade\Tomato;
use TomatoPHP\TomatoEcommerce\Services\Cart\ProductsServices;
use TomatoPHP\TomatoInventory\Facades\TomatoInventory;
use TomatoPHP\TomatoInventory\Models\Inventory;
use TomatoPHP\TomatoInventory\Models\InventoryItem;
use TomatoPHP\TomatoInventory\Models\InventoryLog;
use TomatoPHP\TomatoInventory\Models\InventoryReport;
use TomatoPHP\TomatoOrders\Facades\TomatoOrdering;
use TomatoPHP\TomatoOrders\Models\Order;
use TomatoPHP\TomatoProducts\Models\Product;

class InventoryActionsController extends Controller
{
    public function status(Request $request, \TomatoPHP\TomatoInventory\Models\Inventory $model){
        $request->validate([
            'status' => 'required|string|max:255'
        ]);

        $model->status = $request->get('status');
        $model->save();

        TomatoInventory::log($model->id, __('Inventory Movement Has been updated!'), $model->status);

        Toast::success(__('Inventory Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

    public function approve(Inventory $model, Request $request)
    {
        $model->status = 'done';
        $model->is_activated = true;
        $model->save();

        foreach ($model->inventoryItems as $item){
            if(!$item->is_activated){
                $product = Product::find($item->item_id);

                if($model->type === 'out'){
                    $check = TomatoInventory::checkBranchInventory(
                        productID: $item->item_id,
                        branchID: $model->branch_id,
                        qty: $item->qty,
                        options: $item->options
                    );
                    if(!$check){
                        Toast::danger(__("Sorry This product out of stock"))->autoDismiss(2);
                        return back();
                    }
                }

                $item->is_activated = true;
                $item->save();

                if($model->is_transaction){
                    TomatoInventory::updateQty(
                        productID: $item->item_id,
                        branchID: $model->branch_id,
                        type: 'out',
                        qty: $item->qty,
                        options: $item->options
                    );

                    TomatoInventory::updateQty(
                        productID: $item->item_id,
                        branchID: $model->to_branch_id,
                        type: 'in',
                        qty: $item->qty,
                        options: $item->options
                    );
                }
                else {
                    TomatoInventory::updateQty(
                        productID: $item->item_id,
                        branchID: $model->branch_id,
                        type: $model->type,
                        qty: $item->qty,
                        options: $item->options
                    );
                }



                TomatoInventory::log(
                    inventroyID: $model->id,
                    log: $product->name . " " . __('moved to inventory') . " " . __('with QTY:') . " " . $item->qty,
                    status: $model->status
                );
            }
        }

        if(setting('ordering_active_inventory')){
            if($model->order_id && $model->order->status === setting('ordering_prepared_status')){
                TomatoOrdering::setOrder($model->order)->withdrew();
                TomatoOrdering::setOrder($model->order)->log(__('Order has been ready on the inventory'));
            }
        }

        TomatoInventory::log($model->id, __('Inventory Movement Has been updated!'), $model->status);

        Toast::success(__('Inventory Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

    public function approveItem(InventoryItem $model, Request $request)
    {
        if($model->inventory?->type === 'out'){
            $check = TomatoInventory::checkBranchInventory($model->item_id, $model->inventory?->branch_id, $model->qty, $model->options);
            if(!$check){
                Toast::danger(__("Sorry This product out of stock"))->autoDismiss(2);
                return back();
            }
        }

        $product = Product::find($model->item_id);


        $model->is_activated = true;
        $model->save();

        if($model->is_transaction){
            TomatoInventory::updateQty(
                productID: $model->item_id,
                branchID: $model->inventory?->branch_id,
                type: 'out',
                qty: $model->qty,
                options: $model->options
            );

            TomatoInventory::updateQty(
                productID: $model->item_id,
                branchID: $model->inventory?->branch_id,
                type: 'in',
                qty: $model->qty,
                options: $model->options
            );
        }
        else {
            TomatoInventory::updateQty(
                productID: $model->item_id,
                branchID: $model->inventory?->branch_id,
                type: $model->inventory?->type,
                qty: $model->qty,
                options: $model->options
            );
        }



        TomatoInventory::log(
            inventroyID: $model->inventory?->id,
            log: $product->name . " " . __('moved to inventory') . " " . __('with QTY:') . " " . $model->qty,
            status: $model->inventory?->status
        );

        TomatoInventory::log(
            $model->inventory?->id,
            __('Inventory Item Has been updated!'),
             $model->inventory?->status,
        );

        Toast::success(__('Inventory Item Has been updated! with status:') . " " . $model->inventory->status)->autoDismiss(2);
        return back();
    }

    public function barcodes(){
        return view('tomato-inventory::inventories.barcode');
    }

    public function barcodesPrint(Request $request){
        $request->validate([
            "product_id" => "required|array",
            "product_id*id" => "required|exists:products,id",
            "options" => "nullable|array",
            "qty" => "required|numeric|min:1"
        ]);

        $options = "";
        if($request->get('options') && count($request->get('options'))){
            foreach ($request->get('options') as $key=>$option) {
                $options.= $option.'-';
            }
        }
        $product= Product::find($request->get('product_id')['id']);

        if($product){
            $price = ProductsServices::getProductPrice($product->id, $request->get('options'));
            $barcode = $product->sku . '-'.$options.$price->collect();
            return view('tomato-inventory::inventories.barcode-print', [
                "barcode" => $product->barcode,
                "text" => $barcode,
                "qty" => $request->get('qty')
            ]);
        }
        else {
            return back();
        }
    }

    public function report(){
        return view('tomato-inventory::inventories.report');
    }

    public function reportData(Request $request){
        $request->validate([
            "branch_id" => "required|exists:branches,id",
            "product_id" => "required|array",
            "options" => "nullable|array"
        ]);

        $report = InventoryReport::where('branch_id', $request->get('branch_id'))
            ->where('item_id', $request->get('product_id'))
            ->where('item_type', Product::class)->get()->map(function ($item){
                $item->product  = $item->item_type::find($item->item_id);
                return $item;
            });

        if($report){
            return response()->json([
                "data" => $report,
            ]);
        }
    }

    public function import(){
        return view('tomato-inventory::inventories.import');
    }

    public function printIndex(Request $request){
        $query = Inventory::query();
        $query->where('is_activated', $request->has('is_activated') ?: 0);
        $inventory = $query->with('inventoryItems')->get();
        return view('tomato-inventory::inventories.print', [
            'inventory' => $inventory
        ]);
    }
}
