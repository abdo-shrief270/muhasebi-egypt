<?php

use App\Modules\Payroll\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->prefix('payroll')->controller(PayrollController::class)->group(function (): void {
    Route::pattern('payslip', '[0-9a-fA-F-]{36}');
    Route::pattern('adjustment', '[0-9a-fA-F-]{36}');
    // Every employee's own pay.
    Route::get('mine', 'mine');

    Route::middleware('can:payroll.manage')->group(function (): void {
        Route::get('employees', 'employees');
        Route::put('employees/{employee}', 'saveEmployee')->whereUuid('employee');
        Route::get('sheet', 'sheet');
        Route::get('commissions', 'commissions');
        Route::post('close', 'close');
        Route::post('payslips/{payslip}/pay', 'pay')->middleware('branch');
        Route::get('adjustments', 'adjustments');
        Route::post('adjustments', 'addAdjustment')->middleware('branch');
        Route::delete('adjustments/{adjustment}', 'removeAdjustment');
    });
});
