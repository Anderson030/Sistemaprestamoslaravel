@extends('adminlte::page')

@section('content_header')
    <h1><b>Prestamos/Registro de un nuevo préstamo</b></h1>
    <hr>
@stop

@section('content')
    <form action="{{url('admin/prestamos/create')}}" method="post">
        @csrf
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Datos del cliente</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Búsqueda del cliente</label><b> (*)</b>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    </div>
                                    <select name="cliente_id" class="form-control select2">
                                        <option value="">Buscar cliente...</option>
                                        @foreach($clientes as $cliente)
                                            <option value="{{$cliente->id}}">{{$cliente->nro_documento." - ".$cliente->apellidos." ".$cliente->nombres}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        @error('cliente_id')
                        <small style="color: red">{{$message}}</small>
                        @enderror

                        <hr>

                        <div id="contenido_cliente" style="display: none;">
                            <div class="row">
                                <div class="col-md-3"><label>Documento</label><input type="text" class="form-control" id="nro_documento" disabled></div>
                                <div class="col-md-3"><label>Nombres</label><input type="text" class="form-control" id="nombres" disabled></div>
                                <div class="col-md-3"><label>Apellidos</label><input type="text" class="form-control" id="apellidos" disabled></div>
                                <div class="col-md-3"><label>Fecha de ingreso a la plataforma</label><input type="date" class="form-control" id="fecha_nacimiento" disabled></div>
                            </div>
                            <div class="row">
                                <div class="col-md-3"><label>Categoría</label><input type="text" class="form-control" id="categoria" disabled></div>
                                <div class="col-md-3"><label>Email</label><input type="email" class="form-control" id="email" disabled></div>
                                <div class="col-md-3"><label>Celular</label><input type="number" class="form-control" id="celular" disabled></div>
                                <div class="col-md-3"><label>Referencia Celular</label><input type="number" class="form-control" id="ref_celular" disabled></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Datos del préstamo --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">Datos del préstamo</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <label>Monto del préstamo</label>
                                <select id="monto_prestado" name="monto_prestado" class="form-control select2_montos" required>
                                    <option value="">Selecciona o escribe un monto...</option>
                                    @for ($i = 100000; $i <= 1000000; $i += 100000)
                                        <option value="{{ $i }}">{{ number_format($i, 0, ',', '.') }}</option>
                                    @endfor
                                </select>
                                @error('monto_prestado')
                                <small style="color: red">{{$message}}</small>
                                @enderror
                            </div>

                            <div class="col-md-1">
                                <label>Tasa interés</label>
                                <input type="text" id="tasa_interes" name="tasa_interes" value="20" class="form-control">
                            </div>

                            <div class="col-md-1">
                                <label>Modalidad</label>
                                <select name="modalidad" id="modalidad" class="form-control">
                                    <option value="Diario">Diario</option>
                                    <option value="Semanal">Semanal</option>
                                    <option value="Quincenal">Quincenal</option>
                                </select>
                            </div>

                            <div class="col-md-1">
                                <label>Nro cuotas</label>
                                <input type="number" id="nro_cuotas" name="nro_cuotas" class="form-control" required>
                            </div>

                            <div class="col-md-2">
                                <label>Fecha préstamo</label>
                                <input type="date" id="fecha_prestamo" name="fecha_inicio" class="form-control" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                            </div>

                            <div class="col-md-2" style="margin-top: 30px;">
                                <button type="button" class="btn btn-success" onclick="calcularPrestamo();"><i class="fas fa-calculator"></i> Calcular préstamo</button>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-2">
                                <label>Monto por cuota</label>
                                <input type="text" id="monto_cuota" class="form-control" disabled>
                                <input type="hidden" id="monto_cuota2" name="monto_cuota">
                            </div>
                            <div class="col-md-2">
                                <label>Monto del interés</label>
                                <input type="text" id="monto_interes" class="form-control" disabled>
                            </div>
                            <div class="col-md-2">
                                <label>Monto final</label>
                                <input type="text" id="monto_final" class="form-control" disabled>
                                <input type="hidden" id="monto_final2" name="monto_total">
                            </div>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-primary">Registrar préstamo</button>

                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@section('css')
    <style>
        .select2-container .select2-selection--single {
            height: 40px !important;
        }
    </style>
@stop

@section('js')
@section('js')
<script>
    let tasaAutorizada = 20; // Por defecto

    $('.select2').select2({});
    $('.select2_montos').select2({
        tags: true,
        placeholder: "Selecciona o escribe un monto...",
        allowClear: true,
        createTag: function (params) {
            var term = params.term.trim();
            if (isNaN(term) || parseInt(term) <= 0) {
                return null;
            }
            return {
                id: term,
                text: new Intl.NumberFormat('es-CO').format(term)
            }
        },
        templateSelection: function (data) {
            if (data.id) {
                return '$ ' + new Intl.NumberFormat('es-CO').format(data.id);
            }
            return data.text;
        },
        templateResult: function (data) {
            if (data.id) {
                return '$ ' + new Intl.NumberFormat('es-CO').format(data.id);
            }
            return data.text;
        }
    });

    $('.select2').on('change', function () {
        var id = $(this).val();
        if (id) {
            $.ajax({
                url: "{{ url('/admin/prestamos/cliente/') }}/" + id,
                type: 'GET',
                success: function (cliente) {
                    $('#contenido_cliente').show();
                    $('#nro_documento').val(cliente.nro_documento);
                    $('#nombres').val(cliente.nombres);
                    $('#apellidos').val(cliente.apellidos);
                    $('#fecha_nacimiento').val(cliente.fecha_nacimiento);
                    $('#categoria').val(cliente.categoria);
                    $('#email').val(cliente.email);
                    $('#celular').val(cliente.celular);
                    $('#ref_celular').val(cliente.ref_celular);
                },
                error: function () {
                    alert('No se pudo obtener la información del cliente.');
                }
            });
        }
    });

    // Validar % interés
    $('#tasa_interes').on('change', function () {
        const tasa = parseFloat($(this).val());
        if (tasa !== 20) {
            const clave = prompt('Has ingresado una tasa diferente al 20%. Ingresa la clave de autorización o contacta al administrador Victor o David:');
            if (clave !== 'Emiluna24') {
                alert('Clave incorrecta. Solo se permite 20% de interés.');
                $(this).val(20);
                tasaAutorizada = 20;
            } else {
                alert('Tasa autorizada correctamente.');
                tasaAutorizada = tasa;
            }
        } else {
            tasaAutorizada = 20;
        }

        calcularPrestamo();
    });

    function calcularPrestamo() {
    let montoRaw = $('#monto_prestado').val();
    if (!montoRaw) return;

    montoRaw = montoRaw.toString().replace(/[^\d]/g, '');
    const montoPrestado = parseFloat(montoRaw);
    const nroCuotas = parseInt($('#nro_cuotas').val());
    const modalidad = $('#modalidad').val();

    if (isNaN(montoPrestado) || isNaN(nroCuotas) || montoPrestado <= 0 || nroCuotas <= 0) return;

    // Cuotas por mes según modalidad
    let cuotasPorMes = 30; // Default: Diario
    if (modalidad === 'Semanal') cuotasPorMes = 4;
    else if (modalidad === 'Quincenal') cuotasPorMes = 2;

    // Calcular número total de meses (redondeado hacia arriba)
    const meses = Math.ceil(nroCuotas / cuotasPorMes);

    // Interés total según meses * tasa base (20% por mes)
    const tasaTotal = tasaAutorizada * meses;

    const interes = (montoPrestado * tasaTotal) / 100;
    const totalCancelar = montoPrestado + interes;
    const cuota = totalCancelar / nroCuotas;

    $('#monto_cuota').val('$ ' + cuota.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
    $('#monto_interes').val('$ ' + interes.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
    $('#monto_final').val('$ ' + totalCancelar.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'));

    $('#monto_cuota2').val(cuota.toFixed(2));
    $('#monto_final2').val(totalCancelar.toFixed(2));
}

    // Recalcular si se cambian los valores
    $('#monto_prestado').on('change', function () {
        setTimeout(calcularPrestamo, 100); // Espera a que select2 termine de asignar el valor
    });

    $('#nro_cuotas').on('change keyup', calcularPrestamo);
</script>

@stop

@stop
