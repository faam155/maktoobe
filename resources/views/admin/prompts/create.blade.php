<x-layouts.admin :title="__('prompts.create')"><form method="POST" action="{{ route('admin.prompts.store') }}" class="admin-form">@csrf @include('admin.prompts._form')</form></x-layouts.admin>
