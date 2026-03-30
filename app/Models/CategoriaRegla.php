<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaRegla extends Model
{
    protected $table = 'categoria_reglas';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'categoria',
    ];
}
