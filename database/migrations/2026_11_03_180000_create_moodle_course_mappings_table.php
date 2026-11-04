<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moodle_course_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('moodle_course_id');
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'moodle_course_id']);
            $table->unique(['school_id', 'course_offering_id']);
        });

        Schema::create('moodle_managed_roster_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moodle_course_mapping_id')->constrained('moodle_course_mappings')->cascadeOnDelete();
            $table->foreignId('student_record_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['moodle_course_mapping_id', 'student_record_id'], 'moodle_roster_mapping_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moodle_managed_roster_records');
        Schema::dropIfExists('moodle_course_mappings');
    }
};
