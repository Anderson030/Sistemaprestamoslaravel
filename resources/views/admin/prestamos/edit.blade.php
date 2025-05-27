@extends('adminlte::page')

@section('content_header')
    <h1><b>Préstamos / Modificar datos del préstamo</b></h1>
    <hr>
@stop

@section('content')
    <form action="{{ route('admin.prestamos.update', $prestamo->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-12">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">Datos del cliente</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Buscar cliente <b>(*)</b></label>
                                <div class="input-group mb-3">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <select name="cliente_id" class="form-control select2" required>
                                        <option value="">Seleccione un cliente...</option>
                                        @foreach($clientes as $cliente)
                                            <option value="{{ $cliente->id }}" {{ $prestamo->cliente_id == $cliente->id ? 'selected' : '' }}>
                                                {{ $cliente->nro_documento . ' - ' . $cliente->apellidos . ' ' . $cliente->nombres }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('cliente_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div id="contenido_cliente" style="display:block;">
                            <div class="row mt-3">
                                <div class="col-md-3"><label>Documento</label><input class="form-control" value="{{ $prestamo->cliente->nro_documento }}" disabled></div>
                                <div class="col-md-3"><label>Nombres</label><input class="form-control" value="{{ $prestamo->cliente->nombres }}" disabled></div>
                                <div class="col-md-3"><label>Apellidos</label><input class="form-control" value="{{ $prestamo->cliente->apellidos }}" disabled></div>
                                <div class="col-md-3"><label>Fecha Nacimiento</label><input class="form-control" value="{{ $prestamo->cliente->fecha_nacimiento }}" disabled></div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3"><label>Género</label><input class="form-control" value="{{ $prestamo->cliente->genero }}" disabled></div>
                                <div class="col-md-3"><label>Email</label><input class="form-control" value="{{ $prestamo->cliente->email }}" disabled></div>
                                <div class="col-md-3"><label>Celular</label><input class="form-control" value="{{ $prestamo->cliente->celular }}" disabled></div>
                                <div class="col-md-3"><label>Referencia Celular</label><input class="form-control" value="{{ $prestamo->cliente->ref_celular }}" disabled></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos del préstamo -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-warning">
                    <div class="card-header"><h3 class="card-title">Datos del préstamo</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <label>Monto prestado</label>
                                <input type="number" step="1000" name="monto_prestado" id="monto_prestado" value="{{ $prestamo->monto_prestado }}" class="form-control" required>
                                @error('monto_prestado')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-1">
                                <label>Tasa (%)</label>
                                <input type="number" name="tasa_interes" id="tasa_interes" value="{{ $prestamo->tasa_interes }}" class="form-control" required>
                                @error('tasa_interes')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-2">
                                <label>Modalidad</label>
                                <select name="modalidad" id="modalidad" class="form-control" required>
                                    @foreach(['Diario','Semanal','Quincenal','Mensual','Anual'] as $modo)
                                        <option value="{{ $modo }}" {{ $prestamo->modalidad == $modo ? 'selected' : '' }}>{{ $modo }}</option>
                                    @endforeach
                                </select>
                                @error('modalidad')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-1">
                                <label>Cuotas</label>
                                <input type="number" name="nro_cuotas" id="nro_cuotas" value="{{ $prestamo->nro_cuotas }}" class="form-control" required>
                                @error('nro_cuotas')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-2">
                                <label>Fecha préstamo</label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $prestamo->fecha_inicio }}" class="form-control" required>
                                @error('fecha_inicio')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" onclick="calcularPrestamo()" class="btn btn-success w-100"><i class="fas fa-calculator"></i> Calcular préstamo</button>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-2">
                                <label>Monto cuota</label>
                                <input type="text" id="monto_cuota" class="form-control" disabled>
                                <input type="hidden" id="monto_cuota2" name="monto_cuota">
                            </div>

                            <div class="col-md-2">
                                <label>Interés total</label>
                                <input type="text" id="monto_interes" class="form-control" disabled>
                            </div>

                            <div class="col-md-2">
                                <label>Total a pagar</label>
                                <input type="text" id="monto_final" class="form-control" disabled>
                                <input type="hidden" id="monto_final2" name="monto_total">
                            </div>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-success">Modificar préstamo</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@section('css')
    <style>.select2-container .select2-selection--single { height: 40px !important; }</style>
@stop

@section('js')
<script>
    $('.select2').select2();

    $('.select2').on('change', function () {
        const id = $(this).val();
        if (id) {
            $.ajax({
                url: "{{ url('/admin/prestamos/cliente') }}/" + id,
                type: 'GET',
                success: function (cliente) {
                    $('#contenido_cliente').show();
                    $('#nro_documento').val(cliente.nro_documento);
                    $('#nombres').val(cliente.nombres);
                    $('#apellidos').val(cliente.apellidos);
                    $('#fecha_nacimiento').val(cliente.fecha_nacimiento);
                    $('#genero').val(cliente.genero);
                    $('#email').val(cliente.email);
                    $('#celular').val(cliente.celular);
                    $('#ref_celular').val(cliente.ref_celular);
                }
            });
        }
    });

    function calcularPrestamo() {
        const monto = parseFloat($('#monto_prestado').val());
        const tasaBase = parseFloat($('#tasa_interes').val()) / 100;
        const cuotas = parseInt($('#nro_cuotas').val());
        const modalidad = $('#modalidad').val();

        if (!monto || !tasaBase || !cuotas) return;

        let baseCuotas = 0;

        switch (modalidad) {
            case 'Diario': baseCuotas = 30; break;
            case 'Semanal': baseCuotas = 4; break;
            case 'Quincenal': baseCuotas = 2; break;
            case 'Mensual': baseCuotas = 1; break;
            case 'Anual': baseCuotas = 1; break;
            default: baseCuotas = 1;
        }

        let totalInteres = 0;

        if (cuotas <= baseCuotas) {
            totalInteres = monto * tasaBase;
        } else {
            const adicionales = cuotas - baseCuotas;
            const interesBase = monto * tasaBase;
            const interesAdicional = monto * (tasaBase / baseCuotas) * adicionales;
            totalInteres = interesBase + interesAdicional;
        }

        const total = monto + totalInteres;
        const cuota = total / cuotas;

        $('#monto_cuota').val('$ ' + cuota.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
        $('#monto_cuota2').val(cuota.toFixed(2));
        $('#monto_interes').val('$ ' + totalInteres.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
        $('#monto_final').val('$ ' + total.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
        $('#monto_final2').val(total.toFixed(2));
    }

    window.onload = calcularPrestamo;
    $('#monto_prestado, #tasa_interes, #modalidad, #nro_cuotas').on('change keyup', calcularPrestamo);
</script>
@stop
