<x-tomato-admin-container label="{{__('Show Product Report')}}">
    <x-splade-form class="flex flex-col gap-4" method="POST" action="{{route('admin.inventories.report.data')}}" stay>
        <x-splade-select
            choices
            name="branch_id"
            label="{{__('Branch')}}"
            placeholder="{{__('Select Branch')}}"
            remote-url="{{route('admin.branches.api')}}"
            remote-root="data"
            option-label="name"
            option-value="id"
        />
        <div>
            <label for="" class="block text-sm font-medium leading-6 text-gray-950 dark:text-white">
                {{__('Product')}}
            </label>
            <x-tomato-search
                name="product_id"
                label="{{__('Product')}}"
                placeholder="{{__('Select Product')}}"
                remote-root="data"
                remote-url="{{route('admin.orders.product')}}"
                option-label="name.{{app()->getLocale()}}"
                option-value="object"
            />
        </div>
        <x-tomato-admin-submit spinner label="{{__('Get Report')}}" />

       <table v-if="form.$response && (form.$response.data.length > 0)" class="border min-w-full divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-700">
           <thead>
                <tr class="hover:bg-gray-100 dark:hover:bg-gray-600">
                     <th class="border p-2">Product</th>
                     <th class="border p-2">Options</th>
                     <th class="border p-2">Quantity</th>
                </tr>
           </thead>
           <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800 text-center">
                <tr v-for="(item, key) in form.$response.data" class="hover:bg-gray-100 dark:hover:bg-gray-600">
                     <td class="border p-2"><span v-text="item.product.name['{{app()->getLocale()}}']"></span>  [@{{ item.product?.sku }}]</td>
                     <td class="border p-2">
                         <div v-if="item.options && Object.keys(item.options)?.length" class="flex justify-center gap-2">
                             <div v-for="(option, index) in Object.keys(item.options)" class="flex justify-center gap-2">
                                 <div>@{{ item.options[option] }} <span v-if="index !== Object.keys(item.options).length-1">-</span></div>
                             </div>
                         </div>
                         <div v-else>
                             {{__('Without Options')}}
                         </div>
                     </td>
                     <td class="border p-2 font-bold">@{{ item.qty }}</td>
                </tr>
           </tbody>
       </table>
        <div v-else-if="form.$response && form.$response.data.length === 0" class="flex flex-col gap-4 items-center justify-center">
            <div>
                {{__('There is no stock records for this product on this branch')}}
            </div>
        </div>
    </x-splade-form>

</x-tomato-admin-container>
