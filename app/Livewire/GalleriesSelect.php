<?php

namespace App\Livewire;

use Livewire\Component;
use Spatie\Tags\Tag;

class GalleriesSelect extends Component
{
    public $selected = [];
    
    public $name = 'galleries';
    
    public $id = 'galleries';
    
    public $label = 'Galerías';
    
    public $placeholder = 'Buscar o crear galerías...';
    
    public $required = false;
    
    public $multiple = true;
    
    public function mount($selected = [], $name = 'galleries', $id = null, $label = 'Galerías', $required = false, $multiple = true)
    {
        $this->selected = is_array($selected) ? $selected : [];
        $this->name = $name;
        $this->id = $id ?: $name;
        $this->label = $label;
        $this->required = $required;
        $this->multiple = $multiple;
    }
    
    public function updatedSelected($value)
    {
        $this->dispatch('galleries-updated', $this->selected);
    }
    
    public function render()
    {
        return view('livewire.galleries-select');
    }
}
