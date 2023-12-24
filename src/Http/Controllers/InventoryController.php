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
            query: $query,
            filters: [
                "order_id",
                "branch_id",
                "status"
            ]
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
            filters: [
                "order_id",
                "branch_id",
                "status"
            ]
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
            'items' => ['required','array','min:1', function($attribute, $value, $fail) use ($request){
                if($request->get('type') === 'out'){
                    foreach ($request->get('items') as $item){
                        $ckeckQTY = TomatoInventory::checkBranchInventory($item['item']['id'], $request->get('branch_id'), $item['qty'], $item['options']??[]);
                        if(!$ckeckQTY){
                            $fail(__('Sorry The Product') . ': ' . $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en']  . ' '. __('Do Not have this QTY'));
                        }
                        else {
                            $checkIfExists = TomatoInventory::checkInventoryItemQty($item['item']['id'], $request->get('branch_id'), $item['qty'], $item['options']??[]);
                            if(!$checkIfExists){
                                $fail(__('Sorry The Product') . ': ' . $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en']  . ' '. __('Has Pending QTY on the Inventory Movement'));
                            }
                        }
                    }
                }
            }],
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
                $name = $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en'] ;
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
                'options' => $item['options'] ?? [],
            ]);
        }

        TomatoInventory::log(
            inventroyID: $response->record->id,
            log: __('Inventory Movement Has been saved!'),
            status: $response->record->status
        );

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
            'items' => ['required','array','min:1', function($attribute, $value, $fail) use ($request, $model){
                if($request->get('type') === 'out'){
                    foreach ($request->get('items') as $item){
                        $ckeckQTY = TomatoInventory::checkBranchInventory($item['item']['id'], $request->get('branch_id'), $item['qty'], $item['options']??[]);
                        if(!$ckeckQTY){
                            $fail(__('Sorry The Product') . ': ' . $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en']  . ' '. __('Do Not have this QTY'));
                        }
                        else {
                            $checkIfExists = TomatoInventory::checkInventoryItemQty($item['item']['id'], $request->get('branch_id'), $item['qty'], $item['options']??[], $model->id);
                            if(!$checkIfExists){
                                $fail(__('Sorry The Product') . ': ' . $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en']  . ' '. __('Has Pending QTY on the Inventory Movement'));
                            }
                        }
                    }
                }
            }],
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
                $name = $item['item']['name'][app()->getLocale()] ?? $item['item']['name']['en'] ;
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
                $invItem->update([
                    'item_id' => $item_id??null,
                    'item_type' => $item_type??null,
                    'item' => $name,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'total' => $item['total'],
                    'options' => $item['options'] ?? [],
                ]);
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
                    'options' => $item['options'] ?? [],
                ]);
            }
        }

        TomatoInventory::log(
            inventroyID: $model->id,
            log: __('Inventory Movement Has been updated!'),
            status: $model->status
        );

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
        $model->inventoryItems()->delete();

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


    public function print(\TomatoPHP\TomatoInventory\Models\Inventory $model)
    {
        return view('tomato-inventory::inventories.show-print', compact('model'));
    }
}
