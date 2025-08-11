@extends('adminlte::page')

@section('content_header')
    <h1><b>Capital Empresa / Registro de capital</b></h1>
    <hr>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Formulario para ingresar nuevo capital --}}
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
                                <small style="color: red">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Guardar capital</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($capital)
        {{-- Mostrar último capital registrado --}}
        <div class="card card-outline card-success mt-4">
            <div class="card-header">
                <h3 class="card-title">Último registro de capital</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total registrado:</strong><br>
                        ${{ number_format($capital->capital_total, 0, ',', '.') }}
                    </div>
                    <div class="col-md-4">
    <strong>Capital disponible:</strong><br>
    ${{ number_format($capital->capital_disponible, 0, ',', '.') }}
@if(isset($capital->capital_anterior))
    @php
        $diferencia = $capital->capital_disponible - $capital->capital_anterior;
    @endphp


        @if($diferencia > 0)
            <span class="text-success">📈 +${{ number_format($diferencia, 0, ',', '.') }}</span>
        @elseif($diferencia < 0)
            <span class="text-danger">📉 -${{ number_format(abs($diferencia), 0, ',', '.') }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    @endif
</div>

                    <div class="col-md-4">
                        <strong>Fecha de registro:</strong><br>
                        {{ $capital->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
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

        {{-- Asignar capital a usuarios --}}
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
                                        <form method="POST" action="{{ route('admin.capital.asignar') }}">
                                            @csrf
                                            <input type="text" name="montos[{{ $usuario->id }}]" class="form-control monto-asignar" placeholder="Ej: 3000000">
                                            <input type="hidden" name="asignar_id" value="{{ $usuario->id }}">
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

        {{-- Historial de registros de capital --}}
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

        @endif
    </div>
</div>
@stop

@section('js')
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Formatear y limpiar capital total
    const inputCapital = document.getElementById("capital_total");
    if (inputCapital) {
        inputCapital.addEventListener("input", function () {
            let value = inputCapital.value.replace(/\D/g, '');
            inputCapital.value = new Intl.NumberFormat('es-CO').format(value);
        });

        inputCapital.form.addEventListener("submit", function () {
            inputCapital.value = inputCapital.value.replace(/\./g, '').replace(/,/g, '');
        });
    }

    // Formatear inputs de monto adicional y de asignación
    const allInputs = document.querySelectorAll('.monto-asignar, #monto');
    allInputs.forEach(input => {
        input.addEventListener("input", () => {
            let value = input.value.replace(/\D/g, '');
            input.value = new Intl.NumberFormat('es-CO').format(value);
        });

        // ✅ Este es el cambio clave
        input.form.addEventListener("submit", function () {
            const targetInput = input; // solo el que está en este formulario
            targetInput.value = targetInput.value.replace(/\./g, '').replace(/,/g, '');
        });
    });

    // Inicializar DataTable
    $('#historialTable').DataTable({
        responsive: true,
        autoWidth: false,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
        },
        columnDefs: [
            { targets: '_all', className: 'text-center' }
        ]
    });
});

</script>
@stop
