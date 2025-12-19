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
        Schema::create('study_center_registrations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('full_name')->nullable();
            $table->text('subjects')->nullable(); // O'qimoqchi/o'qiyotgan fanlar
            $table->string('phone')->nullable();
            $table->boolean('is_subscribed')->default(false);
            $table->string('state')->default('start'); // start, subscription, full_name, subjects, phone, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_center_registrations');
    }
};
