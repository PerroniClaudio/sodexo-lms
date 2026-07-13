@props(['data' => []])

@php
    extract($data);
@endphp

<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.editable-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />
