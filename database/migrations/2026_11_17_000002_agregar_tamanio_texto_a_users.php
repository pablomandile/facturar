<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tamaño de letra vive también en la cuenta, no solo en una cookie: así
 * sigue a la persona a otro dispositivo. `string` y no ENUM de MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('tamanio_texto', 20)->default('normal')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('tamanio_texto');
        });
    }
};
