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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('problem');
            $table->text('solution');
            $table->string('area');
            $table->integer('time_saved_per_year')->nullable();
            $table->integer('gross_profit_per_year')->nullable();
            $table->enum('effort', ['low', 'medium', 'high']);
            $table->text('monetary_explanation');
            $table->string('link')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->integer('manual_override_prio')->default(0);
            $table->integer('final_prio');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
