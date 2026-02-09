<x-filament-panels::page>

    {{ $this->form }}

    <x-filament::button
        wire:click="generate"
        class="mt-4"
    >
        Generate PDF
    </x-filament::button>

</x-filament-panels::page>
    