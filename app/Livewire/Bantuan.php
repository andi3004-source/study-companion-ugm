<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Static help page: keyboard, screen reader, voice input, display settings, accessibility statement. */
#[Layout('layouts.study')]
class Bantuan extends Component
{
    public function render()
    {
        return view('livewire.bantuan');
    }
}
