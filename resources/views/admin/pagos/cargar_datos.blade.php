@extends('adminlte::page')

@section('content_header')
    <h1><b>Pagos/Registro de un nuevo pago</b></h1>
    <hr>
@stop

@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Datos del cliente</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">

                    <div class="row">
                        <div class="col-md-6">
                            <label for="">Busqueda del cliente</label><b> (*)</b>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                </div>
                                <select name="cliente_id" id="" class="form-control select2">
                                    <option value="">Buscar cliente...</option>
                                    @foreach($clientes as $cliente)
                                        <option value="{{$cliente->id}}" {{$datosCliente->id == $cliente->id ? 'selected':''}}>{{$cliente->nro_documento." - ".$cliente->apellidos." ".$cliente->nombres}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    @error('cliente_id')
                    <small style="color: red">{{$message}}</small>
                    @enderror
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos del cliente</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p>
                                <i class="fas fa-id-card"></i> {{$datosCliente->nro_documento}} <br><br>
                                <i class="fas fa-user"></i> {{$datosCliente->apellidos." ".$datosCliente->nombres}} <br><br>
                                <i class="fas fa-calendar"></i> {{$datosCliente->fecha_nacimiento}} <br><br>
                                <i class="fas fa-user-check"></i> {{$datosCliente->genero}} <br><br>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <i class="fas fa-envelope"></i> {{$datosCliente->email}} <br><br>
                                <i class="fas fa-phone"></i> {{$datosCliente->celular}} <br><br>
                                <i class="fas fa-phone"></i> {{$datosCliente->ref_celular}} <br><br>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>

        <div class="col-md-3">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos del prestamo</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p>
                                <b>Monto prestado</b> <br>
                                <i class="fas fa-money-bill-wave"></i> {{$prestamo->monto_prestado}} <br><br>

                                <b>Tasa de interes</b> <br>
                                <i class="fas fa-percentage"></i> {{$prestamo->tasa_interes}} <br><br>

                                <b>Modalidad</b> <br>
                                <i class="fas fa-calendar-alt"></i> {{$prestamo->modalidad}} <br><br>

                                <b>Nro de cuotas</b> <br>
                                <i class="fas fa-list"></i> {{$prestamo->nro_cuotas}} cuotas<br><br>

                                <b>Monto Total</b> <br>
                                <i class="fas fa-money-bill-alt"></i> {{$prestamo->monto_total}}<br><br>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>

    </div>


@stop

@section('css')
    <style>
        .select2-container .select2-selection--single {
            height: 40px !important; /* Ajusta la altura total del select */
        }
    </style>
@stop

@section('js')
    <script>
        $('.select2').select2({});

        $('.select2').on('change',function () {
            var id = $(this).val();
            //alert(cliente_id);
            if(id){
                window.location.href ="{{url('/admin/pagos/create/')}}"+"/"+id;
            }
        });

        function calcularPrestamo() {
            // Obtener valores del formulario
            const montoPrestado = parseFloat(document.getElementById('monto_prestado').value);
            const tasaInteres = parseFloat(document.getElementById('tasa_interes').value) / 100;
            const modalidad = document.getElementById('modalidad').value;
            const nroCuotas = parseInt(document.getElementById('nro_cuotas').value);

            if (isNaN(montoPrestado) || isNaN(tasaInteres) || isNaN(nroCuotas) || montoPrestado <= 0 || tasaInteres < 0 || nroCuotas <= 0) {
                alert("Por favor ingrese valores válidos.");
                return;
            }

            // Ajustar la tasa de interés según la modalidad
            let tasaInteresAjustada = 0;

            switch (modalidad) {
                case "Diario":
                    tasaInteresAjustada = tasaInteres / 30; // Suponiendo 30 días en un mes
                    break;
                case "Semanal":
                    tasaInteresAjustada = tasaInteres / 4; // Suponiendo 4 semanas en un mes
                    break;
                case "Quincenal":
                    tasaInteresAjustada = tasaInteres / 2; // Suponiendo 2 quincenas en un mes
                    break;
                case "Mensual":
                    tasaInteresAjustada = tasaInteres; // Tasa mensual directa
                    break;
                case "Anual":
                    tasaInteresAjustada = tasaInteres * 12; // Multiplicar por 12 para convertir a mensual
                    break;
                default:
                    alert("Modalidad no válida.");
                    return;
            }

            // Cálculo del interés total
            const interesTotal = montoPrestado * tasaInteresAjustada * nroCuotas;

            // Cálculo del total a pagar
            const totalCancelar = montoPrestado + interesTotal;

            // Cálculo de la cuota fija
            const cuota = totalCancelar / nroCuotas;

            $('#monto_cuota').val(cuota.toFixed(2));
            $('#monto_cuota2').val(cuota.toFixed(2));
            $('#monto_interes').val(interesTotal.toFixed(2));
            $('#monto_final').val(totalCancelar.toFixed(2));
            $('#monto_final2').val(totalCancelar.toFixed(2));

            // Mostrar los resultados en el HTML
            /* alert(`Resultado del préstamo:
                     Monto Prestado: $${montoPrestado}
                     Tasa de Interés Ajustada: ${(tasaInteresAjustada * 100).toFixed(2)}%
                     Modalidad: ${modalidad}
                     Número de Cuotas: ${nroCuotas}
                     Interés Total: $${interesTotal.toFixed(2)}
                     Cuota por Pagar: $${cuota.toFixed(2)}
                     Total a Cancelar: $${totalCancelar.toFixed(2)}`); */
        }

    </script>
@stop
