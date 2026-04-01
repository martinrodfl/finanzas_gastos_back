<?php
namespace App\Services;

use App\Models\Categoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategoriaReglaMatcher
{
    private Collection $reglas;

    public function __construct(?Collection $reglas = null)
    {
        $this->reglas = ($reglas ?? Categoria::query()->whereNotNull('patron')->get(['nombre', 'patron']))
            ->map(function ($regla) {
                return [
                    'nombre' => $regla->nombre,
                    'needle' => $this->normalizar($regla->patron),
                ];
            })
            ->filter(fn(array $regla) => $regla['needle'] !== '')
            ->sortByDesc(fn(array $regla) => strlen($regla['needle']))
            ->values();
    }

    public function resolver(?string $descripcion, string $fallback = 'Otros'): string
    {
        $texto = $this->normalizar($descripcion);

        if ($texto === '') {
            return $fallback;
        }

        foreach ($this->reglas as $regla) {
            if (str_contains($texto, $regla['needle'])) {
                return $regla['nombre'];
            }
        }

        return $fallback;
    }

    private function normalizar(?string $texto): string
    {
        if ($texto === null) {
            return '';
        }

        $ascii = Str::ascii(mb_strtolower(trim($texto)));
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        return trim($ascii);
    }
}
