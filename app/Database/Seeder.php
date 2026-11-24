<?php
declare(strict_types=1);

namespace App\Database;

class Seeder
{
    public static function run(): void
    {
        self::ensureBaseSystemReferences();
    }

    /**
     * Memastikan referensi struktural dasar (akun role, kategori umum & lokasi penyimpanan default)
     * tersedia agar foreign key database berjalan normal saat input data aktual.
     */
    public static function ensureBaseSystemReferences(): void
    {
        $userCount = (int) (Database::selectOne("SELECT COUNT(*) as c FROM users")['c'] ?? 0);
        if ($userCount === 0) {
            $users = [
                ['id' => 1, 'name' => 'Administrator', 'email' => 'admin@duniakimia.co.id', 'role' => 'Administrator'],
                ['id' => 2, 'name' => 'PPIC Officer', 'email' => 'ppic@duniakimia.co.id', 'role' => 'PPIC'],
                ['id' => 3, 'name' => 'Warehouse Officer', 'email' => 'warehouse@duniakimia.co.id', 'role' => 'Warehouse'],
                ['id' => 5, 'name' => 'Manager Operasional', 'email' => 'manager@duniakimia.co.id', 'role' => 'Manager/Approver'],
                ['id' => 6, 'name' => 'Management', 'email' => 'management@duniakimia.co.id', 'role' => 'Management'],
                ['id' => 7, 'name' => 'R&D Specialist', 'email' => 'rd@duniakimia.co.id', 'role' => 'RD'],
            ];
            foreach ($users as $u) {
                try {
                    Database::insert('users', $u);
                } catch (\Throwable) {}
            }
        }

        $catCount = (int) (Database::selectOne("SELECT COUNT(*) as c FROM categories")['c'] ?? 0);
        if ($catCount === 0) {
            Database::insert('categories', [
                'code' => 'UMUM',
                'name' => 'Bahan Baku Kimia',
                'description' => 'Kategori standar bahan baku',
            ]);
        }

        $whCount = (int) (Database::selectOne("SELECT COUNT(*) as c FROM warehouses")['c'] ?? 0);
        if ($whCount === 0) {
            Database::insert('warehouses', [
                'code' => 'GUD-01',
                'name' => 'Gudang Utama',
                'type' => 'raw_dry',
                'location' => 'Area Gudang Utama',
                'capacity_note' => 'Gudang Penyimpanan Material & Finish Good',
            ]);
        }
    }

    /**
     * Hapus seluruh data transaksi dan master hingga bersih (Clean Slate).
     */
    public static function wipeAllBusinessData(): void
    {
        $tables = [
            'audit_logs',
            'ai_forecast_logs',
            'material_price_histories',
            'email_notification_logs',
            'division_notifications',
            'sales_forecasts',
            'sales_histories',
            'stock_movements',
            'inventory_batches',
            'bom_items',
            'boms',
            'products',
            'materials',
            'suppliers',
            'warehouses',
            'categories',
        ];

        $isMysql = \App\Config\AppConfig::getDbDriver() === 'mysql';
        if ($isMysql) {
            Database::statement('SET FOREIGN_KEY_CHECKS = 0;');
        } else {
            Database::statement('PRAGMA foreign_keys = OFF;');
        }

        foreach ($tables as $table) {
            try {
                Database::statement("DELETE FROM {$table};");
                if ($isMysql) {
                    Database::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1;");
                } else {
                    Database::statement("DELETE FROM sqlite_sequence WHERE name='{$table}';");
                }
            } catch (\Throwable) {
                try {
                    Database::statement("TRUNCATE TABLE {$table};");
                } catch (\Throwable) {}
            }
        }

        if ($isMysql) {
            Database::statement('SET FOREIGN_KEY_CHECKS = 1;');
        } else {
            Database::statement('PRAGMA foreign_keys = ON;');
        }

        self::ensureBaseSystemReferences();
    }
}
