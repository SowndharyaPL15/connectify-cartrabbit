<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('type', ['text', 'image'])->default('text');
            $table->enum('status', ['sent', 'delivered', 'read'])->default('sent');
            $table->boolean('is_edited')->default(false);
            $table->json('deleted_by')->nullable(); // Arrays of user IDs who deleted this for themselves
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
