<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Comprobante de pago</title>
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

<table border="0" style="font-size: 8pt">
    <tr style="text-align: center">
        <td>
            <img src="{{public_path('storage/'.$configuracion->logo)}}" width="50px" alt=""> <br>
            {{$configuracion->nombre}} <br>
            {{$configuracion->descripcion}} <br>
            {{$configuracion->direccion}} <br>
              </td>
        <td width="300px"></td>
        <td style="text-align: center">
            <b>Nro de pago: </b>{{$pago->id}} <br>
            <h3>ORIGINAL</h3>
        </td>
    </tr>
</table>

<p style="text-align: center"><b style="font-size: 18pt"><u>COMPROBANTE DE PAGO</u></b></p>

<b>Datos del cliente:</b>
<hr>

<table class="table" cellpadding="2">
    <tr>
        <td><b>Fecha: </b> {{$fecha_literal}}</td>
        <td><b>Nro de documento: </b> {{$cliente->nro_documento}}</td>
    </tr>
    <tr>
        <td><b>Señor(es):</b> {{$cliente->apellidos." ".$cliente->nombres}} </td>
    </tr>
</table>

<hr>
<b>Datos del pago:</b>
<hr>

<table class="table table-bordered" cellpadding="2">
    <tr>
        <th style="background-color: #c0c0c0">Nro</th>
        <th style="background-color: #c0c0c0">Detalle</th>
        <th style="background-color: #c0c0c0">Monto pagado</th>
    </tr>
    <tr>
        <td style="text-align: center">1</td>
        <td>
            <span>
                <b>Pago del prestamo: </b>{{$prestamo->id}} <br>
                <b>Metodo de pago: </b>{{$pago->metodo_pago}} <br>
                <b>{{$pago->referencia_pago}}</b>
            </span>
        </td>
        <td style="text-align: center">
            {{$configuracion->moneda.". ".$pago->monto_pagado}}
        </td>
    </tr>
</table>
<br>

<table style="text-align: center" class="table">
    <tr>
        <td><b>______________________________ <br> {{$configuracion->nombre}} </b> <br> Usuario: {{Auth::user()->name}}  </td>
        <td><b>______________________________ <br> Cliente <br></b> {{$cliente->apellidos." ".$cliente->nombres}}</td>
    </tr>
</table>

--------------------------------------------------------------------------------------------------------------------------------------


<table border="0" style="font-size: 8pt">
    <tr style="text-align: center">
        <td>
            <img src="{{public_path('storage/'.$configuracion->logo)}}" width="50px" alt=""> <br>
            {{$configuracion->nombre}} <br>
            {{$configuracion->descripcion}} <br>
            {{$configuracion->direccion}} <br>
        </td>
        <td width="300px"></td>
        <td style="text-align: center">
            <b>Nro de pago: </b>{{$pago->id}} <br>
            <h3>COPIA</h3>
        </td>
    </tr>
</table>

<p style="text-align: center"><b style="font-size: 18pt"><u>COMPROBANTE DE PAGO</u></b></p>

<b>Datos del cliente:</b>
<hr>

<table class="table" cellpadding="2">
    <tr>
        <td><b>Fecha: </b> {{$fecha_literal}}</td>
        <td><b>Nro de documento: </b> {{$cliente->nro_documento}}</td>
    </tr>
    <tr>
        <td><b>Señor(es):</b> {{$cliente->apellidos." ".$cliente->nombres}} </td>
    </tr>
</table>

<hr>
<b>Datos del pago:</b>
<hr>

<table class="table table-bordered" cellpadding="2">
    <tr>
        <th style="background-color: #c0c0c0">Nro</th>
        <th style="background-color: #c0c0c0">Detalle</th>
        <th style="background-color: #c0c0c0">Monto pagado</th>
    </tr>
    <tr>
        <td style="text-align: center">1</td>
        <td>
            <span>
                <b>Pago del prestamo: </b>{{$prestamo->id}} <br>
                <b>Metodo de pago: </b>{{$pago->metodo_pago}} <br>
                <b>{{$pago->referencia_pago}}</b>
            </span>
        </td>
        <td style="text-align: center">
            {{$configuracion->moneda.". ".$pago->monto_pagado}}
        </td>
    </tr>
</table>
<br>

<table style="text-align: center" class="table">
    <tr>
        <td><b>______________________________ <br> {{$configuracion->nombre}} </b> <br> Usuario: {{Auth::user()->name}}  </td>
        <td><b>______________________________ <br> Cliente <br></b> {{$cliente->apellidos." ".$cliente->nombres}}</td>
    </tr>
</table>









</body>
</html>
