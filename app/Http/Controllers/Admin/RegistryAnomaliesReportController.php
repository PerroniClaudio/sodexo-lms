<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildRegistryAnomaliesReport;
use App\Http\Controllers\Controller;
use Spatie\LaravelPdf\Facades\Pdf;

class RegistryAnomaliesReportController extends Controller
{
    public function download(BuildRegistryAnomaliesReport $buildRegistryAnomaliesReport)
    {
        return Pdf::view('pdf.registry-anomalies-report', $buildRegistryAnomaliesReport($this->activeCompanyDivisionId()))
            ->driver('dompdf')
            ->landscape()
            ->download('anomalie-anagrafica-'.now()->format('Ymd').'.pdf');
    }

    private function activeCompanyDivisionId(): ?int
    {
        if (request()->user()?->hasRole('superadmin')) {
            return null;
        }

        $divisionId = request()->session()->get('active_company_division_id');

        return $divisionId === null ? null : (int) $divisionId;
    }
}
