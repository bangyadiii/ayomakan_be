<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Food extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable =  [
        'name', 'description', 'ingredients', 'price', 'rate', 'types'
    ];

    public function images()
    {
        return $this->hasMany(FoodImages::class, "food_id", "id");
    }

    public function transactions()
    {
        $this->hasMany(Transactions::class);
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->timestamp;
    }
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->timestamp;
    }
}
