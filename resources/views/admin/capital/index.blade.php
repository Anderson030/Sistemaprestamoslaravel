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
                            <div class="form-group">
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
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Guardar capital</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Mostrar último capital registrado --}}
        @if($capital)
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
                    </div>
                    <div class="col-md-4">
                        <strong>Fecha de registro:</strong><br>
                        {{ $capital->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulario para asignar capital a usuarios --}}
        <div class="card card-outline card-info mt-4">
            <div class="card-header">
                <h3 class="card-title">Asignar capital a prestamistas</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.capital.asignar') }}">
                    @csrf
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
                                           <input type="text" name="montos[{{ $usuario->id }}]" class="form-control monto-asignar">
                                        </td>
                                        <td>
                                            <button type="submit" name="asignar_id" value="{{ $usuario->id }}" class="btn btn-success">
                                                Asignar
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
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
    // Formateo del input capital_total al escribir
    const input = document.getElementById("capital_total");
    if (input) {
        input.addEventListener("input", function () {
            let value = input.value.replace(/\D/g, '');
            value = new Intl.NumberFormat('es-CO').format(value);
            input.value = value;
        });

        input.form.addEventListener("submit", function () {
            input.value = input.value.replace(/\./g, '').replace(/,/g, '');
        });
    }

    // Aplicar formato pesos colombianos a inputs tipo number en la tabla de asignación
 const inputsMonto = document.querySelectorAll('.monto-asignar');

inputsMonto.forEach(input => {
    input.addEventListener("input", () => {
        let raw = input.value.replace(/\D/g, '');
        if (raw.length > 0) {
            input.value = new Intl.NumberFormat('es-CO').format(raw);
        } else {
            input.value = '';
        }
    });

    // Formatear antes de enviar
    input.closest("form").addEventListener("submit", () => {
        inputsMonto.forEach(input => {
            input.value = input.value.replace(/\./g, '').replace(/,/g, '');
        });
    });
});


    // Activar DataTables con filtros
    $('table.table').DataTable({
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
