@php
    $hasChildren = $node->children->isNotEmpty();
    $isAteco = $node->isAteco();
    $isNace = $node->isNace();
    $indentClass = match($level) {
        0 => '',
        1 => 'ml-6',
        2 => 'ml-12',
        3 => 'ml-18',
        4 => 'ml-24',
        default => 'ml-32',
    };
@endphp

<div class="{{ $indentClass }}">
    @if($hasChildren)
        <details @if($search !== '' || $level < 2) open @endif class="collapse collapse-arrow border border-base-300 bg-base-100 mb-2">
            <summary class="collapse-title min-h-0 py-3 px-4 cursor-pointer hover:bg-base-200">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            @if($isAteco)
                                <span class="badge badge-primary badge-sm h-fit">ATECO</span>
                            @elseif($isNace)
                                <span class="badge badge-info badge-sm h-fit">NACE</span>
                            @else
                                <span class="badge badge-outline badge-sm h-fit">{{ $node->hierarchy->label() }}</span>
                            @endif
                            
                            @if($node->isSearchResult ?? false)
                                <x-lucide-search class="h-4 w-4 text-primary" />
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-semibold text-sm">{{ $node->code }}</span>
                                <span class="text-sm truncate">{{ $node->title_it }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 shrink-0">
                        @if($node->risk)
                            <span class="badge {{ $node->risk->badgeColor() }} badge-sm h-fit">
                                {{ $node->risk->label() }}
                            </span>
                        @endif
                        
                        <span class="badge badge-ghost badge-sm h-fit">
                            {{ $node->children->count() }} {{ __('sottolivelli') }}
                        </span>
                    </div>
                </div>
            </summary>
            
            <div class="collapse-content px-4 pb-4">
                <div class="space-y-2 mt-2">
                    @foreach($node->children as $child)
                        @include('admin.nace-ateco.partials.tree-node', ['node' => $child, 'level' => $level + 1, 'search' => $search])
                    @endforeach
                </div>
            </div>
        </details>
    @else
        <div class="border border-base-300 bg-base-100 rounded-lg p-3 mb-2 {{ $node->isSearchResult ?? false ? 'ring-2 ring-primary' : '' }}">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        @if($isAteco)
                            <span class="badge badge-primary badge-sm h-fit">ATECO</span>
                        @elseif($isNace)
                            <span class="badge badge-info badge-sm h-fit">NACE</span>
                        @else
                            <span class="badge badge-outline badge-sm h-fit">{{ $node->hierarchy->label() }}</span>
                        @endif
                        
                        @if($node->isSearchResult ?? false)
                            <x-lucide-search class="h-4 w-4 text-primary" />
                        @endif
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-semibold text-sm">{{ $node->code }}</span>
                                <span class="text-sm">{{ $node->title_it }}</span>
                            </div>
                            @if($node->title_en !== $node->title_it)
                                <span class="text-xs text-base-content/60">{{ $node->title_en }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2 shrink-0">
                    @if($node->risk)
                        <span class="badge {{ $node->risk->badgeColor() }} badge-sm h-fit">
                            {{ $node->risk->label() }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
