<?php
declare(strict_types=1);

namespace App\Services;

class AccessControl
{
    public const ROLE_PPIC = 'PPIC';
    public const ROLE_RD = 'RD';

    public static function getCurrentRole(): string
    {
        $role = $_SESSION['user_division'] ?? self::ROLE_PPIC;
        return in_array($role, [self::ROLE_PPIC, self::ROLE_RD], true)
            ? $role
            : self::ROLE_PPIC;
    }

    public static function isSuperAdmin(): bool
    {
        return self::getCurrentRole() === self::ROLE_PPIC;
    }

    public static function canAccessDivision(string $division): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }

        return strcasecmp(self::getCurrentRole(), $division) === 0;
    }

    public static function getDivisionForUri(string $uri): string
    {
        // PPIC Specific (Forecast Sales 12 Bln & AI Forecasting)
        if (str_starts_with($uri, '/ai') || str_starts_with($uri, '/api/ai') || str_starts_with($uri, '/forecast-sales')) {
            return self::ROLE_PPIC;
        }

        // Data supplier digunakan sebagai referensi analisis AI PPIC.
        if (str_starts_with($uri, '/master/suppliers')) {
            return self::ROLE_PPIC;
        }

        // R&D (Evaluasi Formula BOM, Finish Good, Master & Stok Material)
        if (
            str_starts_with($uri, '/master/materials') ||
            str_starts_with($uri, '/master/products') ||
            str_starts_with($uri, '/master/boms')
        ) {
            return self::ROLE_RD;
        }

        // Aksi baca notifikasi milik divisi aktif boleh diakses semua divisi
        if (
            $uri === '/notifications/mark-read' ||
            $uri === '/notifications/mark-read-ajax'
        ) {
            return 'PUBLIC';
        }

        // Sistem & Administrasi (Super Admin Only: Settings, Trigger Email Notifications)
        if (
            str_starts_with($uri, '/settings') ||
            str_starts_with($uri, '/notifications')
        ) {
            return self::ROLE_PPIC;
        }

        return 'PUBLIC';
    }

    public static function checkAccess(string $uri): bool
    {
        $targetDivision = self::getDivisionForUri($uri);
        if ($targetDivision === 'PUBLIC') {
            return true;
        }

        return self::canAccessDivision($targetDivision);
    }

    public static function getDefaultRouteForCurrentRole(): string
    {
        return match(self::getCurrentRole()) {
            self::ROLE_RD => '/master/boms',
            default => '/'
        };
    }

    public static function getDivisionLabel(string $role): string
    {
        return match($role) {
            self::ROLE_PPIC => 'PPIC (Forecast Sales 12 Bln & MRP)',
            self::ROLE_RD => 'Divisi R&D (Evaluasi Formula BOM & Material)',
            default => $role
        };
    }
}
