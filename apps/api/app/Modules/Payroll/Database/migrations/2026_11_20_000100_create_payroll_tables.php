<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each employee is paid: monthly or weekly (Saturday to Friday), a base salary per period.
        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('pay_cycle', 10)->default('monthly')->comment('monthly | weekly');
            $table->unsignedBigInteger('base_salary')->default(0)->comment('piasters per period');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        // Advances (paid out now), bonuses and deductions; each lands on the next payslip.
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('kind', 12)->comment('advance | bonus | deduction');
            $table->unsignedBigInteger('amount');
            $table->string('note', 200)->nullable();
            $table->date('on_date');
            $table->string('paid_from', 10)->nullable()->comment('an advance: drawer | safe | bank');
            $table->uuid('branch_id')->nullable();
            $table->uuid('payslip_id')->nullable();
            $table->string('created_by_name', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'on_date']);
        });

        // A closed period's pay for one employee, figures fixed; paid once.
        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('user_name', 120);
            $table->string('cycle', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('base_salary');
            $table->unsignedInteger('tickets')->default(0);
            $table->unsignedBigInteger('commission')->default(0);
            $table->unsignedBigInteger('clawback')->default(0);
            $table->unsignedBigInteger('bonuses')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->unsignedBigInteger('advances')->default(0);
            $table->bigInteger('net');
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_from', 10)->nullable()->comment('drawer | safe | bank');
            $table->string('paid_by_name', 120)->nullable();
            $table->string('closed_by_name', 120)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'cycle', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_employees');
    }
};
