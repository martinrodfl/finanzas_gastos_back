<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    const UPDATED_AT = null;

    protected $fillable = [
        'fecha',
        'descripcion',
        'documento',
        'asunto',
        'dependencia',
        'debito',
        'credito',
        'categoria_manual',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'debito'  => 'decimal:2',
        'credito' => 'decimal:2',
    ];
}
