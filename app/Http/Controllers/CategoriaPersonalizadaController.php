<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriaPersonalizadaController extends Controller
{
    /**
     * Devuelve todas las categorías base (patron = null)
     */
    public function index(): JsonResponse
    {
        $categorias = DB::table('categorias')
            ->whereNull('patron')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'icono']);

        return response()->json($categorias);
    }

    /**
     * Crea o actualiza una categoría base (patron = null)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'icono'  => 'required|string|max:20',
        ]);

        $existe = DB::table('categorias')
            ->where('nombre', $validated['nombre'])
            ->whereNull('patron')
            ->exists();

        if ($existe) {
            DB::table('categorias')
                ->where('nombre', $validated['nombre'])
                ->whereNull('patron')
                ->update(['icono' => $validated['icono'], 'updated_at' => now()]);
        } else {
            DB::table('categorias')->insert([
                'nombre'     => $validated['nombre'],
                'icono'      => $validated['icono'],
                'patron'     => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $categoria = DB::table('categorias')
            ->where('nombre', $validated['nombre'])
            ->whereNull('patron')
            ->first(['id', 'nombre', 'icono']);

        return response()->json($categoria, 201);
    }

    /**
     * Actualiza nombre y/o icono de una categoría base
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'icono'  => 'nullable|string|max:20',
        ]);

        $campos = array_filter($validated, fn($v) => $v !== null);
        if (! empty($campos)) {
            $campos['updated_at'] = now();
            DB::table('categorias')
                ->where('id', $id)
                ->whereNull('patron')
                ->update($campos);
        }

        $categoria = DB::table('categorias')
            ->where('id', $id)
            ->first(['id', 'nombre', 'icono']);

        if (! $categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json($categoria);
    }

    /**
     * Elimina una categoría base (solo filas con patron = null)
     */
    public function destroy($id): JsonResponse
    {
        DB::table('categorias')->where('id', $id)->whereNull('patron')->delete();
        return response()->json(null, 204);
    }
}
