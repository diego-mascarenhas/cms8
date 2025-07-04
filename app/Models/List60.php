<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class List60 extends Model
{
    use HasFactory;

    protected $table = 'list60';

    protected $fillable = ['contact_id', 'type_id', 'date_next', 'notes', 'status_id', 'responsible_id'];

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function scopeMyResponsibilities($query)
    {
        return $query->where('responsible_id', auth()->id());
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function type()
    {
        return $this->belongsTo(EnterpriseType::class, 'type_id');
    }

    public function status()
    {
        return $this->belongsTo(List60Status::class, 'status_id');
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->name.'</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }
}
