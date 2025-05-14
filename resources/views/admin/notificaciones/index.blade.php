@extends('adminlte::page')

@section('content_header')
    <h1><b>Notificaciones / Listado de pagos</b></h1>
    <hr>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Pagos pendientes</h3>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                    <thead>
<tr>
    <th class="text-center">Nro</th>
    <th class="text-center">Documento</th>
    <th class="text-center">Cliente</th>
    <th class="text-center">Celular</th>
    <th class="text-center">Ref. Celular</th>
    <th class="text-center">Cuota Pagada</th>
    <th class="text-center">Nro de cuotas</th>
    <th class="text-center">Fecha de pago</th>
    <th class="text-center">Dirección</th>
    <th class="text-center">Comentario</th>
    <th class="text-center">Acción</th>
</tr>
</thead>
<tbody>
@php $contador = 1; @endphp
@foreach($pagos as $pago)
    @if($pago->fecha_cancelado == null)
        <tr>
            <td class="text-center">{{ $contador++ }}</td>
            <td class="text-center">{{ $pago->prestamo->cliente->nro_documento }}</td>
            <td>{{ $pago->prestamo->cliente->apellidos . ' ' . $pago->prestamo->cliente->nombres }}</td>
            <td class="text-center">{{ $pago->prestamo->cliente->celular }}</td>
            <td class="text-center">{{ $pago->prestamo->cliente->ref_celular }}</td>
            <td class="text-center">{{ number_format($pago->monto_pagado, 0, ',', '.') }}</td>
            <td class="text-center">{{ $pago->referencia_pago }}</td>
            <td class="text-center"
                style="@if($pago->fecha_pago == date('Y-m-d')) background-color: #f7f61e; @elseif($pago->fecha_pago < date('Y-m-d')) background-color: #f77c7b; @endif">
                {{ $pago->fecha_pago }}
            </td>
            <td>{{ $pago->prestamo->cliente->direccion }}</td>
            <td>{{ $pago->prestamo->cliente->comentario }}</td>
            <td class="text-center">
                <div class="btn-group" role="group">
                    <a href="https://wa.me/{{ $pago->prestamo->cliente->celular }}?text=Hola cliente, {{ $pago->prestamo->cliente->apellidos . ' ' . $pago->prestamo->cliente->nombres }}, usted tiene una cuota atrasada. Por favor realice el pago lo más pronto posible. Atte: {{ $configuracion->nombre }}"
                       target="_blank" class="btn btn-success btn-sm">
                        <i class="fas fa-phone"></i> Celular
                    </a>
                    <a href="{{ url('/admin/notificaciones/notificar', $pago->id) }}" class="btn btn-info btn-sm">
                        <i class="fas fa-envelope"></i> Enviar correo
                    </a>
                </div>
            </td>
        </tr>
    @endif
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
        #example1_wrapper .dt-buttons {
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

        #example1_wrapper .btn {
            color: #fff;
            border-radius: 4px;
            padding: 5px 15px;
            font-size: 14px;
            white-space: nowrap;
        }

        .btn-danger { background-color: #dc3545; border: none; }
        .btn-success { background-color: #28a745; border: none; }
        .btn-info { background-color: #17a2b8; border: none; }
        .btn-warning { background-color: #ffc107; color: #212529; border: none; }
        .btn-default { background-color: #6e7176; color: #212529; border: none; }

        @media (max-width: 768px) {
            td, th {
                white-space: nowrap;
            }
        }
    </style>
@stop

@section('js')
    <script>
        $('.select2').select2();

        $('.select2').on('change', function () {
            var id = $(this).val();
            if (id) {
                window.location.href = "{{ url('/admin/pagos/prestamos/cliente/') }}/" + id;
            }
        });

        $(function () {
            $("#example1").DataTable({
                "pageLength": 5,
                "language": {
                    "emptyTable": "No hay información",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ Pagos",
                    "infoEmpty": "Mostrando 0 a 0 de 0 Pagos",
                    "infoFiltered": "(Filtrado de _MAX_ total Pagos)",
                    "lengthMenu": "Mostrar _MENU_ Pagos",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscador:",
                    "zeroRecords": "Sin resultados encontrados",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                },
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                buttons: [
                    { text: '<i class="fas fa-copy"></i> COPIAR', extend: 'copy', className: 'btn btn-default' },
                    { text: '<i class="fas fa-file-pdf"></i> PDF', extend: 'pdf', className: 'btn btn-danger' },
                    { text: '<i class="fas fa-file-csv"></i> CSV', extend: 'csv', className: 'btn btn-info' },
                    { text: '<i class="fas fa-file-excel"></i> EXCEL', extend: 'excel', className: 'btn btn-success' },
                    { text: '<i class="fas fa-print"></i> IMPRIMIR', extend: 'print', className: 'btn btn-warning' }
                ]
            }).buttons().container().appendTo('#example1_wrapper .row:eq(0)');
        });
    </script>
@stop
