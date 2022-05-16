<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Unit;

class Keyword extends Model
{
    use HasFactory;

    protected $table = 'keywords';
    protected $fillable = [
        'keyword',
        'description',
    ];
    public $timestamps = false;
    public $incrementing = true;

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
