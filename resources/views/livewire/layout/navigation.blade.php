<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-navy border-b border-navy-deep">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-orange flex items-center justify-center font-display font-600 text-base text-navy">好</div>
                        <span class="font-display font-600 text-white hidden sm:block">TUTURNUSA</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex items-center">
                    <a href="{{ route('dashboard') }}"
                       wire:navigate
                       @class([
                           'px-3 py-2 rounded-xl text-sm font-600 transition',
                           'bg-navy-soft text-white' => request()->routeIs('dashboard'),
                           'text-white/80 hover:text-white hover:bg-navy-soft/60' => ! request()->routeIs('dashboard'),
                       ])>
                        🏠 Dashboard
                    </a>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-navy-soft text-white text-sm font-600 hover:bg-navy-deep transition">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                            <svg class="w-4 h-4 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profil') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Keluar') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-white/80 hover:text-white hover:bg-navy-soft transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-navy-deep border-t border-navy-soft">
        <div class="pt-2 pb-3 px-3 space-y-1">
            <a href="{{ route('dashboard') }}"
               wire:navigate
               @class([
                   'block px-3 py-2 rounded-xl text-sm font-600 transition',
                   'bg-navy-soft text-white' => request()->routeIs('dashboard'),
                   'text-white/80 hover:text-white hover:bg-navy-soft/60' => ! request()->routeIs('dashboard'),
               ])>
                🏠 Dashboard
            </a>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-3 pb-3 px-3 border-t border-navy-soft">
            <div class="px-2 mb-2">
                <div class="font-600 text-sm text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="text-xs text-white/80">{{ auth()->user()->email }}</div>
            </div>

            <div class="space-y-1">
                <a href="{{ route('profile') }}"
                   wire:navigate
                   class="block px-3 py-2 rounded-xl text-sm font-600 text-white/80 hover:text-white hover:bg-navy-soft/60 transition">
                    {{ __('Profil') }}
                </a>

                <button wire:click="logout" class="w-full text-start px-3 py-2 rounded-xl text-sm font-600 text-white/80 hover:text-white hover:bg-navy-soft/60 transition">
                    {{ __('Keluar') }}
                </button>
            </div>
        </div>
    </div>
</nav>
