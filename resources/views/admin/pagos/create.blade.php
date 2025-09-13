@extends('adminlte::page')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><b>Pagos/Registro de un nuevo pago</b></h1>
        <div>
            {{-- Botón Retanqueo al lado del título --}}
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#retanqueoModal">
                <i class="fas fa-sync-alt"></i> Retanqueo
            </button>
        </div>
    </div>
    <hr>
@stop

@section('content')

@php
    // Por si no viene seteada desde el controlador
    $abonosPorCuota = $abonosPorCuota ?? collect();

    // Totales para el modal de retanqueo y para la tarjeta "Saldo actual"
    $total_pagado_confirmado = $pagos->where('estado','Confirmado')->sum('monto_pagado');
    $total_abonos_vista = collect($abonosPorCuota)->sum(); // suma de todos los abonos del préstamo
    // Prioridad: 1) $saldoActual del controller, 2) accesor del modelo, 3) fallback local
    $saldo_actual = $saldoActual
        ?? ($prestamo->saldo_actual ?? max(0, $prestamo->monto_total - ($total_pagado_confirmado + $total_abonos_vista)));
@endphp

    <div class="row">
        <div class="col-md-4">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos del cliente</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p>
                                <i class="fas fa-id-card"></i> {{$prestamo->cliente->nro_documento}} <br><br>
                                <i class="fas fa-user"></i> {{$prestamo->cliente->apellidos." ".$prestamo->cliente->nombres}} <br><br>
                                <i class="fas fa-calendar"></i> {{$prestamo->cliente->fecha_nacimiento}} <br><br>
                                <i class="fas fa-user-check"></i> {{$prestamo->cliente->genero}} <br><br>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <i class="fas fa-envelope"></i> {{$prestamo->cliente->email}} <br><br>
                                <i class="fas fa-phone"></i> {{$prestamo->cliente->celular}} <br><br>
                                <i class="fas fa-phone"></i> {{$prestamo->cliente->ref_celular}} <br><br>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- col -->

        <div class="col-md-2">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos del prestamo</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
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

                                <b>Saldo actual</b> <br>
                                <i class="fas fa-balance-scale"></i>
                                ${{ number_format($saldo_actual, 0, ',', '.') }}<br><br>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- col -->

        <div class="col-md-6">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos de los pagos</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-sm table-striped table-hover table-bordered">
                                <thead>
                                <tr>
                                    <th style="text-align: center">Nro de cuota</th>
                                    <th style="text-align: center">Monto de la cuota</th>
                                    <th style="text-align: center">Fecha de pago</th>
                                    <th style="text-align: center">Estado</th>
                                    <th style="text-align: center">Fecha cancelado</th>
                                    <th style="text-align: center">Acción</th>
                                    <th style="text-align: center">Pago parcial</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php $contador = 1; @endphp
                                @foreach($pagos as $pago)
                                    @php
                                        // Valor real de la cuota (desde la fila de pagos, no promediado)
                                        $cuota_valor = (float) $pago->monto_pagado;
                                        $abonado     = (float) ($abonosPorCuota[$contador] ?? 0);
                                        $restante    = max(0, $cuota_valor - $abonado);
                                    @endphp
                                    <tr>
                                        <td style="text-align: center">{{$contador}}</td>

                                        <td style="text-align: center">
                                            ${{ number_format($cuota_valor, 0, ',', '.') }}
                                            @if($abonado > 0)
                                                <br><small class="text-success">abonado: ${{ number_format($abonado, 0, ',', '.') }}</small>
                                                <br><small class="text-warning">resta: ${{ number_format($restante, 0, ',', '.') }}</small>
                                            @endif
                                        </td>

                                        <td style="text-align: center">{{$pago->fecha_pago}}</td>
                                        <td style="text-align: center">{{$pago->estado}}</td>
                                        <td style="text-align: center">{{$pago->fecha_cancelado}}</td>

                                        <td>
                                            @if($pago->estado == "Confirmado")
                                                <button type="button" class="btn btn-danger btn-sm">Cancelado</button>
                                                <a href="{{url('/admin/pagos/comprobantedepago',$pago->id)}}" class="btn btn-warning btn-sm"><i class="fas fa-print"></i> Imprimir</a>
                                            @else
                                                @if($restante <= 0)
                                                    <button type="button" class="btn btn-secondary btn-sm" disabled>Cuota completa</button>
                                                @else
                                                    <form action="{{url('/admin/pagos/create',$pago->id)}}" method="post"
                                                          onclick="preguntar{{$pago->id}}(event)" id="miFormulario{{$pago->id}}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm" style="border-radius: 4px">
                                                            <i class="fas fa-money-bill"></i> Pagar ${{ number_format($restante, 0, ',', '.') }}
                                                        </button>
                                                    </form>
                                                    <script>
                                                        function preguntar{{$pago->id}}(event) {
                                                            event.preventDefault();
                                                            Swal.fire({
                                                                title: '¿Esta seguro de registrar el pago?',
                                                                text: '',
                                                                icon: 'question',
                                                                showDenyButton: true,
                                                                confirmButtonText: 'Si',
                                                                confirmButtonColor: '#a5161d',
                                                                denyButtonColor: '#270a0a',
                                                                denyButtonText: 'Cancelar',
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    var form = $('#miFormulario{{$pago->id}}');
                                                                    form.submit();
                                                                }
                                                            });
                                                        }
                                                    </script>
                                                @endif
                                            @endif
                                        </td>

                                        <td style="text-align: center">
                                            @if($pago->estado != "Confirmado")
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    data-toggle="modal"
                                                    data-target="#pagoParcialModal"
                                                    data-cuota="{{$contador}}"
                                                    data-montocuota="{{$cuota_valor}}"
                                                    data-restante="{{$restante}}"
                                                >
                                                    Pago parcial
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    @php $contador++; @endphp
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- card-body -->
            </div>
        </div><!-- col -->
    </div><!-- row -->

    {{-- MODAL: Retanqueo --}}
    <div class="modal fade" id="retanqueoModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" action="{{ route('admin.prestamos.retanqueo', $prestamo->id) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Retanqueo</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <input type="hidden" name="modo" value="liquidar">

          <div class="modal-body">
            <div class="alert alert-info">
              <div><b>Saldo préstamo actual:</b> ${{ number_format($saldo_actual, 0, ',', '.') }}</div>
              <small>Se usará parte del nuevo principal para liquidar el saldo; el resto se entrega al cliente.</small>
            </div>

            <div class="form-group">
              <label>Principal nuevo *</label>
              {{-- Visible con formato COP --}}
              <input type="text" class="form-control" id="principalNuevoDisplay" inputmode="numeric" autocomplete="off" placeholder="$ 0">
              {{-- Oculto: es el que se envía al backend --}}
              <input type="hidden" name="principal_nuevo" id="principalNuevo" value="">
            </div>

            <div class="form-group">
              <label>Tasa de interés (%) *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="tasa_interes" id="tasaInteres" value="{{ $prestamo->tasa_interes }}" required>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Modalidad *</label>
                <select name="modalidad" class="form-control" required>
                  <option {{ $prestamo->modalidad=='Diario'?'selected':'' }}>Diario</option>
                  <option {{ $prestamo->modalidad=='Semanal'?'selected':'' }}>Semanal</option>
                  <option {{ $prestamo->modalidad=='Quincenal'?'selected':'' }}>Quincenal</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>Nro. cuotas *</label>
                <input type="number" class="form-control" name="nro_cuotas" value="{{ $prestamo->nro_cuotas }}" min="1" required>
              </div>
            </div>

            <div class="alert alert-secondary">
              <b>Total nuevo (con interés):</b> <span id="totalNuevoPreview">0</span>
            </div>

            <div class="form-group">
              <label>Observaciones</label>
              <textarea class="form-control" name="observaciones" rows="2"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" type="submit">Confirmar retanqueo</button>
          </div>
        </form>
      </div>
    </div>

    {{-- MODAL: Pago parcial --}}
    <div class="modal fade" id="pagoParcialModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" action="{{ route('admin.abonos.store', $prestamo->id) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Pago parcial de la cuota <span id="ppCuotaNro"></span></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body">
            <input type="hidden" name="nro_cuota" id="ppNroCuota">

            <div class="form-group">
              <label>Monto a abonar *</label>
              {{-- Visible con formato COP --}}
              <input type="text" class="form-control" id="ppMontoDisplay" inputmode="numeric" autocomplete="off" placeholder="$ 0" required>
              {{-- Oculto: el que se envía al backend --}}
              <input type="hidden" name="monto" id="ppMonto" value="">
              <small id="ppInfoRestante" class="text-muted"></small>
            </div>

            <div class="form-group">
              <label>Referencia (opcional)</label>
              <input type="text" name="referencia" class="form-control">
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" type="submit">Registrar abono</button>
          </div>
        </form>
      </div>
    </div>

