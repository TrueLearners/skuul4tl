<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('moodle_base_url')->nullable();
            $table->text('moodle_token')->nullable();
            $table->boolean('moodle_enabled')->default(false);
            $table->timestamp('moodle_last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_integrations');
    }
};
