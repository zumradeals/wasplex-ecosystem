<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une création de compte utilisateur par le personnel
 * (instruction explicite du fondateur, 2026-07-31 ;
 * `identity.manage_users`). Mêmes règles de base que l'inscription en
 * libre-service — aucune règle inventée pour ce chemin admin.
 */
class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
