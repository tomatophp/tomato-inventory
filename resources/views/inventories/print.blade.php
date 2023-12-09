<body onload="window.print()">


<table class="border min-w-full divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-700 text-center">
    <thead class="border min-w-full divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-700">
    <tr class="hover:bg-gray-100 dark:hover:bg-gray-600">
        <th class="border p-2 font-bold">{{ __('ID') }}</th>
        <th class="border p-2 font-bold" >{{ __('User') }}</th>
        <th class="border p-2 font-bold">{{ __('Branch') }}</th>
        <th class="border p-2 font-bold">{{ __('Order') }}</th>
        <th class="border p-2 font-bold">{{ __('Type') }}</th>
        <th class="border p-2 font-bold">{{ __('Items') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($inventory as $item)
        <tr>
            <td class="border p-2 font-bold">{{$item->id}}</td>
            <td class="border p-2 font-bold">@if($item->user_id){{$item?->user->name}}@endif</td>
            <td class="border p-2 font-bold">{{$item->branch?->name}}</td>
            <td class="border p-2 font-bold">{{$item->order?->id}}</td>
            <td class="border p-2 font-bold">{{$item->type}}</td>
            <td class="border p-2 font-bold">
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{__('SKU')}}</th>
                        <th>{{__('Options')}}</th>
                        <th>{{__('QNT')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($item->inventoryItems()->get()->map(function ($item){
                        if($item->item_type){
                            $item->item = $item->item_type::where('id',$item->item_id)->with('productMetas', function ($q){
                                $q->where('key', 'options');
                            })->first();
                        }
                        return $item;
                    }) as $key=>$value)
                        <tr>
                            <td>{{ $key+1 }}</td>
                            @if(is_object($value->item))
                            <td>{{ $value->item?->name }} - {{ $value->item?->sku }}</td>
                            @else
                                {{ $value->item }}
                            @endif
                            <td>
                            @if($value->options)
                                @foreach($value->options as $op)
                                    <span class="badge badge-success m-1 p-2">{{ $op }}</span>
                                @endforeach
                            @endif
                            </td>
                            <td>{{ $value->qty }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    @endforeach


    </tbody>
</table>

</body>
