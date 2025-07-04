<?php

namespace App\View\Components;

use App\Models\EnterpriseStatus;
use Illuminate\View\Component;

class EnterpriseStatusSelect extends Component
{
    public $id;

    public $label;

    public $value;

    public $enterpriseTypeId;

    public function __construct($id = 'status_id', $label = 'Estado', $value = null, $enterpriseTypeId = 1)
    {
        $this->id = $id;
        $this->label = $label;
        $this->value = $value;
        $this->enterpriseTypeId = $enterpriseTypeId;
    }

    public function render()
    {
        $options = EnterpriseStatus::getOptions($this->enterpriseTypeId);

        return view('components.enterprise-status-select', [
            'options' => $options,
        ]);
    }
}
