<?php

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (!function_exists('audit_log')) {
    /**
     * Catat log aktivitas sistem ke Audit Trail.
     * Cukup panggil: audit_log('Melakukan update profil');
     *
     * @param string $description Deskripsi aktivitas
     * @param string|null $event Kategori event (opsional, auto-detect jika null)
     * @param string $module Nama modul (default: 'system')
     * @param array|null $properties Metadata tambahan (opsional)
     * @param User|null $user Pelaku (default: Auth::user())
     * @return \Spatie\Activitylog\Models\Activity
     */
    function audit_log(string $description, ?string $event = null, string $module = 'system', ?array $properties = null, ?User $user = null): \Spatie\Activitylog\Models\Activity
    {
        $logger = activity($module)->event($event ?? 'activity');

        if ($user) {
            $logger->causedBy($user);
        }

        return $logger->withProperties($properties ?? [])->log($description);
    }
}

if (!function_exists('send_notification')) {
    /**
     * Kirim notifikasi lonceng ke pengguna.
     * Cukup panggil: send_notification('Judul', 'Isi Pesan', 'https://link-opsional.com', $user);
     * Jika $user tidak diisi, otomatis terkirim ke user yang sedang login saat ini.
     *
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string|null $url Link tujuan saat diklik (opsional)
     * @param User|int|null $user Target pengguna / user_id (opsional, default: Auth::user())
     * @param string $type Tipe notifikasi ('info', 'success', 'warning', 'danger')
     * @param string $icon Ikon MDI (default: 'mdi-bell-outline')
     * @return SystemNotification|null
     */
    function send_notification(string $title, string $message, ?string $url = null, User|int|null $user = null, string $type = 'info', string $icon = 'mdi-bell-outline'): ?SystemNotification
    {
        $targetUser = $user ?? Auth::user();
        if (!$targetUser) {
            return null;
        }

        return SystemNotification::send($targetUser, $title, $message, $type, $icon, $url);
    }
}
