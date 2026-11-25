<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->index(['active', 'position']);
        });
        Schema::table('meals', function (Blueprint $table) {
            $table->boolean('popular')->default(false);
            $table->boolean('price_confirmed')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->index(['category_id', 'position']);
        });
        Schema::table('meal_photo', fn (Blueprint $table) => $table->unsignedSmallInteger('position')->default(0));
        Schema::table('photos', fn (Blueprint $table) => $table->boolean('illustrative')->default(false));
        Schema::create('meal_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('size', 50)->nullable();
            $table->string('protein', 50)->nullable();
            $table->unsignedInteger('price');
            $table->boolean('available')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('meal_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('price');
            $table->boolean('available')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->json('options')->nullable());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('options'));
        Schema::dropIfExists('meal_addons');
        Schema::dropIfExists('meal_variations');
        Schema::table('photos', fn (Blueprint $table) => $table->dropColumn('illustrative'));
        Schema::table('meal_photo', fn (Blueprint $table) => $table->dropColumn('position'));
        Schema::table('meals', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'position']);
            $table->dropColumn(['popular', 'price_confirmed', 'position']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['active', 'position']);
            $table->dropColumn(['active', 'position']);
        });
    }
};
