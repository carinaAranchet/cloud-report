<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
        {
            Schema::create('users', function (Blueprint $table) {
                $table->id();

                // OIDC
                $table->string('sub')->nullable()->index(); // subject del IdP
                $table->string('name')->nullable();

                // Si tu IdP siempre envía email, dejalo unique; si no, permití null
                $table->string('email')->nullable()->unique();

                // Para OIDC no usás password local
                $table->string('password')->nullable();

                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
