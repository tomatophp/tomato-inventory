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
use TomatoPHP\TomatoInventory\Models\Inventory;
use TomatoPHP\TomatoInventory\Models\InventoryItem;
use TomatoPHP\TomatoInventory\Models\InventoryLog;
use TomatoPHP\TomatoInventory\Models\InventoryReport;
use TomatoPHP\TomatoProducts\Models\Product;

class InventoryController extends Controller
{
    public string $model;

    public function __construct()
    {
        $this->model = \TomatoPHP\TomatoInventory\Models\Inventory::class;
    }

    /**
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = Inventory::query();
        $query->where('is_activated', $request->has('is_activated') ?: 0);

        return Tomato::index(
            request: $request,
            model: $this->model,
            view: 'tomato-inventory::inventories.index',
            table: \TomatoPHP\TomatoInventory\Tables\InventoryTable::class,
            query: $query
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function api(Request $request): JsonResponse
    {
        return Tomato::json(
            request: $request,
            model: \TomatoPHP\TomatoInventory\Models\Inventory::class,
        );
    }

    /**
     * @return View
     */
    public function create(): View
    {
        return Tomato::create(
            view: 'tomato-inventory::inventories.create',
        );
    }

    /**
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge([
           "user_id" => auth('web')->user()->id,
            "type" => $request->get('is_transaction') ? 'out' : $request->get('type'),
            "status" => "pending",
            "vat" => collect($request->get('items'))->map(function ($item){
                return $item['tax'] * $item['qty'];
            })->sum(),
            "discount" => collect($request->get('items'))->map(function ($item){
                return $item['discount'] * $item['qty'];
            })->sum(),
            "total" => collect($request->get('items'))->sum('total'),
        ]);
        $request->validate([
            'items' => 'required|array|min:1',
            'uuid' => 'required|unique:inventories,uuid',
            'company_id' => 'nullable|exists:companies,id',
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'nullable|exists:branches,id',
            'type' => 'required|max:255|string',
            'status' => 'required|max:255|string',
            'notes' => 'nullable|max:65535',
            'is_activated' => 'nullable',
            'is_paid' => 'nullable',
            'is_transaction' => 'nullable',
            'vat' => 'nullable',
            'discount' => 'nullable',
            'total' => 'nullable'
        ]);

        $response = Tomato::store(
            request: $request,
            model: \TomatoPHP\TomatoInventory\Models\Inventory::class,
            message: __('Inventory updated successfully'),
            redirect: 'admin.inventories.index',
        );

        foreach ($request->get('items') as $item){
            if(is_array($item['item'])){
                $name = $item['item']['name'][app()->getLocale()];
                $type = isset($item['item']['barcode']) ? 'product' : 'material';
                if($type === 'product'){
                    $item_type = Product::class;
                    $item_id = $item['item']['id'];
                }
                else {
                    $item_type = "\Modules\TomatoProduction\Entities\Material::class";
                    $item_id = $item['item']['id'];
                }
            }
            else {
                $name = $item['item'];
                $type = 'item';
            }

            $response->record->inventoryItems()->create([
                'item_id' => $item_id??null,
                'item_type' => $item_type??null,
                'item' => $name,
                'qty' => $item['qty'],
                'price' => $item['price'],
                'discount' => $item['discount'],
                'tax' => $item['tax'],
                'total' => $item['total'],
                'options' => $item['options'] ?? null,
            ]);

            $checkReport = InventoryReport::where('branch_id', $request->get('branch_id'))
                ->where('item_type', $item_type)
                ->where('item_id', $item_id)
                ->whereJsonContains('options', $model->options ?? null)->first();

            if($checkReport){
                $checkReport->qty += $item['qty'];
                $checkReport->save();

                $log = new InventoryLog();
                $log->user_id = auth('web')->user()->id;
                $log->inventory_id = $response->record->id;
                $log->note = __($name . " " .  __('updated in inventory') . " " . __('with QTY:') . " " . $item['qty'] . " " . __('The Current QTY:') . $checkReport->qty);
                $log->save();
            }
            else {
                $report = new InventoryReport();
                $report->branch_id = $request->get('branch_id');
                $report->item_type = $item_type;
                $report->item_id = $item_id;
                $report->options = $item['options'] ?? null;
                $report->qty = $item['qty'];
                $report->save();

                $log = new InventoryLog();
                $log->user_id = auth('web')->user()->id;
                $log->inventory_id = $response->record->id;
                $log->note = __($name . " " .  __('added to inventory') . " " . __('with QTY:') . " " . $item['qty']);
                $log->save();
            }
        }

        $log = new InventoryLog();
        $log->user_id = auth('web')->user()->id;
        $log->inventory_id = $response->record->id;
        $log->note = __('Inventory Movement Has been saved! with status:') . " " . $response->record->status;
        $log->save();

        if($response instanceof JsonResponse){
            return $response;
        }

        return $response->redirect;
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Inventory $model
     * @return View|JsonResponse
     */
    public function show(\TomatoPHP\TomatoInventory\Models\Inventory $model): View|JsonResponse
    {
        return Tomato::get(
            model: $model,
            view: 'tomato-inventory::inventories.show',
        );
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Inventory $model
     * @return View
     */
    public function edit(\TomatoPHP\TomatoInventory\Models\Inventory $model): View
    {
        $model->items = $model->inventoryItems()->get()->map(function ($item){
            if($item->item_type){
                $item->item = $item->item_type::where('id',$item->item_id)->with('productMetas', function ($q){
                    $q->where('key', 'options');
                })->first();
            }
            return $item;
        });
        return Tomato::get(
            model: $model,
            view: 'tomato-inventory::inventories.edit',
        );
    }

    /**
     * @param Request $request
     * @param \TomatoPHP\TomatoInventory\Models\Inventory $model
     * @return RedirectResponse|JsonResponse
     */
    public function update(Request $request, \TomatoPHP\TomatoInventory\Models\Inventory $model): RedirectResponse|JsonResponse
    {
        $request->merge([
            "user_id" => auth('web')->user()->id,
            "type" => $request->get('is_transaction') ? 'out' : $request->get('type'),
            "vat" => collect($request->get('items'))->map(function ($item){
                return $item['tax'] * $item['qty'];
            })->sum(),
            "discount" => collect($request->get('items'))->map(function ($item){
                return $item['discount'] * $item['qty'];
            })->sum(),
            "total" => collect($request->get('items'))->sum('total'),
        ]);

        $request->validate([
            'items' => 'required|array|min:1',
            'company_id' => 'nullable|exists:companies,id',
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'nullable|exists:branches,id',
            'type' => 'required|max:255|string',
            'status' => 'required|max:255|string',
            'notes' => 'nullable|max:65535',
            'is_activated' => 'nullable',
            'is_paid' => 'nullable',
            'is_transaction' => 'nullable',
            'vat' => 'nullable',
            'discount' => 'nullable',
            'total' => 'nullable'
        ]);

        $response = Tomato::update(
            request: $request,
            model: $model,
            message: __('Inventory updated successfully'),
            redirect: 'admin.inventories.index',
        );

        foreach ($request->get('items') as $item){
            if(is_array($item['item'])){
                $name = $item['item']['name'][app()->getLocale()];
                $type = isset($item['item']['barcode']) ? 'product' : 'material';
                if($type === 'product'){
                    $item_type = Product::class;
                    $item_id = $item['item']['id'];
                }
                else {
                    $item_type = "\Modules\TomatoProduction\Entities\Material::class";
                    $item_id = $item['item']['id'];
                }
            }
            else {
                $name = $item['item'];
                $type = 'item';
            }

            if(array_key_exists('id', $item)){
                $invItem = InventoryItem::find($item['id']);
                $checkReport = InventoryReport::where('branch_id', $request->get('branch_id'))
                    ->where('item_type', $item_type)
                    ->where('item_id', $item_id)
                    ->whereJsonContains('options', $model->options ?? null)->first();
                if($checkReport){
                    $checkReport->qty -= $invItem->qty;
                    $checkReport->save();
                }
                $invItem->update([
                    'item_id' => $item_id??null,
                    'item_type' => $item_type??null,
                    'item' => $name,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'total' => $item['total'],
                    'options' => $item['options'] ?? null,
                ]);

                if($checkReport){
                    $checkReport->qty += $item['qty'];
                    $checkReport->save();

                    $log = new InventoryLog();
                    $log->status = $model->status;
                    $log->user_id = auth('web')->user()->id;
                    $log->inventory_id = $response->record->id;
                    $log->note = __($name . " " .  __('updated in inventory') . " " . __('with QTY:') . " " . $item['qty'] . " " . __('The Current QTY:') . $checkReport->qty);
                    $log->save();
                }
                else {
                    $report = new InventoryReport();
                    $report->branch_id = $request->get('branch_id');
                    $report->item_type = $item_type;
                    $report->item_id = $item_id;
                    $report->options = $item['options'] ?? null;
                    $report->qty = $item['qty'];
                    $report->save();

                    $log = new InventoryLog();
                    $log->status = $model->status;
                    $log->user_id = auth('web')->user()->id;
                    $log->inventory_id = $response->record->id;
                    $log->note = __($name . " " .  __('added to inventory') . " " . __('with QTY:') . " " . $item['qty']);
                    $log->save();
                }
            }
            else {
                $response->record->inventoryItems()->create([
                    'item_id' => $item_id??null,
                    'item_type' => $item_type??null,
                    'item' => $name,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'total' => $item['total'],
                    'options' => $item['options'] ?? null,
                ]);

                $checkReport = InventoryReport::where('branch_id', $request->get('branch_id'))
                    ->where('item_type', $item_type)
                    ->where('item_id', $item_id)
                    ->whereJsonContains('options', $model->options ?? null)->first();

                if($checkReport){
                    $checkReport->qty += $item['qty'];
                    $checkReport->save();

                    $log = new InventoryLog();
                    $log->user_id = auth('web')->user()->id;
                    $log->inventory_id = $response->record->id;
                    $log->status = $model->status;
                    $log->note = __($name . " " .  __('updated in inventory') . " " . __('with QTY:') . " " . $item['qty'] . " " . __('The Current QTY:') . $checkReport->qty);
                    $log->save();
                }
                else {
                    $report = new InventoryReport();
                    $report->branch_id = $request->get('branch_id');
                    $report->item_type = $item_type;
                    $report->item_id = $item_id;
                    $report->options = $item['options'] ?? null;
                    $report->qty = $item['qty'];
                    $report->save();

                    $log = new InventoryLog();
                    $log->user_id = auth('web')->user()->id;
                    $log->status = $model->status;
                    $log->inventory_id = $response->record->id;
                    $log->note = __($name . " " .  __('added to inventory') . " " . __('with QTY:') . " " . $item['qty']);
                    $log->save();
                }
            }
        }

        $log = new InventoryLog();
        $log->user_id = auth('web')->user()->id;
        $log->status = $model->status;
        $log->inventory_id = $response->record->id;
        $log->note = __('Inventory Movement Has been updated! with status:') . " " . $response->record->status;
        $log->save();


        if($response instanceof JsonResponse){
             return $response;
         }

         return $response->redirect;
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Inventory $model
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(\TomatoPHP\TomatoInventory\Models\Inventory $model): RedirectResponse|JsonResponse
    {
        $response = Tomato::destroy(
            model: $model,
            message: __('Inventory deleted successfully'),
            redirect: 'admin.inventories.index',
        );

        if($response instanceof JsonResponse){
            return $response;
        }

        return $response->redirect;
    }

    public function status(Request $request, \TomatoPHP\TomatoInventory\Models\Inventory $model){
        $request->validate([
            'status' => 'required|string|max:255'
        ]);

        $model->status = $request->get('status');
        $model->save();

        $log = new InventoryLog();
        $log->user_id = auth('web')->user()->id;
        $log->inventory_id = $model->id;
        $log->status = $model->status;
        $log->note = __('Inventory Movement Has been updated! with status:') . " " . $model->status;
        $log->save();

        Toast::success(__('Inventory Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

    public function approve(Inventory $model, Request $request)
    {
        $model->status = 'done';
        $model->is_activated = true;
        $model->save();

        foreach ($model->inventoryItems as $item){
            $item->is_activated = true;
            $item->save();

            $checkReport = InventoryReport::where('branch_id', $model->branch_id)
                ->where('item_type', $item->item_type)
                ->where('item_id', $item->item_id)
                ->whereJsonContains('options', $model->options ?? null)->first();

            if($checkReport){
                $checkReport->is_activated = true;
                $checkReport->save();
            }
        }

        $log = new InventoryLog();
        $log->user_id = auth('web')->user()->id;
        $log->inventory_id = $model->id;
        $log->status = $model->status;
        $log->note = __('Inventory Movement Has been updated! with status:') . " " . $model->status;
        $log->save();

        Toast::success(__('Inventory Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

    public function approveItem(InventoryItem $model, Request $request)
    {
        $model->is_activated = true;
        $model->save();

        $checkReport = InventoryReport::where('branch_id', $model->inventory?->branch_id)
            ->where('item_type', $model->item_type)
            ->where('item_id', $model->item_id)
            ->whereJsonContains('options', $model->options ?? null)->first();

        if($checkReport){
            $checkReport->is_activated = true;
            $checkReport->save();
        }

        $log = new InventoryLog();
        $log->user_id = auth('web')->user()->id;
        $log->inventory_id = $model->inventory_id;
        $log->status = $model->inventory->status;
        $log->note = __('Inventory Item Has been updated! with status:') . " " . $model->inventory->status;
        $log->save();

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
            ->where('is_activated', 1)
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
