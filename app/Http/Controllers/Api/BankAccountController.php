<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;

class BankAccountController extends Controller
{
    public function index()
    {
        $banks = BankAccount::where('is_active', true)->get();
        return response()->json(['bank_accounts' => $banks]);
    }
}
