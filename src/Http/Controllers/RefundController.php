<?php

namespace TomatoPHP\TomatoInventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ProtoneMedia\Splade\Facades\Toast;
use TomatoPHP\TomatoAdmin\Facade\Tomato;
use TomatoPHP\TomatoInventory\Models\Refund;
use TomatoPHP\TomatoInventory\Models\RefundItem;
use TomatoPHP\TomatoOrders\Models\Order;
use TomatoPHP\TomatoProducts\Models\Product;

class RefundController extends Controller
{
    public string $model;

    public function __construct()
    {
        $this->model = \TomatoPHP\TomatoInventory\Models\Refund::class;
    }

    /**
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        return Tomato::index(
            request: $request,
            model: $this->model,
            view: 'tomato-inventory::refunds.index',
            table: \TomatoPHP\TomatoInventory\Tables\RefundTable::class
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
            model: \TomatoPHP\TomatoInventory\Models\Refund::class,
        );
    }

    /**
     * @return View
     */
    public function create(): View
    {
        return Tomato::create(
            view: 'tomato-inventory::refunds.create',
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
            "order_id" => $request->get('order_id')['id'],
            "total" => collect($request->get('items'))->sum('total'),
            "vat" => collect($request->get('items'))->map(function ($item) {
                return $item['tax'] * $item['qty'];
            })->sum(),
            "discount" => collect($request->get('items'))->map(function ($item) {
                return $item['discount'] * $item['qty'];
            })->sum(),
        ]);

        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'sometimes|exists:branches,id',
            'order_id' => 'nullable|exists:orders,id',
            'status' => 'nullable|max:255|string',
            'notes' => 'nullable|max:65535',
            'items' => "required|array|min:1",
        ]);

        $response = Tomato::store(
            request: $request,
            model: \TomatoPHP\TomatoInventory\Models\Refund::class,
            message: __('Refund updated successfully'),
            redirect: 'admin.refunds.index',
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
                    $item_type = "\Modules\TomatoMaterials\Entities\Material::class";
                    $item_id = $item['item']['id'];
                }
            }
            else {
                $name = $item['item'];
                $type = 'item';
            }


            $response->record->refundItems()->create([
                'item' => $name,
                'qty' => $item['qty'],
                'price' => $item['price'],
                'discount' => $item['discount'],
                'tax' => $item['tax'],
                'total' => $item['total'],
                'type' => $type,
                'item_id' => $item_id??null,
                'item_type' => $item_type??null,
                'options' => $item['options'] ?? null,
                'linked_type' => Order::class,
                'linked_id' => $request->get('order_id')
            ]);
        };

        if($response instanceof JsonResponse){
            return $response;
        }

        return $response->redirect;
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Refund $model
     * @return View|JsonResponse
     */
    public function show(\TomatoPHP\TomatoInventory\Models\Refund $model): View|JsonResponse
    {
        return Tomato::get(
            model: $model,
            view: 'tomato-inventory::refunds.show',
        );
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Refund $model
     * @return View
     */
    public function edit(\TomatoPHP\TomatoInventory\Models\Refund $model): View
    {
        $model->order_id = Order::where('id', $model->order_id )->with('ordersItems')->first();
        $model->items = $model->refundItems()->get()->map(function ($item){
            $item->item = $item->item_type::where('id',$item->item_id)->with('productMetas', function ($q){
                $q->where('key', 'options');
            })->first();

            return $item;
        });

        return Tomato::get(
            model: $model,
            view: 'tomato-inventory::refunds.edit',
        );
    }

    /**
     * @param Request $request
     * @param \TomatoPHP\TomatoInventory\Models\Refund $model
     * @return RedirectResponse|JsonResponse
     */
    public function update(Request $request, \TomatoPHP\TomatoInventory\Models\Refund $model): RedirectResponse|JsonResponse
    {
        $request->merge([
            "user_id" => auth('web')->user()->id,
            "order_id" => $request->get('order_id')['id'],
            "total" => collect($request->get('items'))->sum('total'),
            "vat" => collect($request->get('items'))->map(function ($item) {
                return $item['tax'] * $item['qty'];
            })->sum(),
            "discount" => collect($request->get('items'))->map(function ($item) {
                return $item['discount'] * $item['qty'];
            })->sum(),
        ]);

        $request->validate([
            'items' => "required|array|min:1",
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'sometimes|exists:branches,id',
            'order_id' => 'nullable|exists:orders,id',
            'status' => 'nullable|max:255|string',
            'notes' => 'nullable|max:65535'
        ]);

        $response = Tomato::update(
            request: $request,
            model: $model,
            message: __('Refund updated successfully'),
            redirect: 'admin.refunds.index',
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
                     $item_type = "\Modules\TomatoMaterials\Entities\Material::class";
                     $item_id = $item['item']['id'];
                 }
             }
             else {
                 $name = $item['item'];
                 $type = 'item';
             }

             if(array_key_exists('id', $item)){
                 $refundItem = RefundItem::find($item['id']);
                 $refundItem->update([
                     'item' => $name,
                     'qty' => $item['qty'],
                     'price' => $item['price'],
                     'discount' => $item['discount'],
                     'tax' => $item['tax'],
                     'total' => $item['total'],
                     'type' => $type,
                     'item_id' => $item_id??null,
                     'item_type' => $item_type??null,
                     'options' => $item['options'] ?? null,
                     'linked_type' => Order::class,
                     'linked_id' => $request->get('order_id')
                 ]);
             }
             else {
                 $response->record->refundItems()->create([
                     'item' => $name,
                     'qty' => $item['qty'],
                     'price' => $item['price'],
                     'discount' => $item['discount'],
                     'tax' => $item['tax'],
                     'total' => $item['total'],
                     'type' => $type,
                     'item_id' => $item_id??null,
                     'item_type' => $item_type??null,
                     'options' => $item['options'] ?? null,
                     'linked_type' => Order::class,
                     'linked_id' => $request->get('order_id')
                 ]);
             }
         };

         if($response instanceof JsonResponse){
             return $response;
         }

         return $response->redirect;
    }

    /**
     * @param \TomatoPHP\TomatoInventory\Models\Refund $model
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(\TomatoPHP\TomatoInventory\Models\Refund $model): RedirectResponse|JsonResponse
    {
        $response = Tomato::destroy(
            model: $model,
            message: __('Refund deleted successfully'),
            redirect: 'admin.refunds.index',
        );

        if($response instanceof JsonResponse){
            return $response;
        }

        return $response->redirect;
    }

    public function orders(Request $request){
        $request->validate([
            "search" => "required|string"
        ]);

        $orders = Order::where('uuid','LIKE',"%".$request->get('search')."%")->with('ordersItems')->get();

        foreach ($orders as $order){
            $order->items = $order->ordersItems()->get()->map(function ($item){
                $item->item = Product::where('id',$item->product_id)->with('productMetas', function ($q){
                    $q->where('key', 'options');
                })->first();

                return $item;
            });
        }
        return response()->json($orders);
    }

    public function approve(Refund $model, Request $request)
    {
        $model->is_activated = true;
        $model->save();

        Toast::success(__('Refund Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

    public function status(Request $request, \TomatoPHP\TomatoInventory\Models\Refund $model){
        $request->validate([
            'status' => 'required|string|max:255'
        ]);

        $model->status = $request->get('status');
        $model->save();

        Toast::success(__('Refund Movement Has been updated! with status:') . " " . $model->status)->autoDismiss(2);
        return back();
    }

}
