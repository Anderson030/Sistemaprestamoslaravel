@extends('adminlte::page')

@section('title', 'Auditorías Diarias')
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)

@section('content_header')
  <h1><b>Auditorías – Ingresos vs Salidas por día</b></h1>
  <hr>
@stop

@section('content')
<div class="row justify-content-center">
  <div class="col-md-12">
    <div class="card card-outline card-primary">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title m-0">Resumen diario</h3>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-secondary mr-2" data-toggle="modal" data-target="#modalGasto">
            <i class="fas fa-plus-circle"></i> Registrar gasto del día
          </button>
        </div>
      </div>

        @if(session('ok'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('ok') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        @endif

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
                <th class="text-center">Fecha</th>
                <th class="text-center">Total Prestado 💸</th>
                <th class="text-center">Total Cobrado 💰</th>
                <th class="text-center">Pagos Parciales 🧮</th>
                <th class="text-center">Gastos del día 🧾</th>
                <th class="text-center">Balance del día ⚖️</th>
                <th class="text-center">Asignado (del día) 🧱</th>
                <th class="text-center">Préstamos 💹</th>
                <th class="text-center">Pagos 💲</th>
                <th class="text-center">Descripción(es) 📝</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($auditorias as $d)
                <tr>
                  {{-- data-order con formato ISO para ordenar bien --}}
                  <td class="text-center" data-order="{{ $d->dia }}">
                    {{ \Carbon\Carbon::parse($d->dia)->format('d/m/Y') }}
                  </td>
                  <td>$ {{ number_format($d->total_prestado,0,',','.') }}</td>
                  <td>$ {{ number_format($d->total_cobrado,0,',','.') }}</td>
                  <td>$ {{ number_format($d->pagos_parciales ?? 0,0,',','.') }}</td>
                  <td>$ {{ number_format($d->gastos_dia ?? 0,0,',','.') }}</td>

                  @php $balance = $d->balance ?? 0; @endphp
                  <td class="{{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                    $ {{ number_format($balance,0,',','.') }}
                  </td>

                  <td>$ {{ number_format($d->asignado_dia ?? 0,0,',','.') }}</td>
                  <td class="text-center">{{ $d->nro_prestamos }}</td>
                  <td class="text-center">{{ $d->nro_pagos }}</td>
                  <td>{{ $d->descripciones ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Modal gasto --}}
<div class="modal fade" id="modalGasto" tabindex="-1" aria-labelledby="modalGastoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="modalGastoLabel">Registrar gasto del día</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="formGasto" method="POST" action="{{ route('admin.auditorias.gastos.store') }}">
        @csrf
        <input type="hidden" name="modal" value="gasto">
        {{-- campo oculto (numérico limpio) que enviamos al servidor --}}
        <input type="hidden" id="monto_gasto" name="monto" value="0">
        <div class="modal-body">
          @if ($errors->any() && old('modal') === 'gasto')
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="form-group">
            <label for="fecha">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $hasta }}" required>
          </div>

          <div class="form-group">
            <label for="monto_gasto_display">Monto (0 si no hubo)</label>
            {{-- campo visible con formato COP --}}
            <input type="text" id="monto_gasto_display" class="form-control" inputmode="numeric" placeholder="$ 0" value="$ 0">
          </div>

          <div class="form-group">
            <label for="descripcion">Descripción (opcional)</label>
            <input type="text" name="descripcion" id="descripcion" class="form-control" placeholder="Ej: gasolina, plan, papelería">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-secondary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal pagos parciales del día (simplificado) --}}
<div class="modal fade" id="modalPagoParcial" tabindex="-1" aria-labelledby="modalPagoParcialLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalPagoParcialLabel">Registrar pago parcial del día</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="formParcial" method="POST" action="{{ route('admin.auditorias.pagosparciales.store') }}">
        @csrf
        <input type="hidden" name="modal" value="pagoparcial">
        {{-- campo oculto (numérico limpio) --}}
        <input type="hidden" id="monto_parcial" name="monto" value="">
        <div class="modal-body">
          @if ($errors->any() && old('modal') === 'pagoparcial')
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="form-group">
            <label for="pp_fecha">Fecha</label>
            <input type="date" name="fecha" id="pp_fecha" class="form-control" value="{{ $hasta }}" required>
          </div>

          <div class="form-group">
            <label for="pp_monto_display">Monto</label>
            {{-- visible con formato COP --}}
            <input type="text" id="pp_monto_display" class="form-control" inputmode="numeric" placeholder="$ 0" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@stop

