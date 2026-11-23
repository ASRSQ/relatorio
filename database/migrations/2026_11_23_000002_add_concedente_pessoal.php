<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('concedentes', function (Blueprint $table) {
            $table->dropUnique(['documento']);
            $table->foreignId('aluno_proprietario_id')->nullable()->unique()->constrained('estagio_alunos')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::table('concedentes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aluno_proprietario_id');
        });
    }
};
