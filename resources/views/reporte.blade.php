@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">Reporte de Documentación</h2>

    <form id="filtros" class="row g-3 mb-3">
        <div class="col-md-3">
            <label for="proveedor" class="form-label">Proveedor</label>
            <select id="proveedor" name="proveedor" class="form-select">
                <option value="">(Todos)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="tramite" class="form-label">Trámite</label>
            <select id="tramite" name="tramite" class="form-select">
                <option value="">(Todos)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="mes" class="form-label">Mes</label>
            <select id="mes" name="mes" class="form-select">
            </select>
        </div>
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">(Todos)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="tabla-reporte">
            <thead class="table-light">
                <tr>
                    <th>Proveedor</th>
                    <th>Trámite</th>
                    <th>Mes</th>
                    <th style="width:140px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", async function () {
    const form  = document.getElementById("filtros");
    const tbody = document.querySelector("#tabla-reporte tbody");



    const selProveedor = document.getElementById('proveedor');
    const selTramite   = document.getElementById('tramite');
    const selMes       = document.getElementById('mes');
    const selEstado    = document.getElementById('estado');


    async function cargarFilters() {
        const r = await fetch(`/reporte/filters`);
        const j = await r.json();


        j.proveedores.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p; opt.textContent = p;
            selProveedor.appendChild(opt);
        });

 
        j.tramites.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t; opt.textContent = t;
            selTramite.appendChild(opt);
        });


        selMes.innerHTML = '';
        selMes.insertAdjacentHTML('beforeend', `<option value="TODOS">(Todos)</option>`);
        j.meses.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.value;
            opt.textContent = m.label;
            if (m.value === j.mesActual) opt.selected = true;
            selMes.appendChild(opt);
        });


        j.estados.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.value;
            opt.textContent = e.label;
            selEstado.appendChild(opt);
        });
    }

    // se fija los filtros del fronnt
    async function cargarDatos() {
        const params = new URLSearchParams(new FormData(form)).toString();
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>`;
        const r = await fetch(`/reporte/data?${params}`);
        const data = await r.json();

        tbody.innerHTML = '';
        if (!data.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">Sin resultados</td></tr>`;
            return;
        }
        data.forEach(row => {
            const badgeClass =
                row.estado === 'OK' ? 'bg-success' :
                row.estado === 'MAL' ? 'bg-danger' : 'bg-warning';
            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${row.proveedor ?? ''}</td>
                    <td>${row.tramite ?? ''}</td>
                    <td>${row.mes ?? ''}</td>
                    <td><span class="badge ${badgeClass}">${row.estado}</span></td>
                </tr>
            `);
        });
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        cargarDatos();
    });
    selProveedor.addEventListener('change', cargarDatos);
    selTramite.addEventListener('change', cargarDatos);
    selMes.addEventListener('change', cargarDatos);
    selEstado.addEventListener('change', cargarDatos);

    // Init
    await cargarFilters();
    await cargarDatos();
});
</script>
@endpush
