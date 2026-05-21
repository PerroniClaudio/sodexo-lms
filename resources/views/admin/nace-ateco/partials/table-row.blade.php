@php
    $hasChildren = $node->children->isNotEmpty();
    $isAteco = $node->isAteco();
    $isNace = $node->isNace();
    $isSearchResult = $node->isSearchResult ?? false;
@endphp

<tr class="hover:bg-base-200 {{ $isSearchResult ? 'bg-primary/5' : '' }}">
    {{-- Livello --}}
    <td class="text-center">
        <span class="badge badge-ghost badge-xs">{{ $level }}</span>
    </td>
    
    {{-- Codice e Titolo con indentazione --}}
    <td>
        <div class="flex items-center gap-2" style="padding-left: {{ ($level - 1) * 1.5 }}rem;">
            {{-- Icona gerarchia --}}
            @if($level > 1)
                <span class="text-base-content/30 text-xs">
                    @if($hasChildren)
                        └─
                    @else
                        └─
                    @endif
                </span>
            @endif
            
            {{-- Badge tipo --}}
            @if($isAteco)
                <span class="badge badge-primary badge-xs">ATECO</span>
            @elseif($isNace)
                <span class="badge badge-info badge-xs">NACE</span>
            @else
                <span class="badge badge-ghost badge-xs opacity-50">{{ $node->hierarchy->label() }}</span>
            @endif
            
            {{-- Icona ricerca --}}
            @if($isSearchResult)
                <x-lucide-search class="h-3 w-3 text-primary shrink-0" />
            @endif
            
            {{-- Codice --}}
            <code class="text-xs font-semibold">{{ $node->code }}</code>
            
            {{-- Titolo --}}
            <span class="text-sm {{ $isAteco ? 'font-medium' : '' }}">{{ $node->title_it }}</span>
            
            {{-- Contatore figli --}}
            @if($hasChildren)
                <span class="badge badge-ghost badge-xs opacity-60">{{ $node->children->count() }}</span>
            @endif
        </div>
    </td>
    
    {{-- Rischio --}}
    <td>
        @if($node->risk)
            <span class="badge {{ $node->risk->badgeColor() }} badge-xs">
                {{ $node->risk->label() }}
            </span>
        @else
            <span class="text-base-content/30 text-xs">—</span>
        @endif
    </td>
</tr>

{{-- Renderizza ricorsivamente i figli --}}
@if($hasChildren)
    @foreach($node->children as $child)
        @include('admin.nace-ateco.partials.table-row', ['node' => $child, 'level' => $level + 1, 'search' => $search])
    @endforeach
@endif
