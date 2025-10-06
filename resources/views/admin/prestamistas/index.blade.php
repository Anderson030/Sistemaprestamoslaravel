@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de Prestamistas</b></h1>
    <hr>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
                <h3 class="card-title mb-2 mb-md-0">Prestamistas registrados</h3>
                <form id="form-reiniciar-totales" action="{{ route('admin.prestamistas.reset') }}" method="POST" style="margin-top: 10px;">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-sync-alt"></i> Reiniciar Totales
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Capital Asignado</th>
                            <th>Total Prestado</th>
                            <th>Total Cobrado</th>
                            <th>Total Recaudado</th>
                            <th>Ganancia Total</th>
                            <th>Ganancia Cobrada</th>
                            <th>Clientes Atendidos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prestamistas as $prestamista)
                            <tr>
                                <td>{{ $prestamista['usuario']->name }}</td>
                                    <td>
                                    ${{ number_format($prestamista['capital_asignado'], 0, ',', '.') }}
                                    @if($prestamista['capital_asignado'] > 0)
                                        <form action="{{ route('admin.prestamistas.eliminarCapital', $prestamista['usuario']->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar capital asignado"
                                                onclick="return confirm('¿Estás seguro de eliminar el capital asignado de este prestamista?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>

                                <td>${{ number_format($prestamista['prestado'], 0, ',', '.') }}</td>
                                <td>${{ number_format($prestamista['cobrado'], 0, ',', '.') }}</td>
                                <td>${{ number_format($prestamista['recaudado'], 0, ',', '.') }}</td>
                                <td>${{ number_format($prestamista['ganancia'], 0, ',', '.') }}</td>
                                <td>${{ number_format($prestamista['ganancia_real'], 0, ',', '.') }}</td>
                                <td>{{ $prestamista['clientes'] }}</td>
                                <td>
                                    <a href="{{ route('admin.prestamistas.detalle', $prestamista['usuario']->id) }}" class="btn btn-sm btn-primary">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <th><strong>TOTALES GENERALES</strong></th>
                            <th><strong>${{ number_format($totalCapitalAsignado, 0, ',', '.') }}</strong></th>
                            <th><strong>${{ number_format($totalPrestado, 0, ',', '.') }}</strong></th>
                            <th><strong>${{ number_format($totalCobrado, 0, ',', '.') }}</strong></th>
                            <th><strong>${{ number_format($totalRecaudado, 0, ',', '.') }}</strong></th>
                            <th><strong>${{ number_format($totalGanancia, 0, ',', '.') }}</strong></th>
                            <th><strong>${{ number_format($totalGananciaCobrada, 0, ',', '.') }}</strong></th>
                            <th><strong>{{ $totalClientes }}</strong></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
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
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
        $(function () {
            $("#example1").DataTable({
                "pageLength": 5,
                "language": {
                    "emptyTable": "No hay información",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                    "infoFiltered": "(Filtrado de _MAX_ total registros)",
                    "lengthMenu": "Mostrar _MENU_ registros",
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

    <script>
        document.getElementById('form-reiniciar-totales').addEventListener('submit', function (e) {
            e.preventDefault();
            const clave = prompt('Ingresa la clave para reiniciar los totales:');
            if (clave === '123') {
                this.submit();
            } else {
                alert('❌ Clave incorrecta. No se reiniciaron los totales.');
            }
        });
    </script>
@stop
