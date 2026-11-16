<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);
        $company = $request->user()->companies()->where('companies.id', $data['company_id'])
            ->where('companies.is_active', true)->wherePivot('is_active', true)->first();
        abort_unless($company, 403);
        $request->session()->regenerate();
        $request->session()->put('company_id', $company->id);

        return redirect()->route('dashboard');
    }
}
