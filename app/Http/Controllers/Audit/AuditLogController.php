<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Enums\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ExportAuditLogRequest;
use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\Audit\AuditLog;
use App\Services\Audit\AuditService;
use Dedoc\Scramble\Attributes\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditService $service) {}

    public function index(IndexAuditLogRequest $request)
    {
        $logs = $this->service->getAllPaginated(
            $this->filters($request),
            $this->perPage($request),
        );

        return AuditLogResource::collection($logs);
    }

    #[Response(200, description: 'Archivo CSV de auditoría', mediaType: 'text/csv', format: 'binary')]
    public function export(ExportAuditLogRequest $request): StreamedResponse
    {
        return $this->service->exportCsv($this->filters($request));
    }

    public function show(AuditLog $audit_log)
    {
        return new AuditLogResource($this->service->getAuditLog($audit_log));
    }

    /**
     * @return array{user_id: int|null, action: AuditAction|null, auditable_type: string|null, auditable_id: int|null, from: string|null, to: string|null}
     */
    private function filters(IndexAuditLogRequest $request): array
    {
        $action = $request->validated('action');

        return [
            'user_id' => $request->validated('user_id') === null ? null : (int) $request->validated('user_id'),
            'action' => $action === null ? null : AuditAction::from((string) $action),
            'auditable_type' => $request->validated('auditable_type') === null ? null : (string) $request->validated('auditable_type'),
            'auditable_id' => $request->validated('auditable_id') === null ? null : (int) $request->validated('auditable_id'),
            'from' => $request->validated('from') === null ? null : (string) $request->validated('from'),
            'to' => $request->validated('to') === null ? null : (string) $request->validated('to'),
        ];
    }
}
