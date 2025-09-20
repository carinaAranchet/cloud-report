<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkObrasSanitariasHistorico extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mark-obras-sanitarias-historico';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asigna la etiqueta Histórico a aquellos archivos de la semana pasada, los lunes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
       $root = 'files/Proveedores habituales';

        // Buscar ID de la etiqueta "Histórico"
        $tagId = DB::connection('nextcloud')
            ->table('oc_systemtag')
            ->where('name', 'Histórico')
            ->value('id');

        if (!$tagId) {
            $this->error('No existe la etiqueta "Histórico" en Nextcloud. Creala primero en el cloud.');
            return;
        }

        // Calcular cutoff: comienzo de la semana actual (lunes 00:00)

        // Seleccionar archivos en Obras sanitarias que:
        // - tengan alguna etiqueta distinta de Histórico
        // - y sean anteriores al lunes actual
        $archivos = DB::connection('nextcloud')
            ->table('oc_filecache as f')
            ->join('oc_systemtag_object_mapping as m', DB::raw('m.objectid::bigint'), '=', 'f.fileid')
            ->join('oc_systemtag as t', 't.id', '=', 'm.systemtagid')
            ->select('f.fileid', 'f.path')
            ->where('f.path','like', "$root/%/Obras sanitarias/%")
            ->whereRaw("f.mtime < EXTRACT(EPOCH FROM date_trunc('week', now()))")
            ->where('t.name', '<>', 'Histórico')
            ->distinct()
            ->get();

        $count = 0;

        foreach ($archivos as $a) {
            // Insertar la etiqueta Histórico si no existe ya
            DB::connection('nextcloud')
                ->table('oc_systemtag_object_mapping')
                ->updateOrInsert(
                    [
                        'objectid'   => $a->fileid,
                        'systemtagid'=> $tagId,
                        'objecttype' => 'files'
                    ],
                    [] // no actualiza nada si ya existe
                );
            $count++;
            \Log::info("[MarkObrasSanitariasHistorico] Marcado como Histórico", [
                'fileid' => $a->fileid,
                'path'   => $a->path,
            ]);
        }

        $this->info("Marcados $count archivos como Histórico.");
        \Log::info("[MarkObrasSanitariasHistorico] Total marcados: {$archivos->count()}");
    }
}
