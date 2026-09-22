<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    protected $table = 'grados';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'grado_id');
    }
}
