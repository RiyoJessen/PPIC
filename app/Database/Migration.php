<?php
declare(strict_types=1);

namespace App\Database;

use App\Config\AppConfig;
use RuntimeException;

class Migration
{
    public static function run(): void
    {
        $driver = AppConfig::getDbDriver();

        if ($driver === 'sqlite') {
            self::runSqlite();
        } else {
            self::runMysql();
        }
    }

    private static function runSqlite(): void
    {
        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL DEFAULT 'PPIC',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            description TEXT
        );

        CREATE TABLE IF NOT EXISTS warehouses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            location TEXT,
            capacity_note TEXT
        );

        CREATE TABLE IF NOT EXISTS suppliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            contact_person TEXT,
            phone TEXT,
            email TEXT,
            address TEXT,
            lead_time_days INTEGER DEFAULT 7,
            rating REAL DEFAULT 4.5
        );

        CREATE TABLE IF NOT EXISTS materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            category_id INTEGER,
            unit TEXT NOT NULL,
            min_stock REAL DEFAULT 0,
            safety_stock REAL DEFAULT 0,
            reorder_point REAL DEFAULT 0,
            lead_time_days INTEGER DEFAULT 7,
            moq REAL DEFAULT 0,
            order_multiple REAL DEFAULT 1,
            unit_cost REAL DEFAULT 0,
            preferred_supplier_id INTEGER,
            warehouse_id INTEGER,
            storage_condition TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id),
            FOREIGN KEY (preferred_supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            category TEXT,
            unit TEXT NOT NULL,
            standard_batch_size REAL DEFAULT 1000,
            description TEXT,
            is_active INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS product_stock_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL UNIQUE,
            current_qty REAL NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS boms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            version TEXT DEFAULT 'v1.0',
            batch_size REAL NOT NULL DEFAULT 1000,
            unit TEXT NOT NULL,
            yield_pct REAL DEFAULT 100,
            scrap_pct REAL DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            notes TEXT,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS bom_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bom_id INTEGER NOT NULL,
            material_id INTEGER NOT NULL,
            quantity_per_batch REAL NOT NULL,
            unit TEXT NOT NULL,
            notes TEXT,
            FOREIGN KEY (bom_id) REFERENCES boms(id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES materials(id)
        );

        CREATE TABLE IF NOT EXISTS inventory_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            warehouse_id INTEGER NOT NULL,
            batch_number TEXT NOT NULL,
            coa_number TEXT,
            expired_date DATE,
            initial_qty REAL NOT NULL,
            current_qty REAL NOT NULL,
            allocated_qty REAL DEFAULT 0,
            status TEXT DEFAULT 'active',
            received_date DATE,
            FOREIGN KEY (material_id) REFERENCES materials(id),
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        );

        CREATE TABLE IF NOT EXISTS stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            warehouse_id INTEGER NOT NULL,
            batch_id INTEGER,
            movement_type TEXT NOT NULL,
            reference_type TEXT,
            reference_number TEXT,
            quantity REAL NOT NULL,
            unit TEXT NOT NULL,
            before_stock REAL DEFAULT 0,
            after_stock REAL DEFAULT 0,
            notes TEXT,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (material_id) REFERENCES materials(id),
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            FOREIGN KEY (batch_id) REFERENCES inventory_batches(id)
        );

        CREATE TABLE IF NOT EXISTS ai_forecast_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            analysis_date DATE NOT NULL,
            current_stock REAL DEFAULT 0,
            safety_stock REAL DEFAULT 0,
            projected_demand_30d REAL DEFAULT 0,
            recommended_qty REAL DEFAULT 0,
            risk_level TEXT DEFAULT 'Aman',
            trend_direction TEXT DEFAULT 'stabil',
            ai_narrative TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (material_id) REFERENCES materials(id)
        );

        CREATE TABLE IF NOT EXISTS email_notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipients VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            critical_items_count INT NOT NULL DEFAULT 0,
            summary_html LONGTEXT,
            status VARCHAR(50) NOT NULL DEFAULT 'sent',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            module TEXT NOT NULL,
            description TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS division_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            division_source TEXT NOT NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            link TEXT DEFAULT '/',
            user_id INTEGER,
            user_name TEXT,
            target_division TEXT DEFAULT 'ALL',
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS material_price_histories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            price_month TEXT NOT NULL,
            recorded_date DATE NOT NULL,
            market_price REAL NOT NULL DEFAULT 0,
            source TEXT DEFAULT 'Indeks Pasar Kimia',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (material_id) REFERENCES materials(id)
        );

        CREATE TABLE IF NOT EXISTS sales_histories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doc_number TEXT UNIQUE NOT NULL,
            doc_type TEXT NOT NULL DEFAULT 'history',
            customer_name TEXT NOT NULL,
            product_id INTEGER NOT NULL,
            sale_month TEXT NOT NULL,
            sale_date DATE NOT NULL,
            quantity REAL NOT NULL DEFAULT 0,
            unit_price REAL NOT NULL DEFAULT 0,
            total_amount REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'fulfilled',
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS sales_forecasts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            forecast_year INTEGER NOT NULL,
            method TEXT NOT NULL DEFAULT 'manual',
            m1 REAL DEFAULT 0,
            m2 REAL DEFAULT 0,
            m3 REAL DEFAULT 0,
            m4 REAL DEFAULT 0,
            m5 REAL DEFAULT 0,
            m6 REAL DEFAULT 0,
            m7 REAL DEFAULT 0,
            m8 REAL DEFAULT 0,
            m9 REAL DEFAULT 0,
            m10 REAL DEFAULT 0,
            m11 REAL DEFAULT 0,
            m12 REAL DEFAULT 0,
            total_12m_qty REAL DEFAULT 0,
            notes TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );
