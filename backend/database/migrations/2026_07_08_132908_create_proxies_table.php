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
        Schema::create('proxies', function (Blueprint $table) {
           $table->id();
            $table->string('ip');
            $table->integer('port');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->enum('type', ['http', 'https', 'socks4', 'socks5'])->default('http');
            $table->enum('status', ['active', 'dead', 'unchecked'])->default('unchecked');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['ip', 'port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxies');
    }
};
