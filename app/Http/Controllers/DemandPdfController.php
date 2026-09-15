<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Demand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DemandPdfController extends Controller
{
    /**
     * Gera e exporta o registro completo da demanda em PDF.
     */
    public function export(Request $request, string $id)
    {
        $demand = Demand::with([
            'entity.macroprocess',
            'processStatus',
            'client',
            'project',
            'requester',
            'assignee',
            'creator',
            'children.processStatus',
            'children.assignee',
            'comments.user',
            'fieldValues.customField',
        ])->findOrFail($id);

        // Busca o histórico de transições na auditoria (por quem passou)
        $transitionsHistory = ActivityLog::where('auditable_type', Demand::class)
            ->where('auditable_id', (string) $demand->id)
            ->where('event', 'transition')
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        $pdf = Pdf::loadView('pdf.demand-report', compact('demand', 'transitionsHistory'))
            ->setPaper('a4', 'portrait')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'defaultFont'          => 'sans-serif',
            ]);

        $shortId = strtoupper(substr($demand->id, 0, 8));
        $filename = "demanda-{$shortId}.pdf";

        if ($request->has('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
