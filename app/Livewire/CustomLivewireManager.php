<?php

namespace App\Livewire;

use Livewire\LivewireManager;

class CustomLivewireManager extends LivewireManager
{
    /**
     * Override getUpdateUri to ensure it always includes the subdirectory path.
     * By default, Livewire 3 uses a relative path (toRoute(..., false)) which
     * breaks AJAX requests when hosted in a subfolder like /gaspar/public.
     */
    public function getUpdateUri()
    {
        return app('url')->to('/livewire/update');
    }
}
