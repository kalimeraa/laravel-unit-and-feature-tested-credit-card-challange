<?php

use App\Models\Loan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scheduled_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Loan::class)->constrained();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('outstanding_amount');
            $table->string('currency_code',5);
            $table->string('status',10);
            $table->dateTime('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scheduled_repayments');
    }
};
