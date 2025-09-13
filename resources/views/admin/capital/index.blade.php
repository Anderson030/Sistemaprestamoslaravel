@extends('adminlte::page')

@section('title', 'Capital Empresa / Registro de capital')

@section('content_header')
  <h1><b>Capital Empresa / Registro de capital</b></h1>
  <hr>
@stop

@section('content')
@php
  // Si definiste la ruta JSON en web.php:
  // Route::get('admin/capital/resumen-json', [CapitalEmpresaController::class,'resumenJson'])->name('admin.capital.resumen');
  $resumenUrl = \Illuminate\Support\Facades\Route::has('admin.capital.resumen') ? route('admin.capital.resumen') : null;
@endphp

<div class="row">
  <div class="col-md-12">

    {{-- Mensajes --}}
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        {!! session('success') !!}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {!! session('error') !!}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
    @endif
    @if(session('info'))
      <div class="alert alert-info alert-dismissible fade show" role="alert">
        {!! session('info') !!}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
    @endif
    @if(session('warning'))
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {!! session('warning') !!}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
    @endif

    {{-- ===================== KPIs / Resumen ===================== --}}
    <div class="row">
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-success h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Caja disponible</div>
                <h4 id="kpi-caja" class="mb-0">
                  ${{ number_format($capitalDisponible ?? 0, 0, ',', '.') }}
                </h4>
              </div>
              <div class="text-success"><i class="fas fa-vault fa-2x"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-info h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Dinero circulando</div>
                <h4 id="kpi-circulando" class="mb-0">
                  ${{ number_format($dineroCirculando ?? 0, 0, ',', '.') }}
                </h4>
              </div>
              <div class="text-info"><i class="fas fa-route fa-2x"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-primary h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total general</div>
                <h4 id="kpi-total" class="mb-0">
                  ${{ number_format($totalGeneral ?? 0, 0, ',', '.') }}
                </h4>
              </div>
              <div class="text-primary"><i class="fas fa-sack-dollar fa-2x"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-dark h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Préstamos activos</div>
                <h4 id="kpi-activos" class="mb-0">{{ $prestamosActivos ?? 0 }}</h4>
              </div>
              <div class="text-dark"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
            </div>
          </div>
        </div>
      </div>

      {{-- TARJETA: Capital asignado total (bucket de ABONOS) --}}
      <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-left-purple h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">Capital asignado total</div>
                <h4 id="kpi-asignado-total" class="mb-0">
                  ${{ number_format($capitalAsignadoTotal ?? 0, 0, ',', '.') }}
                </h4>
              </div>

              {{-- Botón: pasar TODO el bucket de abonos a Caja --}}
              <form action="{{ route('admin.capital.pasar_abonos_a_caja') }}" method="POST"
                    onsubmit="return confirm('🚨 ¿Pasar TODO el saldo de Capital asignado a **Caja disponible**?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary"
                        {{ ($capitalAsignadoTotal ?? 0) <= 0 ? 'disabled' : '' }}>
                  Pasar a caja
                </button>
              </form>
            </div>

            <small class="text-muted d-block mt-2">
              🚨 <b>Importante:</b> al cerrar la liquidación, <b>traslada el saldo de Capital asignado a la Caja disponible</b> — evita descuadres en caja.
            </small>

            {{--
            <!-- OPCIONAL: Pasar un monto específico -->
            <form class="mt-2 d-flex align-items-center gap-2"
                  action="{{ route('admin.capital.pasar_a_caja_monto') }}"
                  method="POST"
                  onsubmit="return confirm('¿Pasar este monto del saldo de abonos a Caja?');">
              @csrf
              <input type="text" name="monto" id="monto_abonos" class="form-control form-control-sm"
                     style="max-width: 140px" placeholder="Ej: 100000">
              <button class="btn btn-outline-primary btn-sm" type="submit">Pasar monto</button>
            </form>
            --}}
          </div>
        </div>
      </div>
    </div>
    {{-- ===================== /KPIs ===================== --}}

    {{-- Formulario: capital inicial --}}
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">Ingresar capital total disponible</h3>
      </div>
      <div class="card-body">
        <form action="{{ route('admin.capital.store') }}" method="POST">
          @csrf
          <div class="row">
            <div class="col-md-4">
              <label for="capital_total">Capital total</label><b> (*)</b>
              <div class="input-group mb-3">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                </div>
                <input type="text" class="form-control" name="capital_total" id="capital_total" placeholder="Ej: 10000000" required>
              </div>
              @error('capital_total')
                <small class="text-danger">{{ $message }}</small>
              @enderror
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary">Guardar capital</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- Agregar capital adicional --}}
    <div class="card card-outline card-warning mt-4">
      <div class="card-header">
        <h3 class="card-title">Agregar capital adicional</h3>
      </div>
      <div class="card-body">
        <form action="{{ route('admin.capital.agregar') }}" method="POST">
          @csrf
          <div class="row">
            <div class="col-md-4">
              <label for="monto">Monto a agregar</label>
              <div class="input-group mb-3">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                </div>
                <input type="text" class="form-control" name="monto" id="monto" placeholder="Ej: 5000000" required>
              </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-warning">Agregar capital</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- Asignar capital a prestamistas --}}
    <div class="card card-outline card-info mt-4">
      <div class="card-header">
        <h3 class="card-title">Asignar capital a prestamistas</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="thead-dark">
              <tr>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Monto a asignar</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach($usuarios as $usuario)
                <tr>
                  <td>{{ $usuario->name }}</td>
                  <td>{{ $usuario->getRoleNames()->first() }}</td>
                  <td>
                    <form method="POST"
                          action="{{ route('admin.capital.asignar') }}"
                          class="form-asignar"
                          id="form-asignar-{{ $usuario->id }}"
                          data-nombre="{{ $usuario->name }}">
                      @csrf
                      <input type="text"
                             name="montos[{{ $usuario->id }}]"
                             class="form-control monto-asignar"
                             placeholder="Ej: 3.000.000">
                      <input type="hidden" name="asignar_id" value="{{ $usuario->id }}">
                      <input type="hidden" name="confirmar" value="0">
                  </td>
                  <td>
                      <button type="submit" class="btn btn-success btn-sm">Asignar</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Historial --}}
    <div class="card card-outline card-secondary mt-4">
      <div class="card-header">
        <h3 class="card-title">Historial de registros de capital</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="historialTable">
            <thead class="thead-dark">
              <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Registrado por</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach(\App\Models\RegistroCapital::with('user')->latest()->get() as $registro)
                <tr>
                  <td>{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                  <td>${{ number_format($registro->monto, 0, ',', '.') }}</td>
                  <td>{{ $registro->user->name ?? '---' }}</td>
                  <td>{{ $registro->tipo_accion ?? '---' }}</td>
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
  .border-left-success   { border-left: .25rem solid #28a745 !important; }
  .border-left-info      { border-left: .25rem solid #17a2b8 !important; }
  .border-left-primary   { border-left: .25rem solid #007bff !important; }
  .border-left-dark      { border-left: .25rem solid #343a40 !important; }
  .border-left-purple    { border-left: .25rem solid #6f42c1 !important; }
</style>
@stop

@section('js')
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // Auto-cierre de alertas
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
      if (el.classList.contains('show')) { $(el).alert('close'); }
    });
  }, 6000);

  // ==== Formateo de inputs y limpieza al enviar ====
  const inputCapital = document.getElementById("capital_total");
  if (inputCapital && inputCapital.form) {
    inputCapital.addEventListener("input", function () {
      const v = inputCapital.value.replace(/\D/g, '');
      inputCapital.value = new Intl.NumberFormat('es-CO').format(v);
    });
    inputCapital.form.addEventListener("submit", function () {
      inputCapital.value = inputCapital.value.replace(/\./g, '').replace(/,/g, '');
    });
  }

  document.querySelectorAll('.monto-asignar, #monto, #monto_abonos').forEach(input => {
    input.addEventListener("input", () => {
      const v = input.value.replace(/\D/g, '');
      input.value = new Intl.NumberFormat('es-CO').format(v);
    });
    if (input.form) {
      input.form.addEventListener("submit", function () {
        input.value = input.value.replace(/\./g, '').replace(/,/g, '');
      });
    }
  });

  // Datatable historial
  $('#historialTable').DataTable({
    responsive: true,
    autoWidth: false,
    language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
    columnDefs: [{ targets: '_all', className: 'text-center' }]
  });

  // ==== Auto-refresh de KPIs (si existe la ruta JSON) ====
  const resumenUrl = @json($resumenUrl);
  if (resumenUrl) {
    const fmtMoney = n => '$' + new Intl.NumberFormat('es-CO').format(Math.round(n || 0));
    const fmtInt   = n => new Intl.NumberFormat('es-CO').format(Math.round(n || 0));
    const setText  = (id, val, money = true) => {
      const el = document.getElementById(id);
      if (el) el.textContent = money ? fmtMoney(val) : fmtInt(val);
    };

    async function refreshKPIs() {
      try {
        const r = await fetch(resumenUrl, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        if (!r.ok) return;
        const d = await r.json();
        setText('kpi-caja',            d.capitalDisponible,    true);
        setText('kpi-circulando',      d.dineroCirculando,     true);
        setText('kpi-total',           d.totalGeneral,         true);
        setText('kpi-activos',         d.prestamosActivos,     false);
        setText('kpi-asignado-total',  d.capitalAsignadoTotal, true);
      } catch (_) {}
    }
    refreshKPIs();
    setInterval(refreshKPIs, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshKPIs(); });
  }

  // ==== Confirmación al asignar capital ====
  document.querySelectorAll('.form-asignar').forEach(form => {
    form.addEventListener('submit', function (e) {
      const inputMonto = form.querySelector('.monto-asignar');
      const hiddenConf = form.querySelector('input[name="confirmar"]');
      const nombre     = form.dataset.nombre || '';

      let raw   = (inputMonto && inputMonto.value ? inputMonto.value : '').toString().replace(/\D/g, '');
      let monto = parseInt(raw || '0', 10);

      if (!monto || monto <= 0) {
        e.preventDefault();
        alert('Ingresa un monto válido para asignar.');
        if (hiddenConf) hiddenConf.value = '0';
        return;
      }

      const montoFmt = new Intl.NumberFormat('es-CO').format(monto);
      const ok = confirm(`⚠️ ¿Estás seguro de asignar $ ${montoFmt}${nombre ? (' a ' + nombre) : ''}?`);

      if (!ok) {
        e.preventDefault();
        if (hiddenConf) hiddenConf.value = '0';
        // Si prefieres enviar igual para que el backend registre "Operación cancelada",
        // elimina el e.preventDefault() y deja hiddenConf en "0".
        return;
      }

      if (hiddenConf) hiddenConf.value = '1';
    });
  });
});
</script>
@stop
