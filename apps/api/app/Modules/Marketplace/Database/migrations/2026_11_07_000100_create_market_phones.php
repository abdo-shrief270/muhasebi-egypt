<?php

use App\Modules\Marketplace\Support\PhoneList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The platform's reference list of phones (not a shop's): what «سوق محاسبي» will match offers
        // against. Edited by the platform admins; starts from database/data/market/phones.json.
        Schema::create('market_phones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('brand', 40);
            $table->string('series', 40)->nullable();
            $table->string('model', 80);
            $table->string('slug', 140)->unique();
            $table->jsonb('aliases')->default('[]');
            $table->smallInteger('year')->nullable();
            $table->jsonb('storage_gb')->default('[]');
            $table->string('network', 4)->nullable();
            $table->string('status', 12)->default('published');
            $table->string('notes', 500)->nullable();
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
            $table->index(['brand', 'year']);
            $table->index('status');
        });

        PhoneList::import();
    }

    public function down(): void
    {
        Schema::dropIfExists('market_phones');
    }
};
