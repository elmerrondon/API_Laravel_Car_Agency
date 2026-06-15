<?php

namespace App\Models\Cars;

use App\Models\Locations\Branch;
use App\Models\Sales\Sale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ["price","status","mileage","vin","year","color_id","car_model_id","category_id","car_type_id","branch_id"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function color(){
       return $this->belongsTo(Color::class);
    }

    public function carModel(){
       return $this->belongsTo(CarModel::class);
    }

    public function carType(){
       return $this->belongsTo(CarType::class);
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }
    
    public function sales(){
        return $this->hasMany(Sale::class);
    }
}
