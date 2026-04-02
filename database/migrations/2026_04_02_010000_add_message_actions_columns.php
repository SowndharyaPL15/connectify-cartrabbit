<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false);
            $table->json('starred_by')->nullable();
            $table->json('favorited_by')->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->unsignedBigInteger('forwarded_from_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'starred_by', 'favorited_by', 'reply_to_id', 'forwarded_from_id']);
        });
    }
};
