<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_disabled')->default(false);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->json('content');
            $table->timestamps();
        });
        \App\Models\Setting::create(['id' => 1, 'content' => \App\Models\Setting::defaults()]);

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('alt');
            $table->string('credit')->nullable();
            $table->boolean('show_in_gallery')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('photo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('preparation')->nullable();
            $table->unsignedInteger('price');
            $table->boolean('available')->default(false);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('meal_photo', function (Blueprint $table) {
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->primary(['meal_id', 'photo_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->string('status')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('fulfilment');
            $table->text('address')->nullable();
            $table->string('delivery_area')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('delivery_fee');
            $table->unsignedBigInteger('total');
            $table->char('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
        });

        Schema::create('order_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('status')->index();
            $table->string('channel')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('provider_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->char('fingerprint', 64)->unique();
            $table->string('type');
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email');
            $table->date('date');
            $table->time('time');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('guests');
            $table->text('requests')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->index(['date', 'time', 'status']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('quote');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['testimonials', 'reservations', 'payment_events', 'payments', 'order_updates', 'order_items', 'orders', 'meal_photo', 'meals', 'categories', 'photos', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'is_admin', 'is_disabled']));
    }
};


