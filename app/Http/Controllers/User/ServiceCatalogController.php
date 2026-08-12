<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DeployedApplication;
use App\Models\ServiceHealthCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $catalog = $request->query('scope') === 'catalog';
        $departmentIds = $request->user()->departments()->pluck('departments.id');
        $query = DeployedApplication::query()->with(['department', 'node', 'owner']);

        if ($catalog) {
            $query->where('health_status', 'online')->whereNotNull('domain');
        } else {
            $query->whereIn('department_id', $departmentIds);
        }

        $applications = $query
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where('display_name', 'like', $term)->orWhere('repo_full_name', 'like', $term)->orWhere('domain', 'like', $term);
            }))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('display_name')
            ->paginate(12)
            ->withQueryString();

        $uptime = ServiceHealthCheck::query()
            ->whereIn('repo_full_name', $applications->pluck('repo_full_name'))
            ->where('checked_at', '>=', now()->subDays(30))
            ->selectRaw("repo_full_name, AVG(CASE WHEN status = 'online' THEN 100 ELSE 0 END) AS percentage")
            ->groupBy('repo_full_name')
            ->pluck('percentage', 'repo_full_name');

        return view('services.index', [
            'applications' => $applications,
            'uptime' => $uptime,
            'catalog' => $catalog,
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'stats' => [
                'total' => $applications->total(),
                'online' => $applications->getCollection()->where('health_status', 'online')->count(),
                'attention' => $applications->getCollection()->whereIn('health_status', ['degraded', 'offline'])->count(),
            ],
        ]);
    }
}
