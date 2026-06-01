<div class="user-event-calendar card border border-base-300 w-full bg-base-100 card-sm shadow-sm">
    <div class="card-body">
        <h2 class="card-title"><x-lucide-calendar class="w-6 h-6" /> {{ __('Calendario') }}</h2>
        <div id="user-event-calendar"></div>
    </div>
</div>

@once
    @vite('resources/js/components/user-event-calendar.js')
@endonce