SQL;

        Database::getConnection()->exec($schema);
        self::removeLegacyPurchasingSchema();
    }

    private static function runMysql(): void
    {
        // Similar schema adapted for MySQL with engine InnoDB
        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'PPIC',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(150) NOT NULL,
            description TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS warehouses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(150) NOT NULL,
            type VARCHAR(50) NOT NULL,
            location VARCHAR(200),
            capacity_note VARCHAR(200)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(100),
            phone VARCHAR(50),
            email VARCHAR(100),
            address TEXT,
            lead_time_days INT DEFAULT 7,
            rating DECIMAL(3,2) DEFAULT 4.5
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            category_id INT,
            unit VARCHAR(30) NOT NULL,
            min_stock DECIMAL(12,2) DEFAULT 0,
            safety_stock DECIMAL(12,2) DEFAULT 0,
            reorder_point DECIMAL(12,2) DEFAULT 0,
            lead_time_days INT DEFAULT 7,
            moq DECIMAL(12,2) DEFAULT 0,
            order_multiple DECIMAL(12,2) DEFAULT 1,
            unit_cost DECIMAL(14,2) DEFAULT 0,
            preferred_supplier_id INT,
            warehouse_id INT,
            storage_condition VARCHAR(150),
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            FOREIGN KEY (preferred_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            category VARCHAR(100),
            unit VARCHAR(30) NOT NULL,
            standard_batch_size DECIMAL(12,2) DEFAULT 1000,
            description TEXT,
            is_active TINYINT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS product_stock_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL UNIQUE,
            current_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS boms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            version VARCHAR(20) DEFAULT 'v1.0',
            batch_size DECIMAL(12,2) NOT NULL DEFAULT 1000,
            unit VARCHAR(30) NOT NULL,
            yield_pct DECIMAL(5,2) DEFAULT 100,
            scrap_pct DECIMAL(5,2) DEFAULT 0,
            is_active TINYINT DEFAULT 1,
            notes TEXT,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS bom_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bom_id INT NOT NULL,
            material_id INT NOT NULL,
            quantity_per_batch DECIMAL(12,2) NOT NULL,
            unit VARCHAR(30) NOT NULL,
            notes VARCHAR(200),
            FOREIGN KEY (bom_id) REFERENCES boms(id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS inventory_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            warehouse_id INT NOT NULL,
            batch_number VARCHAR(100) NOT NULL,
            coa_number VARCHAR(100),
            expired_date DATE,
            initial_qty DECIMAL(12,2) NOT NULL,
            current_qty DECIMAL(12,2) NOT NULL,
            allocated_qty DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(30) DEFAULT 'active',
            received_date DATE,
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            warehouse_id INT NOT NULL,
            batch_id INT NULL,
            movement_type VARCHAR(50) NOT NULL,
            reference_type VARCHAR(50),
            reference_number VARCHAR(100),
            quantity DECIMAL(12,2) NOT NULL,
            unit VARCHAR(30) NOT NULL,
            before_stock DECIMAL(12,2) DEFAULT 0,
            after_stock DECIMAL(12,2) DEFAULT 0,
            notes TEXT,
            user_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS ai_forecast_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            analysis_date DATE NOT NULL,
            current_stock DECIMAL(12,2) DEFAULT 0,
            safety_stock DECIMAL(12,2) DEFAULT 0,
            projected_demand_30d DECIMAL(12,2) DEFAULT 0,
            recommended_qty DECIMAL(12,2) DEFAULT 0,
            risk_level VARCHAR(30) DEFAULT 'Aman',
            trend_direction VARCHAR(30) DEFAULT 'stabil',
            ai_narrative TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS email_notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipients VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            critical_items_count INT NOT NULL DEFAULT 0,
            summary_html LONGTEXT,
            status VARCHAR(50) NOT NULL DEFAULT 'sent',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS division_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            division_source VARCHAR(50) NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT '/',
            user_id INT NULL,
            user_name VARCHAR(150) NULL,
            target_division VARCHAR(50) DEFAULT 'ALL',
            is_read TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS material_price_histories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            price_month VARCHAR(7) NOT NULL,
            recorded_date DATE NOT NULL,
            market_price DECIMAL(14,2) NOT NULL DEFAULT 0,
            source VARCHAR(100) DEFAULT 'Indeks Pasar Kimia',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mat_month (material_id, price_month),
            FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS sales_histories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_number VARCHAR(80) UNIQUE NOT NULL,
            doc_type VARCHAR(30) NOT NULL DEFAULT 'history',
            customer_name VARCHAR(180) NOT NULL,
            product_id INT NOT NULL,
            sale_month VARCHAR(7) NOT NULL,
            sale_date DATE NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'fulfilled',
            notes TEXT,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sales_prod_month (product_id, sale_month),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS sales_forecasts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            forecast_year INT NOT NULL,
            method VARCHAR(30) NOT NULL DEFAULT 'manual',
            m1 DECIMAL(12,2) DEFAULT 0,
            m2 DECIMAL(12,2) DEFAULT 0,
            m3 DECIMAL(12,2) DEFAULT 0,
            m4 DECIMAL(12,2) DEFAULT 0,
            m5 DECIMAL(12,2) DEFAULT 0,
            m6 DECIMAL(12,2) DEFAULT 0,
            m7 DECIMAL(12,2) DEFAULT 0,
            m8 DECIMAL(12,2) DEFAULT 0,
            m9 DECIMAL(12,2) DEFAULT 0,
            m10 DECIMAL(12,2) DEFAULT 0,
            m11 DECIMAL(12,2) DEFAULT 0,
            m12 DECIMAL(12,2) DEFAULT 0,
            total_12m_qty DECIMAL(14,2) DEFAULT 0,
            notes TEXT,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fc_prod_year (product_id, forecast_year),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                Database::getConnection()->exec($stmt);
            }
        }
        self::removeLegacyPurchasingSchema();
    }

    private static function removeLegacyPurchasingSchema(): void
    {
        $legacyTables = [
            'purchase_order_items',
            'purchase_orders',
            'purchase_requisition_items',
            'purchase_requisitions',
        ];
        if (AppConfig::getDbDriver() === 'mysql') {
            $existingTables = Database::select("
                SELECT TABLE_NAME AS name
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ('purchase_order_items', 'purchase_orders', 'purchase_requisition_items', 'purchase_requisitions')
            ");
        } else {
            $existingTables = Database::select("
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name IN ('purchase_order_items', 'purchase_orders', 'purchase_requisition_items', 'purchase_requisitions')
            ");
        }
        $existingNames = array_fill_keys(array_column($existingTables, 'name'), true);
        foreach ($legacyTables as $table) {
            if (isset($existingNames[$table])) {
                Database::statement("DROP TABLE IF EXISTS {$table}");
            }
        }

        Database::statement("
            DELETE FROM audit_logs
            WHERE LOWER(module) = 'purchasing'
               OR UPPER(action) IN ('CREATE_PR_AI', 'APPROVE_PR', 'ISSUE_PO', 'CREATE_PR_MANUAL', 'DELETE_PR')
        ");
        Database::statement("
            DELETE FROM division_notifications
            WHERE LOWER(division_source) = 'purchasing'
               OR LOWER(target_division) = 'purchasing'
               OR UPPER(type) IN ('PR_MANUAL', 'PR_APPROVED', 'PO_GENERATED', 'PO_RECEIVED')
               OR LOWER(link) LIKE '%/purchasing%'
        ");
        Database::statement("
            DELETE FROM stock_movements
            WHERE UPPER(COALESCE(reference_type, '')) = 'PO'
               OR UPPER(COALESCE(movement_type, '')) = 'IN_PURCHASE'
               OR UPPER(COALESCE(reference_number, '')) LIKE 'PO-%'
        ");

        if (AppConfig::getDbDriver() === 'mysql') {
            $column = Database::selectOne("
                SELECT COUNT(*) as column_count
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'ai_forecast_logs'
                  AND COLUMN_NAME = 'is_applied_to_pr'
            ");
            if ((int) ($column['column_count'] ?? 0) > 0) {
                Database::statement('ALTER TABLE ai_forecast_logs DROP COLUMN is_applied_to_pr');
            }
            return;
        }

        $columns = Database::select('PRAGMA table_info(ai_forecast_logs)');
        foreach ($columns as $column) {
            if ($column['name'] === 'is_applied_to_pr') {
                Database::statement('ALTER TABLE ai_forecast_logs DROP COLUMN is_applied_to_pr');
                break;
            }
        }
    }

}
