<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plantel extends Model
{
    protected $table = 'planteles';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
    ];

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'plantel_id');
    }
}
