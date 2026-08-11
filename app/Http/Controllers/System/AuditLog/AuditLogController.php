<?php

namespace App\Http\Controllers\System\AuditLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = Activity::with('causer')->latest();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('user_name', function ($row) {
                    return '<span class="fw-semibold text-dark">' . e($row->causer?->name ?? 'System/Guest') . '</span>';
                })
                ->editColumn('event', function ($row) {
                    $event = (string) $row->event;
                    $badgeClass = match (true) {
                        str_contains($event, 'login')    => 'bg-success',
                        str_contains($event, 'logout')   => 'bg-secondary',
                        str_contains($event, 'create')   => 'bg-info',
                        str_contains($event, 'update')   => 'bg-primary',
                        str_contains($event, 'delete')   => 'bg-danger',
                        default                               => 'bg-dark',
                    };
                    return '<span class="badge ' . $badgeClass . ' fs-11">' . e($event ?: 'activity') . '</span>';
                })
                ->addColumn('action_description', function ($row) {
                    return html_entity_decode($row->description, ENT_QUOTES, 'UTF-8');
                })
                ->addColumn('module', function ($row) {
                    return '<span class="badge bg-light text-primary border fs-11 text-capitalize">' . e($row->log_name) . '</span>';
                })
                ->addColumn('ip_address', function ($row) {
                    return '<code class="fs-12">' . e($row->properties['ip_address'] ?? 'N/A') . '</code>';
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '-';
                })
                ->rawColumns(['user_name', 'event', 'action_description', 'module', 'ip_address'])
                ->make(true);
        }

        return view('admin.audit-logs.index');
    }
}
