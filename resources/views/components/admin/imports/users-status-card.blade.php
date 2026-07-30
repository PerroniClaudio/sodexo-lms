@props(['data' => []])
@php(extract($data))
<x-admin.imports.recent-status-card :recent-imports="$recentImports" :title="$title ?? __('Import utenti completo recenti')" />
