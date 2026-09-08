@props(['sections', 'label', 'mode'])

<div class="flex min-h-0 flex-1 flex-col">
    <nav data-nav-menu="{{ $mode }}" aria-label="{{ $label }}" class="min-h-0 flex-1 overflow-y-auto px-4 py-5">
        <div class="space-y-6">
            @foreach ($sections as $section)
                <section aria-labelledby="{{ $mode }}-navigation-{{ Str::slug($section['label']) }}">
                    <h2 id="{{ $mode }}-navigation-{{ Str::slug($section['label']) }}" class="px-3 text-xs font-bold uppercase tracking-wider text-slate-400">
                        {{ $section['label'] }}
                    </h2>
                    <div class="mt-2 space-y-1">
                        @foreach ($section['items'] as $item)
                            @php($active = request()->routeIs($item['active']))
                            <a href="{{ route($item['route']) }}"
                                data-nav-route="{{ $item['route'] }}"
                                @if ($mode === 'mobile') data-nav-link @endif
                                @if ($active) aria-current="page" @endif
                                @class([
                                    'block rounded-lg border-l-4 px-3 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2',
                                    'border-amber-500 bg-amber-50 text-amber-950' => $active,
                                    'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-950' => ! $active,
                                ])>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </nav>

    <div data-nav-account="{{ $mode }}" class="shrink-0 border-t border-slate-200 bg-slate-50 px-5 py-4 print:hidden">
        <div class="min-w-0 break-words text-sm">
            <p class="font-semibold text-slate-950">{{ auth()->user()->name }}</p>
            <p class="mt-0.5 text-slate-500">{{ ucfirst(auth()->user()->role) }}</p>
        </div>
        <form data-nav-logout="{{ $mode }}" method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">Sign out</button>
        </form>
    </div>
</div>
