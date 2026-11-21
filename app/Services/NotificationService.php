<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

class NotificationService
{
    /**
     * Rekam notifikasi baru ke database untuk target divisi tertentu (atau multiple divisi).
     *
     * @param string $divisionSource Divisi pembuat/pengirim (PPIC, Purchasing, Warehouse, QC, RD)
     * @param string $type Tipe aktivitas/event
     * @param string $title Judul notifikasi
     * @param string $message Pesan notifikasi
     * @param string $link Tautan tujuan
     * @param int|null $userId User ID pembuat
     * @param string|null $userName Nama user pembuat
     * @param bool $sendEmail Apakah mengirimkan email alert
     * @param string|array $targetDivision Divisi penerima ('Purchasing', 'Warehouse', 'QC', 'PPIC', 'RD', atau array ['Warehouse','PPIC'], default: 'ALL')
     */
    public static function record(
        string $divisionSource,
        string $type,
        string $title,
        string $message,
        string $link = '/',
        ?int $userId = null,
        ?string $userName = null,
        bool $sendEmail = true,
        string|array $targetDivision = 'ALL'
    ): int {
        if ($userName === null) {
            $userName = $_SESSION['user_name'] ?? 'Petugas Sistem';
        }
        if ($userId === null) {
            $userId = (int) ($_SESSION['user_id'] ?? 1);
        }

        // Normalize target divisions into an array
        $targets = is_array($targetDivision) ? $targetDivision : explode(',', (string) $targetDivision);
        $targets = array_values(array_filter(array_map('trim', $targets)));
        if (empty($targets)) {
            $targets = ['ALL'];
        }

        $firstId = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($targets as $target) {
            try {
                $id = Database::insert('division_notifications', [
                    'division_source' => $divisionSource,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'link' => $link,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'target_division' => $target,
                    'is_read' => 0,
                    'created_at' => $now,
                ]);
                if ($firstId === 0) {
                    $firstId = $id;
                }
            } catch (\Throwable $e) {
                error_log("Gagal merekam notifikasi divisi untuk target {$target}: " . $e->getMessage());
            }
        }

        // Kirim email notifikasi instan ke pihak terkait
        if ($sendEmail) {
            try {
                EmailNotificationService::sendDivisionActivityAlert(
                    $divisionSource,
                    $type,
                    $title,
                    $message,
                    $userName,
                    $link
                );
            } catch (\Throwable $e) {
                error_log("Gagal kirim email notifikasi aktivitas divisi: " . $e->getMessage());
            }
        }

        return $firstId;
    }

    /**
     * Ambil daftar notifikasi terbaru yang ditujukan untuk divisi tertentu.
     * Jika $division null, otomatis menggunakan divisi aktif dari session/AccessControl.
     */
    public static function getRecent(?string $division = null, int $limit = 15): array
    {
        if ($division === null) {
            $division = AccessControl::getCurrentRole();
        }
        $division = trim($division);

        try {
            return Database::select("
                SELECT * FROM division_notifications 
                WHERE LOWER(target_division) = LOWER(:div) OR UPPER(target_division) = 'ALL'
                ORDER BY id DESC 
                LIMIT {$limit}
            ", ['div' => $division]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ambil jumlah notifikasi yang belum dibaca (unread) khusus untuk divisi tertentu.
     * Jika $division null, otomatis menggunakan divisi aktif dari session/AccessControl.
     */
    public static function getUnreadCount(?string $division = null): int
    {
        if ($division === null) {
            $division = AccessControl::getCurrentRole();
        }
        $division = trim($division);

        try {
            $row = Database::selectOne("
                SELECT COUNT(*) as unread_count 
                FROM division_notifications 
                WHERE is_read = 0 AND (LOWER(target_division) = LOWER(:div) OR UPPER(target_division) = 'ALL')
            ", ['div' => $division]);
            return (int) ($row['unread_count'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Tandai semua notifikasi untuk divisi tertentu sebagai sudah dibaca.
     * Jika $division null, otomatis menggunakan divisi aktif dari session/AccessControl.
     */
    public static function markAllAsRead(?string $division = null): void
    {
        if ($division === null) {
            $division = AccessControl::getCurrentRole();
        }
        $division = trim($division);

        try {
            Database::statement("
                UPDATE division_notifications 
                SET is_read = 1 
                WHERE is_read = 0 AND (LOWER(target_division) = LOWER(:div) OR UPPER(target_division) = 'ALL')
            ", ['div' => $division]);
        } catch (\Throwable $e) {
            error_log("Gagal update read status divisi {$division}: " . $e->getMessage());
        }
    }

    /**
     * Inisialisasi notifikasi (hanya mencatat dari aktivitas data aktual).
     */
    public static function seedIfEmpty(): void
    {
        // Clean slate: hanya mencatat notifikasi dari aktivitas aktual pengguna
    }
}
