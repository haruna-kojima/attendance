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
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_id')->constrained()->onDelete('cascade');
            $table->timestamp('requested_clock_in_at')->nullable();
            $table->timestamp('requested_clock_out_at')->nullable();
            // 複数の休憩修正データをJSON形式で保存（例: [{break_id: 1, in: '12:00', out: '13:00'}, {in: '15:00', out: '15:15'}]）
            // これにより「追加で1つ分の入力フィールド」の新規追加分もまとめて保存可能
            $table->json('requested_breaks')->nullable();
            $table->text('requested_remarks')->nullable();
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_requests');
    }
};
