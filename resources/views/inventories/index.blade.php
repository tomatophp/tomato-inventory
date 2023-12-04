<x-tomato-admin-layout>
    <x-slot:header>
        {{ __('Inventory') }}
    </x-slot:header>
    <x-slot:buttons>
        <x-tomato-admin-button class="w-full" :href="route('admin.inventories.create')" type="link">
            {{__('Add Items')}}
        </x-tomato-admin-button>
    </x-slot:buttons>

    <div class="pb-12">
        <div class="mx-auto">
            <x-splade-table :for="$table" striped>
                <x-slot:actions>
                    @if(request()->is_activated === 'true')
                        <x-tomato-admin-table-action href="{{route('admin.inventories.index')}}" secondary icon="bx bx-home">
                            {{__('Inventory Home')}}
                        </x-tomato-admin-table-action>
                    @else
                        <x-tomato-admin-table-action href="{{route('admin.inventories.index') . '?is_activated=true'}}" secondary icon="bx bx-history">
                            {{__('Inventory History')}}
                        </x-tomato-admin-table-action>
                    @endif
                    <x-tomato-admin-table-action :href="route('admin.inventories.print')" secondary icon="bx bx-printer">
                        {{__('Print Inventory Report')}}
                    </x-tomato-admin-table-action>
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.report')" secondary icon="bx bx-chart">
                        {{__('Product Inventory Report')}}
                    </x-tomato-admin-table-action>
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.barcodes')" secondary icon="bx bx-barcode">
                        {{__('Print Product Barcodes')}}
                    </x-tomato-admin-table-action>
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.import')" secondary icon="bx bx-import">
                        {{__('Import Inventory')}}
                    </x-tomato-admin-table-action>
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.import')" secondary icon="bx bxs-file-pdf">
                        {{__('Export PDF')}}
                    </x-tomato-admin-table-action>
                </x-slot:actions>
                <x-splade-cell items>
                    <table class="border min-w-full divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-700">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                                @foreach($item->inventoryItems as $invItem)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-600">
                                        <td class="border p-2">
                                            <div>{{$invItem->item}}</div>
                                            <div class="text-gray-400 flex justify-start gap-2">
                                                @foreach($invItem->options ?? [] as $option)
                                                    <div>
                                                        {{ str($option)->upper() }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="border p-2 font-bold">{{$invItem->qty}}</td>
                                        @if($item->status !== 'canceled')
                                        <td class="border p-2">
                                            @if(!$invItem->is_activated)
                                            <x-tomato-admin-tooltip text="{{__('Approve Item')}}">
                                                <x-splade-link confirm href="{{route('admin.inventories.approve.item', $invItem->id)}}" method="POST">
                                                    <i class="bx bx-check-circle text-primary-500"></i>
                                                </x-splade-link>
                                            </x-tomato-admin-tooltip>
                                            @else
                                                <x-tomato-admin-tooltip text="{{__('Item Approved')}}">
                                                    <i class="bx bx-check text-success-500"></i>
                                                </x-tomato-admin-tooltip>
                                            @endif
                                        </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </x-splade-cell>
                <x-splade-cell status>
                    <x-splade-form confirm method="POST" class="w-full" action="{{route('admin.orders.status', $item->id)}}" :default="$item" submit-on-change>
                        <x-splade-select class="w-32" :disabled="$item->status === 'canceled' || $item->status === 'done'" name="status" placeholder="{{__('Status')}}" >
                            <option value="pending">{{__('Pending')}}</option>
                            <option value="not-available">{{__('Not Available')}}</option>
                            <option value="part-available">{{__('Part Available')}}</option>
                            <option value="canceled">{{__('Canceled')}}</option>
                            <option value="done">{{__('Done')}}</option>
                        </x-splade-select>
                    </x-splade-form>
                </x-splade-cell>
                <x-splade-cell is_activated>
                    <x-tomato-admin-row table type="bool" :value="$item->is_activated" />
                </x-splade-cell>
                <x-splade-cell order.uuid>
                    <x-tomato-admin-row table  :value="$item->order?->uuid" />
                </x-splade-cell>
                <x-splade-cell total>
                    <x-tomato-admin-row table  :value="dollar($item->total)" />
                </x-splade-cell>


                <x-splade-cell actions>
                    <div class="flex justify-start">
                        @if(!$item->is_activated)
                        <x-tomato-admin-button confirm method="POST" type="icon" title="{{__('Approve All')}}" :href="route('admin.inventories.approve', $item->id)">
                            <x-heroicon-s-check-circle class="h-6 w-6"/>
                        </x-tomato-admin-button>
                        @endif
                        <x-tomato-admin-button success type="icon" title="{{trans('tomato-admin::global.crud.view')}}" :href="route('admin.inventories.show', $item->id)">
                            <x-heroicon-s-eye class="h-6 w-6"/>
                        </x-tomato-admin-button>
                        @if($item->status !== 'canceled' && $item->status !== 'done')
                        <x-tomato-admin-button warning type="icon" title="{{trans('tomato-admin::global.crud.edit')}}" :href="route('admin.inventories.edit', $item->id)">
                            <x-heroicon-s-pencil class="h-6 w-6"/>
                        </x-tomato-admin-button>
                        <x-tomato-admin-button danger type="icon" title="{{trans('tomato-admin::global.crud.delete')}}" :href="route('admin.inventories.destroy', $item->id)"
                           confirm="{{trans('tomato-admin::global.crud.delete-confirm')}}"
                           confirm-text="{{trans('tomato-admin::global.crud.delete-confirm-text')}}"
                           confirm-button="{{trans('tomato-admin::global.crud.delete-confirm-button')}}"
                           cancel-button="{{trans('tomato-admin::global.crud.delete-confirm-cancel-button')}}"
                           method="delete"
                        >
                            <x-heroicon-s-trash class="h-6 w-6"/>
                        </x-tomato-admin-button>
                            @endif
                    </div>
                </x-splade-cell>
            </x-splade-table>
        </div>
    </div>
</x-tomato-admin-layout>
