
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // User Information
            $table->string('name', 150);
            $table->string('username', 50)->unique();
            $table->string('password');

            // Role and Account Status
            $table->enum('role', [
                'superadmin',
                'admin',
                'evaluator',
                'user'
            ])->default('user');

            $table->enum('status', [
                'active',
                'inactive'
            ])->default('active');

            // Login Tracking
            $table->timestamp('last_login_at')->nullable();

            // Authentication
            $table->rememberToken();

            // Created and Updated Dates
            $table->timestamps();
        });

        // Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
