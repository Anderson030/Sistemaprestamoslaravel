@extends('adminlte::page')

@section('title', 'Pagos Parciales')
@section('content_header')
    <h1>Notas de Pagos Parciales</h1>
@stop

@section('content')
    <a href="{{ route('admin.pagos_parciales.create') }}" class="btn btn-primary mb-3">Agregar nueva nota</a>

    <div class="table-responsive">
        <table class="table table-bordered">
        <thead>
    <tr>
        <th>Cédula</th>
        <th>Cliente</th>
        <th>Nota</th>
        @unless(auth()->user()->hasRole('PRESTAMISTA'))
            <th>Prestamista</th>
        @endunless
        <th>Fecha</th>
        <th>Acciones</th> {{-- Nueva columna --}}
    </tr>
</thead>
<tbody>
    @foreach ($pagosParciales as $pago)
        <tr>
            <td>{{ $pago->cliente->nro_documento ?? '-' }}</td>
            <td>{{ $pago->cliente->nombres ?? '-' }}</td>
            <td>{{ $pago->nota }}</td>
            @unless(auth()->user()->hasRole('PRESTAMISTA'))
                <td>{{ $pago->usuario->name ?? '-' }}</td>
            @endunless
            <td>{{ $pago->created_at->format('d/m/Y') }}</td>
            <td>
                <form action="{{ route('admin.pagos_parciales.destroy', $pago->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta nota?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm">Eliminar</button>
                </form>
            </td>
        </tr>
    @endforeach
</tbody>

        </table>
    </div>
@stop
