<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ajoute un identifiant public UUID v7 aux comptes existants et futurs
 * (P003-A §6). L'identifiant interne bigint de `users` ne doit jamais être
 * utilisé dans une future URL publique.
 *
 * Additive et non destructive : la table `users` n'est ni renommée ni
 * déplacée pendant P003-A (P003-A §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
        });

        User::query()
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $user->newQuery()
                        ->whereKey($user->getKey())
                        ->update(['public_id' => (string) Str::uuid7()]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('public_id');
        });
    }
};
