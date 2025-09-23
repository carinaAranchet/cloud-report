<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReporteController extends Controller
{
    //RAIZ
    private const NC_ROOT = 'files/Proveedores habituales';

    // 
    private const MESES_ES = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                              7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

    public function index(Request $request) {
        return view('reporte');
    }

    public function filters(Request $request) {
        $root = self::NC_ROOT;


        // aca me quedo con todos los proveedores, OJO si pongo dentro de carpeta de proveedores habituales, otra carpeta con cualquier nombre, va a tomar que es un prov
        $proveedores = DB::connection('nextcloud')
            ->table('oc_filecache as f')
            ->selectRaw("DISTINCT split_part(f.path,'/',3) as proveedor")
            ->where('f.path','like', "$root/%")
            ->whereRaw("split_part(f.path,'/',3) <> ''")
            ->orderBy('proveedor')
            ->pluck('proveedor');

        // idem caso anterior
        $tramites = DB::connection('nextcloud')
            ->table('oc_filecache as f')
            ->selectRaw("DISTINCT split_part(f.path,'/',4) as tramite")
            ->where('f.path','like', "$root/%")
            ->whereRaw("split_part(f.path,'/',4) <> ''")
            ->orderBy('tramite')
            ->pluck('tramite');

        $meses = collect(self::MESES_ES)->map(fn($v,$k)=>['value'=>$k,'label'=>$v])->values();

        // Estados disponibles
        $estados = [
            ['value'=>'OK',           'label'=>'OK'],
            ['value'=>'MAL',          'label'=>'MAL'],
            ['value'=>'En revisión',  'label'=>'En Revisión'],
        ];

        return response()->json([
            'proveedores' => $proveedores,
            'tramites'    => $tramites,
            'meses'       => $meses,
            'estados'     => $estados,
            'mesActual'   => (int)date('n'),
        ]);
    }

    public function data(Request $request) {
        $root      = self::NC_ROOT;
        $proveedor = trim((string)$request->input('proveedor', '')); // texto o ''
        $tramite   = trim((string)$request->input('tramite',   '')); // texto o ''
        $mesNum    = (int)$request->input('mes', date('n'));         // 1..12 (default mes actual)
        $estado    = trim((string)$request->input('estado',    '')); // 'OK' | 'MAL' | 'En revisión' | ''

        $mesNombre = self::MESES_ES[$mesNum] ?? null;

        // Subquery base con joins a tags
        $base = DB::connection('nextcloud')
            ->table('oc_filecache as f')
            ->join('oc_systemtag_object_mapping as m', DB::raw('m.objectid::bigint'), '=', 'f.fileid')
            ->join('oc_systemtag as t', 't.id', '=', 'm.systemtagid')
            ->where('f.path','like', "$root/%");

        Log::info('Base query: '.$base->toSql(), $base->getBindings());
        // Filtros opcionales
        if ($proveedor !== '') {
            $base->whereRaw("LOWER(split_part(f.path,'/',3)) = LOWER(?)", [$proveedor]);
        }
        if ($tramite !== '') {
            $base->whereRaw("split_part(f.path,'/',4) = ?", [$tramite]);
        }
        // Si el trámite es anual, el frontend podría no enviar mes. Igual, por defecto mostramos mes actual.
        if ($mesNombre) {
            $base->whereRaw("split_part(f.path,'/',5) = ?", [$mesNombre]);
        }

        // Agregación por Proveedor/Trámite/Mes con precedencia de estado: MAL > OK > En revisión
        $query = $base->selectRaw("
        split_part(f.path,'/',3) as proveedor,
        split_part(f.path,'/',4) as tramite,
        CASE
            WHEN split_part(f.path,'/',5) IN ('Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio',
                                            'Agosto','Septiembre','Octubre','Noviembre','Diciembre')
            THEN split_part(f.path,'/',5)
            ELSE 'ANUAL'
        END as mes,
        CASE
            WHEN bool_or(t.name = 'MAL') THEN 'MAL'
            WHEN bool_or(t.name = 'OK')  THEN 'OK'
            ELSE 'En revisión'
        END as estado
        ")
        ->groupByRaw("split_part(f.path,'/',3), split_part(f.path,'/',4), split_part(f.path,'/',5)")
        ->orderBy('proveedor')
        ->orderBy('tramite')
        ->orderByRaw("array_position(ARRAY['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'], split_part(f.path,'/',5))");

        if (in_array($estado, ['OK','MAL','En revisión'], true)) {
    $query->havingRaw("CASE
            WHEN bool_or(t.name = 'MAL') THEN 'MAL'
            WHEN bool_or(t.name = 'OK')  THEN 'OK'
            ELSE 'En revisión'
        END = ?", [$estado]);
}

$rows = $query->get();

return response()->json($rows);
    }
}