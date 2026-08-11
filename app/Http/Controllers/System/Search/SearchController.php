<?php

namespace App\Http\Controllers\System\Search;

use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use App\Models\Developer;
use App\Models\SystemNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Role;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->get('q', ''));
        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $results = [];

        // 1. Navigation Pages & System Configuration Features (Bilingual Search)
        $pages = [
            ['name' => __('messages.dashboard') . ' / Dashboard / Home', 'url' => route('v1.dashboard'), 'icon' => 'mdi-view-dashboard-outline', 'category' => 'System Pages'],
            ['name' => __('messages.user_management') . ' / User Management / Pengguna', 'url' => route('admin.users.index'), 'icon' => 'mdi-account-group-outline', 'category' => 'System Pages'],
            ['name' => __('messages.roles_permissions') . ' / Roles & Permissions / Hak Akses Peran', 'url' => route('admin.permissions.index'), 'icon' => 'mdi-shield-key-outline', 'category' => 'System Pages'],
            ['name' => __('messages.support_tickets') . ' / Support Tickets / Tiket Bantuan', 'url' => route('admin.tickets.index'), 'icon' => 'mdi-lifebuoy', 'category' => 'System Pages'],
            ['name' => __('messages.cloud_directory') . ' / Storage', 'url' => route('admin.directory.index'), 'icon' => 'mdi-folder-network-outline', 'category' => 'System Pages'],
            ['name' => 'Horizon / Queue Monitoring', 'url' => url('/horizon'), 'icon' => 'mdi-cpu', 'category' => 'System Pages'],
            ['name' => 'Pulse / Application Monitoring', 'url' => url('/pulse'), 'icon' => 'mdi-chart-line', 'category' => 'System Pages'],
            ['name' => __('messages.notification_blast') . ' / Notification Blast / Pesan Blast', 'url' => route('admin.notifications.index'), 'icon' => 'mdi-bell-outline', 'category' => 'System Pages'],
            ['name' => __('messages.audit_trail') . ' / Audit Trail / Activity Logs', 'url' => route('admin.audit-logs.index'), 'icon' => 'mdi-history', 'category' => 'System Pages'],
            ['name' => __('messages.my_account') . ' / My Account Profile / Profil Saya', 'url' => route('v1.profile.index'), 'icon' => 'mdi-account-circle-outline', 'category' => 'System Pages'],
            ['name' => __('messages.api_docs') . ' / API Documentation / Swagger API', 'url' => url('/api/documentation'), 'icon' => 'mdi-file-code-outline', 'category' => 'System Pages'],
        ];

        foreach ($pages as $page) {
            if (stripos($page['name'], $query) !== false) {
                $results[] = [
                    'type'     => 'page',
                    'category' => 'Navigation Pages',
                    'title'    => explode(' / ', $page['name'])[0],
                    'subtitle' => 'System Module Page',
                    'url'      => $page['url'],
                    'icon'     => $page['icon']
                ];
            }
        }

        // 2. Search Support Tickets (Code, Subject, Description, Category, Reporter)
        try {
            $tickets = Ticket::where('ticket_code', 'like', "%{$query}%")
                ->orWhere('subject', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($tickets as $ticket) {
                $url = (auth()->user()->isDeveloper() || auth()->user()->isAdmin()) 
                    ? route('admin.tickets.show', $ticket->id) 
                    : route('v1.tickets.show', $ticket->ticket_code);

                $results[] = [
                    'type'     => 'ticket',
                    'category' => 'Support Tickets',
                    'title'    => "#{$ticket->ticket_code} - {$ticket->subject}",
                    'subtitle' => "Category: " . ucfirst(str_replace('_', ' ', $ticket->category)) . " | Status: " . ucfirst($ticket->status),
                    'url'      => $url,
                    'icon'     => 'mdi-ticket-confirmation-outline'
                ];
            }
        } catch (\Throwable $e) {}

        // 3. Search Users (Name, Email, Phone, Designation)
        try {
            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->orWhere('designation', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($users as $user) {
                $results[] = [
                    'type'     => 'user',
                    'category' => 'Users & Staff',
                    'title'    => $user->name,
                    'subtitle' => $user->email . ($user->designation ? " ({$user->designation})" : ''),
                    'url'      => route('admin.users.edit', $user->id),
                    'avatar'   => $user->avatar_url,
                    'icon'     => 'mdi-account-outline'
                ];
            }
        } catch (\Throwable $e) {}

        // 4. Search Assigned Developers
        try {
            $devs = Developer::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->limit(3)
                ->get();

            foreach ($devs as $dev) {
                $results[] = [
                    'type'     => 'developer',
                    'category' => 'Support Developers',
                    'title'    => "👨‍💻 " . $dev->name,
                    'subtitle' => "Email: {$dev->email} | Active",
                    'url'      => route('admin.tickets.developers.index'),
                    'icon'     => 'mdi-code-braces'
                ];
            }
        } catch (\Throwable $e) {}

        // 5. Search Roles & Permissions
        try {
            if (class_exists(Role::class)) {
                $roles = Role::where('name', 'like', "%{$query}%")
                    ->limit(3)
                    ->get();

                foreach ($roles as $role) {
                    $results[] = [
                        'type'     => 'role',
                        'category' => 'Roles & Access',
                        'title'    => ucfirst($role->name) . ' Role',
                        'subtitle' => 'System Permission Role',
                        'url'      => route('admin.permissions.edit', $role->id),
                        'icon'     => 'mdi-shield-account-outline'
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // 6. Search System Audit Logs (Event, Description, Module, IP)
        try {
            if (class_exists(Activity::class)) {
                $logs = Activity::with('causer')->where('event', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('log_name', 'like', "%{$query}%")
                    ->limit(4)
                    ->get();

                foreach ($logs as $log) {
                    $results[] = [
                        'type'     => 'audit',
                        'category' => 'Audit Trail Logs',
                        'title'    => "Log: {$log->event} ({$log->log_name})",
                        'subtitle' => $log->description,
                        'url'      => route('admin.audit-logs.index'),
                        'icon'     => 'mdi-history'
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // 7. Search System Notifications
        try {
            $notifs = SystemNotification::where('title', 'like', "%{$query}%")
                ->orWhere('message', 'like', "%{$query}%")
                ->where('user_id', auth()->id())
                ->limit(3)
                ->get();

            foreach ($notifs as $n) {
                $results[] = [
                    'type'     => 'notification',
                    'category' => 'Notifications',
                    'title'    => $n->title,
                    'subtitle' => $n->message,
                    'url'      => $n->url ?: route('v1.profile.index') . '#tab-notifications',
                    'icon'     => $n->icon ?: 'mdi-bell-outline'
                ];
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'results' => array_values($results)
        ]);
    }
}
