@extends('adminlte::page')

@section('title', 'Auditorías Diarias')
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true) {{-- Buttons, Responsive, etc. --}}

@section('content_header')
    <h1><b>Auditorías – Ingresos vs Salidas por día</b></h1>
    <hr>
@stop

@section('content')
<div class="row justify-content-center">
  <div class="col-md-12">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">Resumen diario</h3>
      </div>

      <div class="card-body">

        {{-- Filtros por rango de fechas --}}
        <form method="GET" class="row g-2 mb-3">
          <div class="col-sm-3">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" class="form-control">
          </div>
          <div class="col-sm-3">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}" class="form-control">
          </div>
          <div class="col-sm-2 align-self-end">
            <button class="btn btn-primary w-100">Filtrar</button>
          </div>
        </form>

        <div class="table-responsive">
          <table id="tablaAuditorias" class="table table-bordered table-hover table-striped table-sm">
            <thead>
              <tr>
                <th style="text-align:center">Fecha</th>
                <th style="text-align:center">Total Prestado 💸</th>
                <th style="text-align:center">Total Cobrado 💰</th>
                <th style="text-align:center">Balance del día 🔄</th>
                <th style="text-align:center">Préstamos 💹</th>
                <th style="text-align:center">Pagos 💲</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($auditoria as $d)
                <tr>
                  <td class="text-center">{{ \Carbon\Carbon::parse($d->dia)->format('d/m/Y') }}</td>
                  <td>$ {{ number_format($d->total_prestado,0,',','.') }}</td>
                  <td>$ {{ number_format($d->total_cobrado,0,',','.') }}</td>
                  <td class="{{ $d->balance_dia >= 0 ? 'text-success' : 'text-danger' }}">
                    $ {{ number_format($d->balance_dia,0,',','.') }}
                  </td>
                  <td class="text-center">{{ $d->nro_prestamos }}</td>
                  <td class="text-center">{{ $d->nro_pagos }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
@stop

@section('css')
<style>
  /* Botones DataTables organizados y con scroll en móvil (mismo estilo que Clientes) */
  #tablaAuditorias_wrapper .dt-buttons{
    background-color: transparent;
    box-shadow: none;
    border: none;
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    justify-content: flex-start;
    gap: 10px;
    margin-bottom: 15px;
  }
  #tablaAuditorias_wrapper .btn{
    color:#fff;
    border-radius:4px;
    padding:5px 15px;
    font-size:14px;
    white-space:nowrap;
  }
  .btn-danger{ background-color:#dc3545; border:none; }
  .btn-success{ background-color:#28a745; border:none; }
  .btn-info{ background-color:#17a2b8; border:none; }
  .btn-warning{ background-color:#ffc107; color:#212529; border:none; }
  .btn-default{ background-color:#6e7176; color:#212529; border:none; }

  @media (max-width: 768px){
    td, th{ white-space:nowrap; }
  }
</style>
@stop

@section('js')
<script>
$(function () {
  $('#tablaAuditorias').DataTable({
    pageLength: 5,
    language: {
      emptyTable: "No hay información",
      info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty: "Mostrando 0 a 0 de 0 registros",
      infoFiltered: "(Filtrado de _MAX_ registros)",
      lengthMenu: "Mostrar _MENU_ registros",
      loadingRecords: "Cargando...",
      processing: "Procesando...",
      search: "Buscador:",
      zeroRecords: "Sin resultados encontrados",
      paginate: {
        first: "Primero", last: "Último",
        next: "Siguiente", previous: "Anterior"
      }
    },
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[0,'desc']],
    buttons: [
      { text:'<i class="fas fa-copy"></i> COPIAR',  extend:'copy',  className:'btn btn-default' },
      { text:'<i class="fas fa-file-pdf"></i> PDF',  extend:'pdf',   className:'btn btn-danger' },
      { text:'<i class="fas fa-file-csv"></i> CSV',  extend:'csv',   className:'btn btn-info' },
      { text:'<i class="fas fa-file-excel"></i> EXCEL', extend:'excel', className:'btn btn-success' },
      { text:'<i class="fas fa-print"></i> IMPRIMIR', extend:'print', className:'btn btn-warning' }
    ]
  }).buttons().container().appendTo('#tablaAuditorias_wrapper .row:eq(0)');
});
</script>
@stop
