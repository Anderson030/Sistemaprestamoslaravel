<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema; // 👈 para inspeccionar columnas

class Cliente extends Model
{
    use HasFactory;

    // Permitir asignación masiva de estos campos
    protected $fillable = [
        'nro_documento',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'categoria', // antes era el género
        'email',
        'celular',
        'ref_celular',
        'direccion',
        'nombre_referencia1',
        'telefono_referencia1',
        'nombre_referencia2',
        'telefono_referencia2',
        'idusuario', // asegúrate de incluir esto si lo usas en los formularios
    ];

    /**
     * Relación: Un cliente tiene muchos préstamos
     */
    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }

    /**
     * Relación: Un cliente pertenece a un usuario (prestamista)
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }

    /**
     * Accessor: nombre listo para mostrar en selects/tablas
     * Usa la mejor combinación disponible según tus columnas actuales.
     */
    public function getDisplayNameAttribute(): string
    {
        $nombre = $this->nombre
            ?? $this->razon_social
            ?? trim(($this->nombres ?? '') . ' ' . ($this->apellidos ?? ''))
            ?? $this->name
            ?? 'Cliente #' . $this->id;

        $nombre = trim($nombre);
        return $nombre !== '' ? $nombre : ('Cliente #' . $this->id);
    }

    /**
     * Scope: ordena por la mejor columna disponible (evita errores si no existe 'nombre')
     */
    public function scopeDisplayOrder($query)
    {
        // obtener la tabla del modelo asociado al query
        $table = $query->getModel()->getTable();
        $cols  = Schema::getColumnListing($table);

        if (in_array('nombre', $cols)) {
            return $query->orderBy('nombre', 'asc');
        }
        if (in_array('razon_social', $cols)) {
            return $query->orderBy('razon_social', 'asc');
        }
        if (in_array('nombres', $cols)) {
            // si existen nombres/apellidos, ordena primero por nombres luego apellidos
            return $query->orderBy('nombres', 'asc')
                         ->when(in_array('apellidos', $cols), fn($q) => $q->orderBy('apellidos', 'asc'));
        }

        // fallback seguro
        return $query->orderBy('id', 'desc');
    }
}
