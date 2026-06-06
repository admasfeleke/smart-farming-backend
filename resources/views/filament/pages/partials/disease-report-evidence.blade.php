<div class="space-y-4">
    @if (empty($items))
        <p class="text-sm text-gray-600">No image evidence is available for this report.</p>
    @else
        @foreach ($items as $item)
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="mb-2 text-sm font-semibold text-gray-800">
                    {{ $item['label'] ?? 'Evidence' }}
                </div>
                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="block">
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['label'] ?? 'Evidence image' }}"
                            class="max-h-80 w-full rounded-md object-contain bg-gray-50"
                        >
                    </a>
                    <div class="mt-2 text-xs text-gray-500 break-all">
                        {{ $item['url'] }}
                    </div>
                @else
                    <div class="text-sm text-gray-500">Image URL is missing.</div>
                @endif
            </div>
        @endforeach
    @endif
</div>
