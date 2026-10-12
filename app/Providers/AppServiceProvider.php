<?php

namespace App\Providers;

use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Uso en rutas: ->can('ver-grupo', 'grupo')
        Gate::define('ver-grupo', fn (Usuario $u, Grupo $grupo) => $u->puedeVerGrupo($grupo));
        Gate::define('usar-horario', fn (Usuario $u, Horario $horario) => $u->puedeUsarHorario($horario));
    }
}
