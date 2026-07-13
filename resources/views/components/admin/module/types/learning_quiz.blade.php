@props(['data' => []])

@php
    extract($data);
@endphp

<x-admin.module.quiz-validity-badge :data="get_defined_vars()" />
<x-admin.module.readonly-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<div class="grid gap-6 md:grid-cols-2"> 
<x-admin.module.quiz-thresholds :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />
</div>
