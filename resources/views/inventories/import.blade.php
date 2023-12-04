<x-tomato-admin-container label="{{__('Import Inventory')}}">
    <x-splade-form method="POST" class="flex flex-col gap-4" action="{{route('admin.inventories.import.store')}}">
        <x-splade-file filepond name="excel" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"  label="{{__('Inventory Excel Sheet')}}" />
        <x-tomato-admin-submit spinner label="{{__('Import')}}" />
    </x-splade-form>
</x-tomato-admin-container>
