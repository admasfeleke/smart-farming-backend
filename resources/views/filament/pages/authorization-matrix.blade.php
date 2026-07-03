<x-filament-panels::page>
    <x-filament::section>
        <div class="text-sm text-gray-600">
            This matrix is loaded from <code>config/authority_matrix.php</code> and shows role-level action permissions.
            Administrative scope constraints are applied additionally by policy checks.
        </div>
    </x-filament::section>

    <x-filament::section class="mt-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Action</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Super Admin</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Admin</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Supporter</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Expert</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Farmer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr class="align-top">
                            <td class="px-3 py-2 font-medium text-gray-800">
                                {{ $row['action'] }}
                            </td>

                            @foreach ($roles as $role)
                                @php
                                    $cell = $row['roles'][$role];
                                @endphp
                                <td class="px-3 py-2">
                                    @if ($cell['allowed'])
                                        <span class="inline-flex rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">
                                            {{ $cell['labels'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                            No
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
