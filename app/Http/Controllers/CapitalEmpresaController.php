<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use App\Models\CapitalPrestamista;
use App\Models\User;
use App\Models\Prestamo;

class CapitalEmpresaController extends Controller
{
    public function index()
    {
        $capital = EmpresaCapital::latest()->first();
        $caja = (int) ($capital->capital_disponible ?? 0);

        // Dinero circulando (definición final: capital activo en la calle)
        [$dineroCirculando, $prestamosActivos] = $this->calcularCirculando();

        // Capital asignado total = asignado a prestamistas + saldo asesores/ruta
        $capitalAsignadoTotal = $this->calcularCapitalAsignadoTotal($capital);

        // Total general = Caja + Circulante + Capital asignado total
        $totalGeneral = $caja + $dineroCirculando + $capitalAsignadoTotal;

        $usuarios = User::role(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])->get();

        return view('admin.capital.index', [
            'capital'                     => $capital,
            'usuarios'                    => $usuarios,
            'capitalDisponible'           => $caja,
            'dineroCirculando'            => $dineroCirculando,
            'totalGeneral'                => $totalGeneral,
            'prestamosActivos'            => $prestamosActivos,
            'capitalAsignadoTotal'        => $capitalAsignadoTotal,
            'capitalAsignadoPrestamistas' => $capitalAsignadoTotal, // alias por compatibilidad de vista
        ]);
    }

    // Endpoint AJAX para refrescar tarjetas
    public function resumenJson()
    {
        $capital = EmpresaCapital::latest()->first();
        $caja = (int) ($capital->capital_disponible ?? 0);

        [$dineroCirculando, $prestamosActivos] = $this->calcularCirculando();
        $capitalAsignadoTotal = $this->calcularCapitalAsignadoTotal($capital);

        $totalGeneral = $caja + $dineroCirculando + $capitalAsignadoTotal;

        return response()->json([
            'capitalDisponible'     => $caja,
            'dineroCirculando'      => $dineroCirculando,
            'totalGeneral'          => $totalGeneral,
            'prestamosActivos'      => $prestamosActivos,
            'capitalAsignadoTotal'  => $capitalAsignadoTotal,
        ]);
    }

    /**
     * Dinero circulando (definitivo):
     *   suma de p.monto_prestado de todos los préstamos con estado = 'Pendiente'
     *   + conteo de préstamos activos (para mostrar en tarjeta).
     */
    private function calcularCirculando(): array
    {
        $row = Prestamo::where('estado', 'Pendiente')
            ->selectRaw('COALESCE(SUM(monto_prestado),0) AS capital_en_circulacion,
                         COUNT(*) AS prestamos_activos')
            ->first();

        $dineroCirculando = (int) ($row->capital_en_circulacion ?? 0);
        $prestamosActivos = (int) ($row->prestamos_activos ?? 0);

        return [$dineroCirculando, $prestamosActivos];
    }

    /** Suma (asignado a prestamistas) + (saldo asesores/ruta no pasado a caja) — clamp a 0. */
    private function calcularCapitalAsignadoTotal(?EmpresaCapital $empresa): int
    {
        $asignadoPrestamistas = max(0, (int) CapitalPrestamista::sum('monto_asignado'));
        $saldoAsesoresRuta    = max(0, (int) ($empresa->capital_asignado_total ?? 0));
        return $asignadoPrestamistas + $saldoAsesoresRuta;
    }

    // ================== CRUD de capital ==================

    // Guardar capital total (inicializa caja EXACTA)
    public function store(Request $request)
    {
        $request->merge(['capital_total' => str_replace(['.', ','], '', $request->capital_total)]);
        $request->validate(['capital_total' => 'required|numeric|min:1']);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::create([
                'capital_total'          => (int) $request->capital_total,
                'capital_disponible'     => (int) $request->capital_total,
                'capital_anterior'       => (int) $request->capital_total,
                'capital_asignado_total' => 0,
            ]);

            RegistroCapital::create([
                'monto'       => (int) $request->capital_total,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital total inicial registrado',
            ]);

            DB::commit();
            return back()->with('success', 'Capital registrado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar el capital: ' . $e->getMessage());
        }
    }

    // Agregar capital adicional (suma a caja)
    public function agregar(Request $request)
    {
        $request->merge(['monto' => str_replace(['.', ','], '', $request->monto)]);
        $request->validate(['monto' => 'required|numeric|min:1']);

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

            $capital->capital_anterior    = (int) $capital->capital_disponible;
            $capital->capital_total      += (int) $request->monto;
            $capital->capital_disponible += (int) $request->monto;
            $capital->save();

            RegistroCapital::create([
                'monto'       => (int) $request->monto,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital adicional agregado',
            ]);

            DB::commit();
            return back()->with('success', 'Capital adicional agregado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al agregar capital adicional.');
        }
    }

    // Asignar capital a prestamistas (resta de caja y lo deja “afuera” con el asesor)
    public function asignar(Request $request)
    {
        $montoCrudo = $request->montos[$request->asignar_id] ?? 0;
        $monto = (int) str_replace(['.', ','], '', $montoCrudo);

        // Confirmación opcional (si la vista envía "confirmar")
        $confirmar = filter_var($request->input('confirmar', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (!$monto || $monto <= 0) {
            return back()->with('error', 'Debe ingresar un monto válido para asignar.');
        }

        if ($confirmar === false) {
            return back()->with('info', '🛑 Operación cancelada por el usuario.');
        }

        DB::beginTransaction();
        try {
            $capital = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

            if ($monto > (int) ($capital->capital_disponible ?? 0)) {
                DB::rollBack();
                return back()->with('error', 'El monto excede el capital disponible.');
            }

            $capitalPrestamista = CapitalPrestamista::firstOrCreate([
                'user_id' => $request->asignar_id,
            ]);

            $capitalPrestamista->monto_asignado   = max(0, (int) $capitalPrestamista->monto_asignado) + $monto;
            $capitalPrestamista->monto_disponible = max(0, (int) $capitalPrestamista->monto_disponible) + $monto;
            $capitalPrestamista->save();

            $capital->capital_anterior    = (int) $capital->capital_disponible;
            $capital->capital_disponible  = (int) $capital->capital_disponible - $monto;
            $capital->save();

            $prestamista = User::find($request->asignar_id);
            RegistroCapital::create([
                'monto'       => $monto,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Capital asignado a prestamista: ' . ($prestamista->name ?? ''),
            ]);

            DB::commit();
            return back()->with('success', '✅ Capital asignado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al asignar capital.');
        }
    }

    /**
     * Pasar un MONTO específico del saldo de asesores (ruta) a Caja.
     * Solo afecta empresa.capital_asignado_total.
     */
    public function pasarACaja(Request $request)
    {
        $montoCrudo = $request->input('monto');
        $monto = (int) str_replace(['.', ','], '', (string) $montoCrudo);

        $request->merge(['monto' => $monto]);
        $request->validate(['monto' => ['required','numeric','min:1']]);

        return DB::transaction(function () use ($monto) {
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();

            if (!$empresa) {
                return back()->with('error', 'No hay registro de capital de empresa.');
            }

            $saldoAbonos = max(0, (int) ($empresa->capital_asignado_total ?? 0));
            $m = min($monto, $saldoAbonos);

            if ($m <= 0) {
                return back()->with('info', 'No hay saldo para transferir.');
            }

            $empresa->capital_anterior       = (int) $empresa->capital_disponible;
            $empresa->capital_asignado_total = max(0, $saldoAbonos - $m);
            $empresa->capital_disponible     = (int) $empresa->capital_disponible + $m;
            $empresa->save();

            RegistroCapital::create([
                'monto'       => (int) $m,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Traslado de abonos a Caja',
            ]);

            return back()->with('success', 'Transferencia realizada a Caja.');
        });
    }

    /**
     * Pasar TODO lo que muestra “Capital asignado total” a Caja:
     *  - Saldo de asesores (empresa.capital_asignado_total)
     *  - Asignaciones a prestamistas (capital_prestamistas.monto_asignado)
     */
    public function pasarAbonosACaja(Request $request)
    {
        return DB::transaction(function () {
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

            $saldoAsesores    = max(0, (int) ($empresa->capital_asignado_total ?? 0));
            $asignadoFuera    = max(0, (int) CapitalPrestamista::sum('monto_asignado'));
            $totalATransferir = $saldoAsesores + $asignadoFuera;

            if ($totalATransferir <= 0) {
                return back()->with('info', 'No hay saldo para transferir.');
            }

            // Caja += todo; saldo asesores → 0; asignaciones → 0
            $empresa->capital_anterior       = (int) $empresa->capital_disponible;
            $empresa->capital_disponible     = (int) $empresa->capital_disponible + $totalATransferir;
            $empresa->capital_asignado_total = 0;
            $empresa->save();

            if ($asignadoFuera > 0) {
                CapitalPrestamista::query()->update([
                    'monto_asignado'   => 0,
                    'monto_disponible' => 0,
                ]);
            }

            RegistroCapital::create([
                'monto'       => (int) $totalATransferir,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Traslado de Capital asignado total a Caja (abonos + asignaciones)',
            ]);

            return back()->with('success', 'Se transfirió a Caja el Capital asignado total.');
        });
    }

    /**
     * Devolver SOLO las asignaciones de CapitalPrestamista a Caja.
     */
    public function pasarAsignadoACaja(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $totalAsignado = max(0, (int) CapitalPrestamista::sum('monto_asignado'));
            if ($totalAsignado <= 0) {
                return back()->with('warning', 'No hay capital asignado para devolver.');
            }

            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->firstOrFail();

            $empresa->capital_anterior   = (int) $empresa->capital_disponible;
            $empresa->capital_disponible = (int) $empresa->capital_disponible + $totalAsignado;
            $empresa->save();

            CapitalPrestamista::query()->update([
                'monto_asignado'   => 0,
                'monto_disponible' => 0,
            ]);

            RegistroCapital::create([
                'monto'       => (int) $totalAsignado,
                'user_id'     => $request->user()->id ?? auth()->id(),
                'tipo_accion' => 'Capital devuelto desde asignaciones',
            ]);

            return back()->with('success', "Se devolvieron $totalAsignado a Caja disponible y se limpiaron las asignaciones.");
        });
    }
}
