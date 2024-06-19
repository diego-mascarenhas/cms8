<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

	protected $table = 'messages';

    protected $fillable = ['name', 'category_id', 'type_id', 'text', 'status'];

    public function type()
    {
        return $this->belongsTo(MessageType::class);
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
