@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de usuarios </b></h1>
    <hr>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <div class="row w-100">
                    <div class="col-md-6 d-flex align-items-center">
                        <h3 class="card-title mb-0">Usuarios registrados</h3>
                    </div>
                    <div class="col-md-6 text-md-right text-start mt-2 mt-md-0">
                        <a href="{{url('/admin/usuarios/create')}}" class="btn btn-primary">Crear nuevo</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                        <tr>
                            <th style="text-align: center">Nro</th>
                            <th style="text-align: center">Rol del usuario</th>
                            <th style="text-align: center">Nombre</th>
                            <th style="text-align: center">Email</th>
                            <th style="text-align: center">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $contador = 1; @endphp
                        @foreach($usuarios as $usuario)
                            <tr>
                                <td style="text-align: center">{{ $contador++ }}</td>
                                <td>{{ $usuario->roles->pluck('name')->implode(', ') }}</td>
                                <td>{{ $usuario->name }}</td>
                                <td>{{ $usuario->email }}</td>
                                <td style="text-align: center">
                                    <div class="btn-group" role="group">
                                        <a href="{{ url('/admin/usuarios', $usuario->id) }}" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                        <a href="{{ url('/admin/usuarios/' . $usuario->id . '/edit') }}" class="btn btn-success btn-sm"><i class="fas fa-pencil-alt"></i></a>
                                        @if(!$usuario->roles->pluck('name')->contains('ADMINISTRADOR'))
                                            <form action="{{ url('/admin/usuarios', $usuario->id) }}" method="POST" id="miFormulario{{$usuario->id}}" onclick="preguntar{{$usuario->id}}(event)">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <script>
                                                function preguntar{{$usuario->id}}(event) {
                                                    event.preventDefault();
                                                    Swal.fire({
                                                        title: '¿Desea eliminar este registro?',
                                                        icon: 'question',
                                                        showDenyButton: true,
                                                        confirmButtonText: 'Eliminar',
                                                        confirmButtonColor: '#a5161d',
                                                        denyButtonColor: '#270a0a',
                                                        denyButtonText: 'Cancelar',
                                                    }).then((result) => {
                                                        if (result.isConfirmed) {
                                                            document.getElementById('miFormulario{{$usuario->id}}').submit();
                                                        }
                                                    });
                                                }
                                            </script>
                                        @endif
                                    </div>
                                </td>
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
        .btn-info    { background-color: #17a2b8; border: none; }
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
        $(function () {
            $("#example1").DataTable({
                "pageLength": 5,
                "language": {
                    "emptyTable": "No hay información",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ Usuarios",
                    "infoEmpty": "Mostrando 0 a 0 de 0 Usuarios",
                    "infoFiltered": "(Filtrado de _MAX_ total Usuarios)",
                    "lengthMenu": "Mostrar _MENU_ Usuarios",
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
