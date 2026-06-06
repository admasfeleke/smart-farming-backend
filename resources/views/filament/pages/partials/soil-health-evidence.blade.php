<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 p-3 text-sm">
        <div class="font-semibold text-gray-800">Soil record summary</div>
        <div class="mt-2 grid gap-1 text-gray-600">
            <div>Farm: {{ $record->plot?->farm?->farm_name ?? '-' }}</div>
            <div>Plot: {{ $record->plot?->plot_name ?? '-' }}</div>
            <div>Test date: {{ optional($record->test_date)->toDateString() ?? '-' }}</div>
            <div>Review status: {{ $record->review_status ?? '-' }}</div>
        </div>
    </div>

    @if (empty($items))
        <p class="text-sm text-gray-600">No evidence file is available for this soil health record.</p>
    @else
        @foreach ($items as $item)
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="mb-2 text-sm font-semibold text-gray-800">
                    {{ $item['label'] ?? 'Evidence' }}
                </div>

                @if (! empty($item['url']) && ! empty($item['is_image']))
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="block">
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['label'] ?? 'Soil evidence image' }}"
                            class="max-h-80 w-full rounded-md object-contain bg-gray-50"
                        >
                    </a>
                @elseif (! empty($item['url']))
                    <a
                        href="{{ $item['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white"
                    >
                        Open evidence file
                    </a>
                @else
                    <div class="text-sm text-gray-500">Evidence URL is missing.</div>
                @endif

                @if (! empty($item['url']))
                    <div class="mt-2 text-xs text-gray-500 break-all">
                        {{ $item['url'] }}
                    </div>
                @endif

                @if (! empty($item['type']))
                    <div class="mt-1 text-xs text-gray-500">
                        Type: {{ $item['type'] }}
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
