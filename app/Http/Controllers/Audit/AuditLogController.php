<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Enums\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\Audit\AuditLog;
use App\Services\Audit\AuditService;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditService $service) {}

    public function index(IndexAuditLogRequest $request)
    {
        $action = $request->validated('action');

        $logs = $this->service->getAllPaginated(
            [
                'user_id' => $request->validated('user_id') === null ? null : (int) $request->validated('user_id'),
                'action' => $action === null ? null : AuditAction::from((string) $action),
                'auditable_type' => $request->validated('auditable_type') === null ? null : (string) $request->validated('auditable_type'),
                'auditable_id' => $request->validated('auditable_id') === null ? null : (int) $request->validated('auditable_id'),
                'from' => $request->validated('from') === null ? null : (string) $request->validated('from'),
                'to' => $request->validated('to') === null ? null : (string) $request->validated('to'),
            ],
            $this->perPage($request),
        );

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $audit_log)
    {
        return new AuditLogResource($this->service->getAuditLog($audit_log));
    }
}
