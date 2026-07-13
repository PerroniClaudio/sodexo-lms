@props(['data' => []])

@php
    extract($data);
@endphp

<div>
    <!-- Nothing worth having comes easy. - Theodore Roosevelt -->
</div>
<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.editable-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />
<x-admin.module.appointment-details :data="get_defined_vars()" />
