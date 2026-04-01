<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriasSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Definiciones base (patron = null) ──────────────────────────────
        // Todos los iconos de ICONOS_CATEGORIA (categoriaIconos.js) del frontend
        $definiciones = [
            'Supermercado'       => '🛒',
            'Servicios'          => '⚡',
            'Vivienda'           => '🏠',
            'Salud'              => '🏥',
            'Transporte'         => '🚌',
            'Educación'          => '📚',
            'Entretenimiento'    => '🎬',
            'Transferencias'     => '↔️',
            'Ingresos'           => '💰',
            'Personal'           => '👤',
            'Impuestos'          => '💸',
            'Otros'              => '📋',
            'Etiqueta'           => '🏷',
            'Factura'            => '🧾',
            'Compras'            => '🛍️',
            'Comida'             => '🍔',
            'Auto'               => '🚗',
            'Combustible'        => '⛽',
            'Hogar'              => '🏡',
            'Mantenimiento'      => '🔧',
            'Tecnologia'         => '📱',
            'Estudio'            => '🎓',
            'Objetivo'           => '🎯',
            'Regalos'            => '🎁',
            'Viajes'             => '✈️',
            'Mascotas'           => '🐶',
            'Trabajo'            => '💼',
            'Tarjeta'            => '💳',
            'Herramientas'       => '💡',
            'Bienestar'          => '🧘',
            'Cigarrillos/Snacks' => '🚬',
        ];

        foreach ($definiciones as $nombre => $icono) {
            $existe = DB::table('categorias')
                ->where('nombre', $nombre)
                ->whereNull('patron')
                ->exists();

            if ($existe) {
                DB::table('categorias')
                    ->where('nombre', $nombre)
                    ->whereNull('patron')
                    ->update(['icono' => $icono, 'updated_at' => now()]);
            } else {
                DB::table('categorias')->insert([
                    'nombre'     => $nombre,
                    'icono'      => $icono,
                    'patron'     => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── 2. Patrones (patron = descripción a buscar en movimientos) ────────
        // patron => nombre_de_categoria
        $patrones = [
            // Supermercado / Alimentación
            'Comercio: DISCO N? 7'            => 'Supermercado',
            'Comercio: DISCO NE 7'            => 'Supermercado',
            'Comercio: TIENDA INGLESA 6'      => 'Supermercado',
            'Comercio: FROG'                  => 'Supermercado',
            'Comercio: SBARRO'                => 'Supermercado',
            'supermercado'                    => 'Supermercado',

            // Transporte
            'uber'                            => 'Transporte',
            'cabify'                          => 'Transporte',
            'combustible'                     => 'Transporte',
            'shell'                           => 'Transporte',
            'ypf'                             => 'Transporte',

            // Entretenimiento
            'netflix'                         => 'Entretenimiento',
            'spotify'                         => 'Entretenimiento',
            'steam'                           => 'Entretenimiento',

            // Cigarrillos/Snacks
            'Comercio: DISA'                  => 'Cigarrillos/Snacks',

            // Salud
            'Comercio: FARMACITY FARMAGLOBA'  => 'Salud',
            'Comercio: FARMACIA FARMASHOP 3'  => 'Salud',
            'clinica'                         => 'Salud',

            // Servicios
            'PAGO SERVICIOS'                  => 'Servicios',
            'PAGO SERVICIOS Antel'            => 'Servicios',
            'PAGO SERVICIOS ANTEL-Celular'    => 'Servicios',
            'PAGO SERVICIOS UTE'              => 'Servicios',
            'PAGO DE TC'                      => 'Servicios',
            'PAGO FACTURAS MC DEB'            => 'Servicios',
            'Comercio: DLO *ASOCIACION URUGU' => 'Servicios',
            'telefono'                        => 'Servicios',

            // Personal
            'Comercio: CLASSIE *HANDY*'       => 'Personal',

            // Vivienda
            'alquiler'                        => 'Vivienda',
            'TRF E-BROU ALQUILERES'           => 'Vivienda',

            // Educación
            'colegio'                         => 'Educación',
            'universidad'                     => 'Educación',

            // Ingresos
            'Retiro Red: REDBROU'             => 'Ingresos',
            'TRF SPI SUELDOS'                 => 'Ingresos',
            'transferencia recibida'          => 'Ingresos',

            // Transferencias
            'mercadopago'                     => 'Transferencias',
            'TRANSFERENCIA SPI ENVIADA'       => 'Transferencias',

            // Otros
            'otros'                           => 'Otros',
        ];

        $rows = [];
        foreach ($patrones as $patron => $nombre) {
            $icono  = $definiciones[$nombre] ?? '🏷';
            $rows[] = [
                'nombre'     => $nombre,
                'icono'      => $icono,
                'patron'     => $patron,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // upsert por patron (único): si ya existe actualiza nombre e icono
        DB::table('categorias')->upsert(
            $rows,
            ['patron'],
            ['nombre', 'icono', 'updated_at']
        );
    }
}
