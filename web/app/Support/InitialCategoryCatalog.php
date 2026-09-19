<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InitialCategoryCatalog
{
    /**
     * @var array<string, list<array{name: string, color: string, icon: string, children: list<string>}>>
     */
    private const CATALOG = [
        'expense' => [
            ['name' => 'Vivienda', 'color' => '#356859', 'icon' => 'home', 'children' => ['Alquiler o hipoteca', 'Comunidad', 'Mantenimiento y reparaciones', 'Mobiliario y hogar', 'Seguro del hogar']],
            ['name' => 'Suministros', 'color' => '#2878A0', 'icon' => 'bolt', 'children' => ['Electricidad', 'Agua', 'Gas', 'Internet', 'Telefonía', 'Otros suministros']],
            ['name' => 'Alimentación', 'color' => '#B25900', 'icon' => 'cart', 'children' => ['Supermercado', 'Restaurantes', 'Comida a domicilio', 'Cafetería y aperitivos']],
            ['name' => 'Transporte', 'color' => '#2563A9', 'icon' => 'car', 'children' => ['Combustible', 'Transporte público', 'Mantenimiento del vehículo', 'Seguro', 'Aparcamiento y peajes', 'Taxi/VTC']],
            ['name' => 'Salud', 'color' => '#B93F50', 'icon' => 'health', 'children' => ['Farmacia', 'Consultas médicas', 'Dentista', 'Seguro médico', 'Óptica']],
            ['name' => 'Educación', 'color' => '#6D43A6', 'icon' => 'education', 'children' => ['Matrículas', 'Cursos', 'Libros', 'Material escolar']],
            ['name' => 'Familia', 'color' => '#B52A73', 'icon' => 'family', 'children' => ['Niños', 'Guardería', 'Actividades', 'Ayuda familiar']],
            ['name' => 'Mascotas', 'color' => '#79533A', 'icon' => 'paw', 'children' => ['Alimentación', 'Veterinario', 'Medicamentos', 'Accesorios']],
            ['name' => 'Compras personales', 'color' => '#A94712', 'icon' => 'bag', 'children' => ['Ropa y calzado', 'Cuidado personal', 'Tecnología', 'Regalos']],
            ['name' => 'Ocio y cultura', 'color' => '#7F3B9D', 'icon' => 'leisure', 'children' => ['Cine y espectáculos', 'Aficiones', 'Deporte', 'Juegos']],
            ['name' => 'Viajes', 'color' => '#087E9A', 'icon' => 'travel', 'children' => ['Transporte', 'Alojamiento', 'Comidas', 'Actividades']],
            ['name' => 'Suscripciones', 'color' => '#4F52A3', 'icon' => 'repeat', 'children' => ['Streaming', 'Software', 'Almacenamiento', 'Membresías']],
            ['name' => 'Impuestos y administración', 'color' => '#59697D', 'icon' => 'document', 'children' => ['Impuestos', 'Tasas', 'Gestoría', 'Trámites']],
            ['name' => 'Finanzas y seguros', 'color' => '#176B63', 'icon' => 'bank', 'children' => ['Comisiones bancarias', 'Intereses', 'Seguros diversos']],
            ['name' => 'Donaciones', 'color' => '#A52662', 'icon' => 'heart', 'children' => ['Donaciones', 'Asociaciones', 'Regalos solidarios']],
            ['name' => 'Imprevistos y otros', 'color' => '#9B5510', 'icon' => 'alert', 'children' => ['Emergencias', 'Reparaciones inesperadas', 'Otros gastos']],
        ],
        'income' => [
            ['name' => 'Trabajo', 'color' => '#28733A', 'icon' => 'briefcase', 'children' => ['Nómina', 'Paga extraordinaria', 'Horas extra', 'Bonificaciones']],
            ['name' => 'Prestaciones', 'color' => '#15766E', 'icon' => 'aid', 'children' => ['Pensiones', 'Desempleo', 'Ayudas', 'Subvenciones']],
            ['name' => 'Rendimientos', 'color' => '#176D55', 'icon' => 'chart', 'children' => ['Intereses', 'Dividendos recibidos', 'Alquileres']],
            ['name' => 'Ingresos extraordinarios', 'color' => '#58791A', 'icon' => 'gift', 'children' => ['Venta de artículos', 'Premios', 'Regalos recibidos']],
            ['name' => 'Otros ingresos', 'color' => '#4D6E24', 'icon' => 'plus', 'children' => ['Otros ingresos']],
        ],
    ];

    public function createFor(Project $project, User $creator): void
    {
        $now = now();

        foreach (self::CATALOG as $type => $categories) {
            foreach ($categories as $categoryPosition => $category) {
                $parentId = DB::table('categories')->insertGetId([
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'type' => CategoryType::from($type)->value,
                    'name' => $category['name'],
                    'color' => $category['color'],
                    'icon' => $category['icon'],
                    'position' => ($categoryPosition + 1) * 10,
                    'is_initial' => true,
                    'created_by_user_id' => $creator->id,
                    'updated_by_user_id' => $creator->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $children = [];

                foreach ($category['children'] as $childPosition => $childName) {
                    $children[] = [
                        'project_id' => $project->id,
                        'parent_id' => $parentId,
                        'type' => CategoryType::from($type)->value,
                        'name' => $childName,
                        'color' => $category['color'],
                        'icon' => 'dot',
                        'position' => ($childPosition + 1) * 10,
                        'is_initial' => true,
                        'created_by_user_id' => $creator->id,
                        'updated_by_user_id' => $creator->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('categories')->insert($children);
            }
        }
    }
}
