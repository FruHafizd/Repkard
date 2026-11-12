<x-filament-widgets::widget>
    <x-filament::section heading="Pengumuman" icon="heroicon-o-megaphone">
        <ul class="grid gap-4">
            @foreach ($announcements as $announcement)
                <li class="grid gap-1">
                    <h3 class="font-semibold text-gray-950 dark:text-white">
                        {{ $announcement->title }}
                        @if ($announcement->is_pinned)
                            <span class="text-xs font-normal text-gray-500">· Disematkan</span>
                        @endif
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $announcement->starts_at->format('d M Y') }}</p>
                    <p class="text-gray-700 dark:text-gray-300">{!! nl2br(e($announcement->body)) !!}</p>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
