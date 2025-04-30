<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Contrato</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }

        .table-bordered {
            border: 1px solid #000000;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #000000;
        }

        .table-bordered thead th {
            border-bottom-width: 2px;
        }
    </style>
</head>
<body>

<table border="0" style="font-size: 9pt">
    <tr style="text-align: center">
        <td>
            {{$configuracion->nombre}} <br>
            {{$configuracion->descripcion}} <br>
            {{$configuracion->direccion}} <br>
            {{$configuracion->telefono}} <br>
            {{$configuracion->email}}
        </td>
        <td width="350px"></td>
        <td style="text-align: center"><img src="{{public_path('storage/'.$configuracion->logo)}}" width="80px" alt=""></td>
    </tr>
</table>

<p style="text-align: center"><b style="font-size: 25pt"><u>Prestamo nro {{$prestamo->id}}</u></b></p>

<br>
<b>Datos del cliente:</b>
<hr>

<table border="0" class="table table-bordered" cellpadding="3">
    <tr>
        <td style="background-color: #c0c0c0"><b>Documento:</b></td>
        <td>{{$prestamo->cliente->nro_documento}}</td>
        <td style="background-color: #c0c0c0"><b>Correo Electrónico:</b></td>
        <td>{{$prestamo->cliente->email}}</td>
    </tr>
    <tr>
        <td style="background-color: #c0c0c0"><b>Cliente:</b></td>
        <td> {{$prestamo->cliente->apellidos." ".$prestamo->cliente->nombres}}</td>
        <td style="background-color: #c0c0c0"><b>Celular:</b></td>
        <td> {{$prestamo->cliente->celular}}</td>
    </tr>
    <tr>
        <td style="background-color: #c0c0c0"><b>Fecha de nacimiento:</b></td>
        <td>{{$prestamo->cliente->fecha_nacimiento}} </td>
        <td style="background-color: #c0c0c0"><b>Ref celular:</b></td>
        <td>{{$prestamo->cliente->ref_celular}} </td>
    </tr>
    <tr>
        <td style="background-color: #c0c0c0"><b>Género:</b></td>
        <td>{{$prestamo->cliente->genero}}</td>
        <td style="background-color: #c0c0c0"></td>
        <td></td>
    </tr>
</table>

<br>
<b>Detalles del prestamo:</b>
<hr>

<table border="0" class="table table-bordered" cellpadding="3">
    <tr>
        <td style="background-color: #c0c0c0"><b>Monto del prestamo:</b></td>
        <td>{{$configuracion->moneda.". ".$prestamo->monto_prestado}}</td>
        <td style="background-color: #c0c0c0"><b>Tasa de interes:</b></td>
        <td>{{$prestamo->tasa_interes}} %</td>
    </tr>
    <tr>
        <td style="background-color: #c0c0c0"><b>Modalida de pago:</b></td>
        <td>{{$prestamo->modalidad}}</td>
        <td style="background-color: #c0c0c0"><b>Nro de cuotas:</b></td>
        <td style="text-align: center">{{$prestamo->nro_cuotas}}</td>
    </tr>
    <tr>
        <td style="background-color: #c0c0c0"><b>Monto total:</b></td>
        <td>{{$configuracion->moneda.". ".$prestamo->monto_total}}</td>
        <td style="background-color: #c0c0c0"><b>Estado</b></td>
        <td>{{$prestamo->estado}}</td>
    </tr>
</table>

<br>
<b>Detalle de las cuotas:</b>
<hr>

<table border="0" class="table table-bordered" cellpadding="3">
    <thead>
    <tr style="background-color: #c0c0c0">
        <th>Nro de cuotas</th>
        <th>Fecha de pago</th>
        <th>Monto</th>
        <th>Estado</th>
    </tr>
    </thead>
    @php
    $contador = 1;
    @endphp
    @foreach($pagos as $pago)
        <tr>
            <td style="text-align: center">{{$contador++}}</td>
            <td style="text-align: center">{{$pago->fecha_pago}}</td>
            <td style="text-align: center">{{$configuracion->moneda.". ".$pago->monto_pagado}}</td>
            <td style="text-align: center">{{$pago->estado}}</td>
        </tr>
    @endforeach
</table>

</body>
</html>
