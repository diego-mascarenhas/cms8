<?php

namespace App\Livewire;

use Livewire\Component;
use Spatie\Tags\Tag;

class TagsSelect extends Component
{
    public $selected = [];
    
    public $name = 'tags';
    
    public $id = 'tags';
    
    public $label = 'Etiquetas';
    
    public $placeholder = 'Buscar o crear etiquetas...';
    
    public $required = false;
    
    public $multiple = true;
    
    public function mount($selected = [], $name = 'tags', $id = null, $label = 'Etiquetas', $required = false, $multiple = true)
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
        $this->dispatch('tags-updated', $this->selected);
    }
    
    // This method is called when the parent component updates the :selected prop
    public function updateSelected($newSelected)
    {
        $this->selected = is_array($newSelected) ? $newSelected : [];
        $this->dispatch('tags-updated', $this->selected);
    }
    
    public function render()
    {
        return view('livewire.tags-select');
    }
}
