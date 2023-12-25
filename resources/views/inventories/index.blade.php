<x-tomato-admin-layout>
    <x-slot:header>
        @isset($history)
            {{ __('Inventory') }}
        @else
            {{ __('Pending Inventory') }}
        @endisset
    </x-slot:header>
    <x-slot:buttons>
        <x-tomato-admin-button class="w-full" :href="route('admin.inventories.create')" type="link">
            {{__('Add Items')}}
        </x-tomato-admin-button>
        <x-tomato-admin-button warning :modal="true" :href="route('admin.inventories.import')" type="link">
            <x-tomato-admin-tooltip :text="__('Import Inventory')">
                <i class="bx bx-import"></i>
            </x-tomato-admin-tooltip>
        </x-tomato-admin-button>
    </x-slot:buttons>
    <x-slot:icon>
        @isset($history)
            bx bx-building-house
        @else
            bx bx-pause-circle
        @endisset
    </x-slot:icon>

    <div class="pb-12">
        <div class="mx-auto">
            <x-splade-table :for="$table" striped>
                <x-slot:actions>
                    @can('admin.inventories.print')
                    <a href="{{route('admin.inventories.print') . '?history='}}{{isset($history) ? '1' : '0'}}" target="_blank" class="text-left w-full px-4 py-2 text-sm font-normal text-gray-700 dark:text-white dark:hover:bg-gray-600 hover:bg-gray-50 hover:text-gray-900">
                        <div class="flex justify-start gap-2">
                            <div class="flex flex-col justify-center items-center">
                                <i class="bx bx-printer"></i>
                            </div>
                            <div>  {{__('Print Inventory Report')}} </div>
                        </div>
                    </a>
                    @endcan
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.report')" secondary icon="bx bx-chart">
                        {{__('Product Inventory Report')}}
                    </x-tomato-admin-table-action>
                    <x-tomato-admin-table-action modal :href="route('admin.inventories.barcodes')" secondary icon="bx bx-barcode">
                        {{__('Print Product Barcodes')}}
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
                                            @can('admin.inventories.approve.item')
                                                <x-tomato-admin-tooltip text="{{__('Approve Item')}}">
                                                    <x-splade-link confirm href="{{route('admin.inventories.approve.item', $invItem->id)}}" method="POST">
                                                        <i class="bx bx-check-circle text-primary-500"></i>
                                                    </x-splade-link>
                                                </x-tomato-admin-tooltip>
                                                @else
                                                <x-tomato-admin-tooltip text="{{__('Item Approved')}}">
                                                    <i class="bx bx-x text-danger-500"></i>
                                                </x-tomato-admin-tooltip>
                                            @endcan
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
                    <x-splade-form confirm method="POST" class="w-full" action="{{route('admin.inventories.status', $item->id)}}" :default="$item" submit-on-change>
                        <x-splade-select class="w-64" :disabled="$item->status === 'canceled' || $item->status === 'done'" name="status" placeholder="{{__('Status')}}" >
                            <option value="pending">{{__('Pending')}}</option>
                            <option value="not-available">{{__('Not Available')}}</option>
                            <option value="part-available">{{__('Part Available')}}</option>
                            <option value="canceled">{{__('Canceled')}}</option>
                            <option value="done">{{__('Done')}}</option>
                        </x-splade-select>
                    </x-splade-form>
                </x-splade-cell>
                <x-splade-cell created_at>
                    <x-tomato-admin-row table type="datetime" value="{{$item->created_at}}" />
                </x-splade-cell>
                <x-splade-cell is_activated>
                    <x-tomato-admin-row table type="bool" :value="$item->is_activated" />
                </x-splade-cell>
                <x-splade-cell order.uuid>
                    @if($item->order_id)
                    <div class="grid gap-y-2">
                        <a href="{{ route('admin.orders.print', $item->order?->id) }}" target="_blank" class="flex fi-in-text">
                            <div class="min-w-0 flex-1">
                                <div class="whitespace-nowrap inline-flex items-center gap-2 justify-center ml-auto rtl:ml-0 rtl:mr-auto min-h-4 px-2 py-0.5 text-xs font-medium tracking-tight rounded-xl whitespace-normal text-primary-700 bg-primary-500/10 dark:text-primary-500">
                                    <div class="flex justify-center gap-2">
                                        <x-heroicon-s-printer class="h-4 w-4"/>
                                        <div>
                                            {{$item->order?->uuid}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @else
                        -
                    @endif
                </x-splade-cell>
                <x-splade-cell total>
                    <x-tomato-admin-row table  :value="dollar($item->total)" />
                </x-splade-cell>


                <x-splade-cell actions>
                    <div class="flex justify-start">
                        @if(!$item->is_activated)
                            @can('admin.inventories.approve')
                                <x-tomato-admin-button confirm method="POST" type="icon" title="{{__('Approve All')}}" :href="route('admin.inventories.approve', $item->id)">
                                    <x-heroicon-s-check-circle class="h-6 w-6"/>
                                </x-tomato-admin-button>
                            @endcan
                        @endif
                        @can('admin.inventories.print.show')
                                <a href="{{route('admin.inventories.print.barcode', $item->id)}}" target="_blank" title="{{__('Barcode')}}" class="px-2 text-primary-500">
                                    <x-heroicon-s-qr-code class="h-6 w-6"/>
                                </a>

                            <a href="{{route('admin.inventories.print.show', $item->id)}}" target="_blank" title="{{__('Print')}}" class="px-2 text-success-500">
                                <x-heroicon-s-printer class="h-6 w-6"/>
                            </a>
                        @endcan
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
