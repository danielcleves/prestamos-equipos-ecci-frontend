<?php

namespace Tests\Support;

use Illuminate\Support\ViewErrorBag;

/**
 * Normaliza los errores de la sesión para poder afirmar sobre los mensajes.
 *
 * Según el driver de sesión y el tipo de flash, `session('errors')` puede venir
 * como ViewErrorBag o como el arreglo serializado con la clave `default`.
 * Este helper devuelve siempre una lista plana de mensajes.
 */
trait ErroresDeSesion
{
    /** @return list<string> */
    private function erroresDeSesion(): array
    {
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag) {
            return $errors->all();
        }

        if (is_array($errors)) {
            return collect($errors['default']['messages'] ?? [])->flatten()->all();
        }

        return [];
    }
}
