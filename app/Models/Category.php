<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'categories';

    protected $fillable = ['name', 'description', 'data', 'parent_id', 'order', 'status'];

    protected $casts = [
        'data' => 'object',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'category_user', 'category_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'category_id');
    }

    public static function getOptions($parentId = null)
    {
        $query = self::query();

        if (!is_null($parentId))
        {
            $query->where('parent_id', $parentId);
        }

        return $query->get()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }
}