@stop

@section('css')
@stop

@section('js')
<script>
(function(){
  const fmtCOP = new Intl.NumberFormat('es-CO');

  function digitsOnly(str){ return (str || '').replace(/[^\d]/g, ''); }

  /** Enlaza un input visible (con formato) con uno oculto (valor numérico sin separadores).
   *  Soporta onChange(num) para lógica adicional (ej. tope de abono). */
  function bindMoney(displayEl, hiddenEl, onChange){
    if(!displayEl || !hiddenEl) return;

    displayEl.addEventListener('input', () => {
      let raw = digitsOnly(displayEl.value);
      let num = parseInt(raw || '0', 10);

      // hook opcional (p.ej. limitar a restante)
      if(typeof onChange === 'function'){
        const adjusted = onChange(num);
        if (typeof adjusted === 'number') num = adjusted;
      }

      hiddenEl.value = isNaN(num) ? '' : String(num);
      displayEl.value = num ? ('$ ' + fmtCOP.format(num)) : '';
    });

    displayEl.addEventListener('focus', () => {
      setTimeout(() => {
        const len = displayEl.value.length;
        displayEl.setSelectionRange(len, len);
      }, 0);
    });
  }

  // ------- Retanqueo: preview del total -------
  var tasa = document.getElementById('tasaInteres');
  var totalOut = document.getElementById('totalNuevoPreview');
  function calcTotalNuevo(){
    const principal = parseFloat((document.getElementById('principalNuevo')?.value || '0'));
    const t = parseFloat(tasa?.value || '0');
    const total = principal * (1 + (t/100));
    if(totalOut) totalOut.textContent = fmtCOP.format(Math.round(total || 0));
  }

  bindMoney(
    document.getElementById('principalNuevoDisplay'),
    document.getElementById('principalNuevo'),
    function(){ calcTotalNuevo(); } // recalcula total cuando cambia el principal
  );
  if(tasa){ tasa.addEventListener('input', calcTotalNuevo); }
  calcTotalNuevo();

  // ------- Pago Parcial: setear datos al abrir modal + tope -------
  var restanteActual = 0;

  $('#pagoParcialModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var nro = button.data('cuota');
    var mc  = parseFloat(button.data('montocuota') || 0);
    var rest = parseFloat(button.data('restante') || 0);

    restanteActual = isNaN(rest) ? 0 : rest;

    $('#ppCuotaNro').text(nro);
    $('#ppNroCuota').val(nro);
    $('#ppInfoRestante').text(
      'Resta: $' + fmtCOP.format(Math.round(restanteActual)) +
      ' de $' + fmtCOP.format(Math.round(mc))
    );

    // limpiar valor previo del monto
    $('#ppMontoDisplay').val('');
    $('#ppMonto').val('');
  });

  // Enlazar input de pago parcial con formato y tope (no dejar pasar el restante)
  bindMoney(
    document.getElementById('ppMontoDisplay'),
    document.getElementById('ppMonto'),
    function(num){
      if(num > restanteActual) return restanteActual;
      if(num < 0) return 0;
      return num;
    }
  );
})();
</script>
@stop
