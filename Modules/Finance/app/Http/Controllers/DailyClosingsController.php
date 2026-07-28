<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Files\Http\Resources\FileResource;
use Modules\Finance\Http\Resources\DailyClosingResource;
use Modules\Finance\Models\DailyClosing;
use Modules\Finance\Services\DailyClosingDocumentService;
use Modules\Finance\Services\DailyClosingService;

class DailyClosingsController extends Controller
{
    use ApiResponse;

    public function __construct(protected DailyClosingService $service)
    {
    }

    public function index(Request $request)
    {
        $closings = DailyClosing::orderByDesc('closing_date')->paginate((int) $request->integer('per_page', 30));

        return $this->ok(DailyClosingResource::collection($closings));
    }

    public function compute(Request $request)
    {
        $validated = $request->validate(['date' => ['required', 'date']]);

        $figures = $this->service->compute($validated['date']);
        $closing = $figures['closing'];
        unset($figures['closing']);
        $figures['closing'] = $closing ? new DailyClosingResource($closing) : null;

        return $this->ok($figures);
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'counted_cash' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $closing = $this->service->close(
            $validated['date'],
            $validated['counted_cash'] ?? null,
            $validated['notes'] ?? null,
            $request->user()->id,
        );

        return $this->ok(new DailyClosingResource($closing), 'Day closed and entries locked.');
    }

    public function reopen(Request $request, DailyClosing $dailyClosing)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $closing = $this->service->reopen($dailyClosing, $validated['reason'], $request->user()->id);

        return $this->ok(new DailyClosingResource($closing), 'Day reopened.');
    }

    public function pdf(Request $request, DailyClosing $dailyClosing, DailyClosingDocumentService $documents)
    {
        $file = $documents->generatePdf($dailyClosing, $request->user()->id);

        return $this->created([
            'closing' => new DailyClosingResource($dailyClosing->refresh()->load('generatedFile')),
            'file' => new FileResource($file),
        ], 'Daily closing PDF generated.');
    }

    public function sendToAdmin(Request $request, DailyClosing $dailyClosing, DailyClosingDocumentService $documents)
    {
        if (! $dailyClosing->generated_file_id) {
            $documents->generatePdf($dailyClosing, $request->user()->id);
            $dailyClosing->refresh();
        }

        $closing = $this->service->sendToAdmin($dailyClosing, $request->user()->id);

        return $this->ok(new DailyClosingResource($closing->load('generatedFile')), 'Daily closing sent to admin.');
    }
}
