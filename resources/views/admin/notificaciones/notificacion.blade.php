<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<h1>Notificación de pago</h1>
<hr>
<p>Usted tiene un pago atrado, le pedimos que realize el pago lo mas antes posible.</p>
<hr>
<p>
    <b>Detalle del pago:</b> <br><br>

    <b>Monto: {{$pago->monto_pagado}}</b> <br>
    <b>Fecha de pago: {{$pago->fecha_pago}}</b> <br>
    <b>{{$pago->referencia_pago}}</b> <br>
    <b>Estado: </b> {{$pago->estado}} <br>

</p>

<small>Gracias por su atención.</small>

</body>
</html>
