<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Preview Container --}}
        <div class="bg-white shadow-sm ring-1 ring-gray-950/5 rounded-xl overflow-hidden">
            {{-- Preview Header --}}
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-eye class="w-5 h-5 text-gray-500" />
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('resources.page_resource.live_preview') }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <x-heroicon-o-language class="w-4 h-4" />
                        <span>{{ strtoupper(app()->getLocale()) }}</span>
                    </div>
                </div>
            </div>

            {{-- Preview Content --}}
            <div class="p-8">
                <div class="prose max-w-none">
                    {!! $this->getPageContent() !!}
                </div>
            </div>
        </div>

        {{-- Page Metadata --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white shadow-sm ring-1 ring-gray-950/5 rounded-xl p-4">
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                    <x-heroicon-o-key class="w-4 h-4" />
                    {{ __('resources.page_resource.page_key') }}
                </div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ $record->page_key }}
                </div>
            </div>

            <div class="bg-white shadow-sm ring-1 ring-gray-950/5 rounded-xl p-4">
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                    <x-heroicon-o-code-bracket class="w-4 h-4" />
                    {{ __('resources.page_resource.template') }}
                </div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ $record->template }}
                </div>
            </div>

            <div class="bg-white shadow-sm ring-1 ring-gray-950/5 rounded-xl p-4">
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                    <x-heroicon-o-tag class="w-4 h-4" />
                    {{ __('resources.page_resource.version') }}
                </div>
                <div class="text-lg font-semibold text-gray-900">
                    {{ $record->version }}
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