@section('css')
<style>
  #tablaAuditorias_wrapper .dt-buttons{
    background-color: transparent;
    box-shadow: none;
    border: none;
    display:flex; flex-wrap:nowrap; overflow-x:auto;
    justify-content:flex-start; gap:10px; margin-bottom:15px;
  }
  #tablaAuditorias_wrapper .btn{
    color:#fff; border-radius:4px; padding:5px 15px; font-size:14px; white-space:nowrap;
  }
  .btn-danger{ background-color:#dc3545; border:none; }
  .btn-success{ background-color:#28a745; border:none; }
  .btn-info{ background-color:#17a2b8; border:none; }
  .btn-warning{ background-color:#ffc107; color:#212529; border:none; }
  .btn-default{ background-color:#6e7176; color:#212529; border:none; }
  @media (max-width:768px){ td,th{ white-space:nowrap; } }
</style>
@stop

@section('js')
<script>
$(function () {
  /* ================= DataTable ================ */
  const dt = $('#tablaAuditorias').DataTable({
    pageLength: 5,
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[0,'desc']], // usa data-order (YYYY-MM-DD)
    buttons: [
      { text:'<i class="fas fa-copy"></i> COPIAR',  extend:'copy',  className:'btn btn-default' },
      { text:'<i class="fas fa-file-pdf"></i> PDF',  extend:'pdf',   className:'btn btn-danger' },
      { text:'<i class="fas fa-file-csv"></i> CSV',  extend:'csv',   className:'btn btn-info' },
      { text:'<i class="fas fa-file-excel"></i> EXCEL', extend:'excel', className:'btn btn-success' },
      { text:'<i class="fas fa-print"></i> IMPRIMIR', extend:'print', className:'btn btn-warning' }
    ],
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
      paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
    }
  });
  dt.buttons().container().appendTo('#tablaAuditorias_wrapper .row:eq(0)');

  /* ============ Formateo COP en modales ============ */
  const nfCOP = new Intl.NumberFormat('es-CO');

  function toDigits(str){
    return (str || '').toString().replace(/[^\d]/g,'');
  }
  function formatCOPFromDigits(digs){
    if(!digs) return '$ 0';
    return '$ ' + nfCOP.format(parseInt(digs,10));
  }

  // --- Gasto ---
  const $gastoVisible = $('#monto_gasto_display');
  const $gastoHidden  = $('#monto_gasto');

  // valor inicial
  $gastoVisible.val(formatCOPFromDigits($gastoHidden.val()));

  $gastoVisible.on('input', function(){
    const digits = toDigits(this.value);
    $gastoVisible.val(formatCOPFromDigits(digits));
    $gastoHidden.val(digits || 0);
  });

  $('#formGasto').on('submit', function(){
    // asegurar número limpio
    const digits = toDigits($gastoVisible.val());
    $gastoHidden.val(digits || 0);
  });

  // --- Pago Parcial ---
  const $ppVisible = $('#pp_monto_display');
  const $ppHidden  = $('#monto_parcial');

  $ppVisible.on('input', function(){
    const digits = toDigits(this.value);
    $ppVisible.val(formatCOPFromDigits(digits));
    $ppHidden.val(digits);
  });

  $('#formParcial').on('submit', function(){
    const digits = toDigits($ppVisible.val());
    $ppHidden.val(digits);
  });

  // Auto-abrir el modal correcto si hubo errores de validación
  const modal = @json(old('modal'));
  if (modal === 'gasto') {
    $('#modalGasto').modal('show');
  } else if (modal === 'pagoparcial') {
    $('#modalPagoParcial').modal('show');
  }
});
</script>
@stop
