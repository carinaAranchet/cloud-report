<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ['value'=>'MAL',          'label'=>'Mal'],
            ['value'=>'En revisión',  'label'=>'En revisión'],
            ['value'=>'VACÍO',        'label'=>'VACÍO'],
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
    $proveedor = trim((string)$request->input('proveedor', ''));
    $tramite   = trim((string)$request->input('tramite',   ''));
    $mesNum    = (int)$request->input('mes', date('n'));
    $estadoReq = trim((string)$request->input('estado',    '')); // OK | MAL | En revisión | VACÍO | ''

    $mesNombre = self::MESES_ES[$mesNum] ?? null;

    // Lista fija de meses para usar en SQL
    $MESES_SQL = "'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'";

    // -------- 1) SUBQUERY: CARPETAS (periodos reales existentes) --------
    // a) Meses en nivel 5
    $qMes5 = DB::connection('nextcloud')
        ->table('oc_filecache as f')
        ->join('oc_mimetypes as mt', 'mt.id', '=', 'f.mimetype')
        ->selectRaw("
            split_part(f.path,'/',3) as proveedor,
            split_part(f.path,'/',4) as tramite,
            split_part(f.path,'/',5) as mes
        ")
        ->where('f.path','like', "$root/%")
        ->where('mt.mimetype', 'httpd/unix-directory')
        ->whereRaw("split_part(f.path,'/',5) IN ($MESES_SQL)")
        ->whereRaw("split_part(f.path,'/',4) <> ''");

    // b) Meses en nivel 6 (casos como .../931/Enero/...)
    $qMes6 = DB::connection('nextcloud')
        ->table('oc_filecache as f')
        ->join('oc_mimetypes as mt', 'mt.id', '=', 'f.mimetype')
        ->selectRaw("
            split_part(f.path,'/',3) as proveedor,
            split_part(f.path,'/',4) as tramite,
            split_part(f.path,'/',6) as mes
        ")
        ->where('f.path','like', "$root/%")
        ->where('mt.mimetype', 'httpd/unix-directory')
        ->whereRaw("split_part(f.path,'/',6) IN ($MESES_SQL)")
        ->whereRaw("split_part(f.path,'/',4) <> ''")
;

    // c) ANUAL sólo si NO existen carpetas de meses (ni nivel 5 ni nivel 6) para ese prov+tram
    $qAnual = DB::connection('nextcloud')
        ->table('oc_filecache as f')
        ->join('oc_mimetypes as mt', 'mt.id', '=', 'f.mimetype')
        ->selectRaw("
            split_part(f.path,'/',3) as proveedor,
            split_part(f.path,'/',4) as tramite,
            'ANUAL' as mes
        ")
        ->where('f.path','like', "$root/%")
        ->where('mt.mimetype', 'httpd/unix-directory')
        ->whereRaw("split_part(f.path,'/',5) = ''") // carpeta del trámite
        ->whereRaw("split_part(f.path,'/',4) <> ''")
        ->whereNotExists(function($nx) use ($root, $MESES_SQL) {
            $nx->select(DB::raw(1))
               ->from('oc_filecache as f2')
               ->join('oc_mimetypes as mt2', 'mt2.id', '=', 'f2.mimetype')
               ->where('f2.path','like', "$root/%")
               ->where('mt2.mimetype', 'httpd/unix-directory')
               // mismo proveedor/trámite
               ->whereRaw("split_part(f2.path,'/',3) = split_part(f.path,'/',3)")
               ->whereRaw("split_part(f2.path,'/',4) = split_part(f.path,'/',4)")
               // existe al menos una carpeta de mes en nivel 5 o 6
               ->whereRaw("(split_part(f2.path,'/',5) IN ($MESES_SQL) OR split_part(f2.path,'/',6) IN ($MESES_SQL))");
        });

    // Filtros opcionales para cada rama
    foreach ([$qMes5, $qMes6, $qAnual] as $q) {
        if ($proveedor !== '') {
            $q->whereRaw("LOWER(split_part(f.path,'/',3)) = LOWER(?)", [$proveedor]);
        }
        if ($tramite !== '') {
            $q->whereRaw("split_part(f.path,'/',4) = ?", [$tramite]);
        }
    }
    if ($mesNombre) {
        // Si filtran por mes, sólo tiene sentido en ramas mensuales
        $qMes5->whereRaw("split_part(f.path,'/',5) = ?", [$mesNombre]);
        $qMes6->whereRaw("split_part(f.path,'/',6) = ?", [$mesNombre]);
        // Si filtran un mes puntual, ANUAL no aplica, forzamos a no devolver nada:
        $qAnual->whereRaw("1=0");
    }

    // Unimos las tres (mes5 ∪ mes6 ∪ anual)
    $carpetas = $qMes5->unionAll($qMes6)->unionAll($qAnual);

    // -------- 2) SUBQUERY: ESTADOS (sólo archivos con etiquetas) --------
    // Normalizamos el "mes" detectando nivel 5 o 6; si ninguno es mes => 'ANUAL'
    // Estado por prioridad: MAL (1) > En revisión (2) > OK (3); devolvemos el nombre tal cual (array_agg ... )[1]
    $estados = DB::connection('nextcloud')
        ->table('oc_filecache as f')
        ->join('oc_systemtag_object_mapping as m', DB::raw('m.objectid::bigint'), '=', 'f.fileid')
        ->join('oc_systemtag as t', 't.id', '=', 'm.systemtagid')
        ->selectRaw("
            split_part(f.path,'/',3) as proveedor,
            split_part(f.path,'/',4) as tramite,
            CASE
                WHEN split_part(f.path,'/',5) IN ($MESES_SQL) THEN split_part(f.path,'/',5)
                WHEN split_part(f.path,'/',6) IN ($MESES_SQL) THEN split_part(f.path,'/',6)
                ELSE 'ANUAL'
            END as mes,
            (array_agg(t.name ORDER BY
                CASE
                    WHEN t.name ILIKE 'MAL'           THEN 1
                    WHEN t.name ILIKE 'En revisión'   THEN 2
                    WHEN t.name ILIKE 'En revision'   THEN 2
                    WHEN t.name ILIKE 'OK'            THEN 3
                    ELSE 99
                END
            ))[1] as estado
        ")
        ->where('f.path','like', "$root/%")
        ->whereRaw("split_part(f.path,'/',4) <> ''")
        ->groupByRaw("split_part(f.path,'/',3), split_part(f.path,'/',4),
                     CASE
                       WHEN split_part(f.path,'/',5) IN ($MESES_SQL) THEN split_part(f.path,'/',5)
                       WHEN split_part(f.path,'/',6) IN ($MESES_SQL) THEN split_part(f.path,'/',6)
                       ELSE 'ANUAL'
                     END");

    if ($proveedor !== '') {
        $estados->whereRaw("LOWER(split_part(f.path,'/',3)) = LOWER(?)", [$proveedor]);
    }
    if ($tramite !== '') {
        $estados->whereRaw("split_part(f.path,'/',4) = ?", [$tramite]);
    }
    if ($mesNombre) {
        $estados->havingRaw("(CASE
                WHEN split_part(f.path,'/',5) IN ($MESES_SQL) THEN split_part(f.path,'/',5)
                WHEN split_part(f.path,'/',6) IN ($MESES_SQL) THEN split_part(f.path,'/',6)
                ELSE 'ANUAL'
            END) = ?", [$mesNombre]);
    }

    // -------- 3) JOIN: carpetas ∴ estados (VACÍO cuando no matchea) --------
    $query = DB::connection('nextcloud')
        ->query()
        ->fromSub($carpetas, 'c')
        ->leftJoinSub($estados, 'e', function($join) {
            $join->on('c.proveedor','=','e.proveedor')
                 ->on('c.tramite','=','e.tramite')
                 ->on('c.mes','=','e.mes');
        })
        ->selectRaw("
            c.proveedor,
            c.tramite,
            c.mes,
            COALESCE(e.estado, 'VACÍO') as estado
        ")
        ->orderBy('c.proveedor')
        ->orderBy('c.tramite')
        ->orderByRaw("
            CASE 
              WHEN c.mes = 'ANUAL' THEN 999
              ELSE array_position(ARRAY[$MESES_SQL], c.mes)
            END
        ");

    if ($estadoReq !== '') {
        if (mb_strtoupper($estadoReq) === 'VACÍO') {
            $query->whereRaw("e.estado IS NULL");
        } else {
            // Comparación case-insensitive por si las etiquetas tienen may/min o acentos distintos
            $query->whereRaw("e.estado ILIKE ?", [$estadoReq]);
        }
    }

    $rows = $query->get();

    return response()->json($rows);
}



}