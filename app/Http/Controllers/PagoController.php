<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use App\Models\Abono; // Para calcular abonos por cuota
use App\Models\CapitalPrestamista;
use App\Models\MovimientoCapitalPrestamista;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PagoController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();

        // Si es PRESTAMISTA, solo ver pagos de sus préstamos
        $pagosQuery = Pago::query()->orderBy('id', 'desc');
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            $pagosQuery->whereIn('prestamo_id', function ($q) {
                $q->select('id')
                  ->from('prestamos')
                  ->where('idusuario', auth()->id());
            });
        }
        $pagos = $pagosQuery->get();

        foreach ($pagos as $p) {
            $nroCuota = $this->resolverNroCuota($p);

            $abonosCuota = (float) Abono::where('prestamo_id', $p->prestamo_id)
                ->where('nro_cuota', $nroCuota)
                ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

            // Si está confirmado, monto_real_pagado = cuota - abonos; si no, 0
            $p->monto_real_pagado = $p->estado === 'Confirmado'
                ? max(0.0, (float) $p->monto_pagado - $abonosCuota)
                : 0.0;

            $p->es_pago_parcial = $abonosCuota > 0;
            $p->nro_cuota_calc  = $nroCuota;
        }

        return view('admin.pagos.index', compact('pagos', 'clientes'));
    }

    public function cargar_prestamos_cliente($id)
    {
        $cliente = Cliente::findOrFail($id);

        $prestamos = Prestamo::query()
            ->where('cliente_id', $cliente->id)
            ->when(auth()->user()->hasRole('PRESTAMISTA'), function ($q) {
                $q->where('idusuario', auth()->id());
            })
            ->get();

        return view('admin.pagos.cargar_prestamos_cliente', compact('cliente', 'prestamos'));
    }

    public function create($id)
    {
        // Guard: prestamista solo puede ver sus préstamos
        $prestamo = Prestamo::with('cliente')->findOrFail($id);
        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.pagos.index')
                ->with('mensaje', 'No tienes permiso para operar este préstamo.')
                ->with('icono', 'error');
        }

        $pagos = Pago::where('prestamo_id', $id)
            ->orderBy('fecha_pago')
            ->get();

        $abonosPorCuota = Abono::where('prestamo_id', $id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        // SALDO SIN DOBLE CONTEO
        $totalConfirmado = (float) $pagos->where('estado', 'Confirmado')->sum('monto_pagado');

        $porNro = [];
        foreach ($pagos as $i => $p) {
            $porNro[$i + 1] = ['estado' => $p->estado, 'monto' => (float) $p->monto_pagado];
        }

        $abonosPendientes = 0.0;
        foreach ($abonosPorCuota as $nro => $total) {
            $estado = $porNro[$nro]['estado'] ?? 'Pendiente';
            $montoCuota = (float) ($porNro[$nro]['monto'] ?? 0);
            if ($estado !== 'Confirmado') {
                $abonosPendientes += min((float) $total, $montoCuota);
            }
        }

        $saldoCalc = max(0.0, (float) $prestamo->monto_total - ($totalConfirmado + $abonosPendientes));
        $saldoActual = property_exists($prestamo, 'saldo_actual') ? ($prestamo->saldo_actual ?? $saldoCalc) : $saldoCalc;

        return view(
            'admin.pagos.create',
            compact('prestamo', 'pagos', 'abonosPorCuota') + [
                'saldoActual'  => $saldoActual,
                'saldo_actual' => $saldoActual,
            ]
        );
    }

    public function cargar_datos($id)
    {
        $datosCliente = Cliente::findOrFail($id);
        $clientes = Cliente::all();
        return view('admin.pagos.cargar_datos', compact('datosCliente', 'clientes'));
    }

    public function store($id)
    {
        $pago = Pago::findOrFail($id);

        // Guard: prestamista solo puede confirmar pagos de sus préstamos
        $prestamoGuard = Prestamo::findOrFail($pago->prestamo_id);
        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamoGuard->idusuario !== auth()->id()) {
            return redirect()->back()
                ->with('mensaje', 'No tienes permiso para confirmar este pago.')
                ->with('icono', 'error');
        }

        if ($pago->estado === 'Confirmado') {
            return redirect()->back()
                ->with('mensaje', 'El pago ya estaba confirmado.')
                ->with('icono', 'info');
        }

        DB::transaction(function () use ($pago) {
            // 1) Confirmar pago (guardar fecha en UTC)
            $pago->estado = 'Confirmado';
            $pago->fecha_cancelado = Carbon::now('UTC'); // DATETIME UTC
            $pago->save();

            // 2) NETO = cuota - abonos de esa cuota
            $nroCuota = $this->resolverNroCuota($pago);

            $abonosCuota = (float) Abono::where('prestamo_id', $pago->prestamo_id)
                ->where('nro_cuota', $nroCuota)
                ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

            $montoCuota = (float) $pago->monto_pagado;
            $neto = max(0.0, $montoCuota - $abonosCuota);

            // 3) Acumular SOLO el NETO en SALDO DE ASESORES (bucket de ruta)
            if ($neto > 0) {
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                if ($empresa) {
                    $actual = max(0, (int) ($empresa->capital_asignado_total ?? 0));
                    $empresa->capital_asignado_total = $actual + (int) round($neto, 0);
                    $empresa->save();
                }
            }

            // 3.b) Acreditar NETO al PRESTAMISTA (disponible para volver a prestar)
            $prestamo = Prestamo::find($pago->prestamo_id);
            $ownerId  = $prestamo->idusuario ?? auth()->id();

            if ($neto > 0 && $ownerId) {
                $cp = CapitalPrestamista::query()
                    ->lockForUpdate()
                    ->firstOrCreate(['user_id' => $ownerId], [
                        'monto_asignado'   => 0,
                        'monto_disponible' => 0,
                    ]);

                $cp->monto_disponible = (int) $cp->monto_disponible + (int) round($neto, 0);
                $cp->save();

                if (class_exists(MovimientoCapitalPrestamista::class)) {
                    MovimientoCapitalPrestamista::create([
                        'user_id'     => $ownerId,
                        'monto'       => (int) round($neto, 0),
                        'descripcion' => 'Cobro de cuota (neto) préstamo #' . $pago->prestamo_id,
                    ]);
                }
            }

            // 4) Log de movimiento general (solo si neto > 0) — usado por Auditorías
            if ($neto > 0) {
                RegistroCapital::create([
                    'monto'       => (int) round($neto, 0),
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Ingreso recibido por asesor (cuota neta) → Saldo asesores (préstamo #' . $pago->prestamo_id . ', cuota ' . $nroCuota . ')',
                ]);
            }

            // 5) ¿Fue la última cuota?
            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes === 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Cancelado';
                    $prestamo->save();
                }
            }
        });

        return redirect()->back()
            ->with('mensaje', 'Pago registrado (neto sumado a saldo de asesores y al capital del prestamista).')
            ->with('icono', 'success');
    }

    public function comprobantedepago($id)
    {
        $pago = Pago::findOrFail($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();

        // Mostrar fecha literal en zona local (Bogotá), aunque almacenamos UTC
        $dt = $pago->fecha_cancelado
            ? Carbon::parse($pago->fecha_cancelado)->timezone('America/Bogota')
            : Carbon::now('America/Bogota');

        $dia = $dt->format('j');
        $mes = $dt->format('F');
        $ano = $dt->format('Y');

        $meses = [
            'January' => 'enero',
            'February' => 'febrero',
            'March' => 'marzo',
            'April' => 'abril',
            'May' => 'mayo',
            'June' => 'junio',
            'July' => 'julio',
            'August' => 'agosto',
            'September' => 'septiembre',
            'October' => 'octubre',
            'November' => 'noviembre',
            'December' => 'diciembre',
        ];

        $mes_espanol = $meses[$mes] ?? $mes;
        $fecha_literal = $dia . " de " . $mes_espanol . " de " . $ano;

        $configuracion = Configuracion::latest()->first();
        $pdf = Pdf::loadView('admin.pagos.comprobantedepago', compact('pago', 'configuracion', 'fecha_literal', 'prestamo', 'cliente'));
        return $pdf->stream();
    }

    public function show($id)
    {
        $pago = Pago::findOrFail($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();

        // Guard: prestamista solo puede ver sus pagos
        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.pagos.index')
                ->with('mensaje', 'No tienes permiso para ver este pago.')
                ->with('icono', 'error');
        }

        return view('admin.pagos.show', compact('pago', 'prestamo', 'cliente'));
    }

    public function edit(Pago $pago) { /* ... */ }

    public function update(Request $request, Pago $pago) { /* ... */ }

    public function destroy($id)
    {
        $pago = Pago::findOrFail($id);

        // Guard: prestamista solo puede revertir pagos de sus préstamos
        $prestamoGuard = Prestamo::findOrFail($pago->prestamo_id);
        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamoGuard->idusuario !== auth()->id()) {
            return redirect()->route('admin.pagos.index')
                ->with('mensaje', 'No tienes permiso para eliminar este pago.')
                ->with('icono', 'error');
        }

        DB::transaction(function () use ($pago) {
            if ($pago->estado === 'Confirmado') {
                $nroCuota = $this->resolverNroCuota($pago);

                $abonosCuota = (float) Abono::where('prestamo_id', $pago->prestamo_id)
                    ->where('nro_cuota', $nroCuota)
                    ->sum(DB::raw('COALESCE(monto, monto_abonado, 0)'));

                $montoCuota = (float) $pago->monto_pagado;
                $neto = max(0.0, $montoCuota - $abonosCuota);

                if ($neto > 0) {
                    // Ajuste saldo asesores (clamp)
                    $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                    if ($empresa) {
                        $actual = max(0, (int) ($empresa->capital_asignado_total ?? 0));
                        $empresa->capital_asignado_total = max(0, $actual - (int) round($neto, 0));
                        $empresa->save();
                    }

                    // Reversa en capital del prestamista (clamp)
                    $prestamo = Prestamo::find($pago->prestamo_id);
                    $ownerId  = $prestamo->idusuario ?? auth()->id();

                    if ($ownerId) {
                        $cp = CapitalPrestamista::query()
                            ->lockForUpdate()
                            ->firstOrCreate(['user_id' => $ownerId], [
                                'monto_asignado'   => 0,
                                'monto_disponible' => 0,
                            ]);

                        $cp->monto_disponible = max(0, (int) $cp->monto_disponible - (int) round($neto, 0));
                        $cp->save();

                        if (class_exists(MovimientoCapitalPrestamista::class)) {
                            MovimientoCapitalPrestamista::create([
                                'user_id'     => $ownerId,
                                'monto'       => -(int) round($neto, 0),
                                'descripcion' => 'Reverso de cobro (neto) préstamo #' . $pago->prestamo_id,
                            ]);
                        }
                    }

                    RegistroCapital::create([
                        'monto'       => -(int) round($neto, 0),
                        'user_id'     => auth()->id(),
                        'tipo_accion' => 'Reverso de cuota (neto) desde saldo asesores (préstamo #' . $pago->prestamo_id . ', cuota ' . $nroCuota . ')',
                    ]);
                }
            }

            $pago->fecha_cancelado = null;
            $pago->estado = 'Pendiente';
            $pago->save();

            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes > 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Pendiente';
                    $prestamo->save();
                }
            }
        });

        return redirect()->route('admin.pagos.index')
            ->with('mensaje', 'Se eliminó el pago del cliente correctamente.')
            ->with('icono', 'success');
    }

    private function resolverNroCuota(Pago $pago): int
    {
        // Orden estable por fecha_pago + id para numerar cuotas
        return (int) Pago::where('prestamo_id', $pago->prestamo_id)
            ->where(function ($q) use ($pago) {
                $q->where('fecha_pago', '<', $pago->fecha_pago)
                  ->orWhere(function ($qq) use ($pago) {
                      $qq->where('fecha_pago', $pago->fecha_pago)
                         ->where('id', '<=', $pago->id);
                  });
            })
            ->count();
    }
}
