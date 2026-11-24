<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Config\AppConfig;
use App\Database\Database;
use App\Database\Seeder;

class SettingsController extends Controller
{
    public static function index(): array
    {
        $dbDriver = AppConfig::getDbDriver();
        $apiKey = AppConfig::getGeminiApiKey();
        $model = AppConfig::getGeminiModel();

        $mailConfig = [
            'host' => AppConfig::get('MAIL_HOST', 'smtp.gmail.com'),
            'port' => AppConfig::get('MAIL_PORT', 587),
            'username' => AppConfig::get('MAIL_USERNAME', ''),
            'password' => AppConfig::get('MAIL_PASSWORD', ''),
            'encryption' => AppConfig::get('MAIL_ENCRYPTION', 'tls'),
            'from_address' => AppConfig::get('MAIL_FROM_ADDRESS', 'ppic@duniakimia.co.id'),
            'from_name' => AppConfig::get('MAIL_FROM_NAME', 'PPIC Alert'),
            'recipients' => AppConfig::get('MAIL_ALERT_RECIPIENTS', 'ppic@duniakimia.co.id'),
        ];

        $emailLogs = Database::select("
            SELECT * FROM email_notification_logs ORDER BY id DESC LIMIT 15
        ");

        $stats = [
            'materials_count' => Database::selectOne("SELECT COUNT(*) as c FROM materials")['c'] ?? 0,
            'products_count' => Database::selectOne("SELECT COUNT(*) as c FROM products")['c'] ?? 0,
            'boms_count' => Database::selectOne("SELECT COUNT(*) as c FROM boms")['c'] ?? 0,
            'batches_count' => Database::selectOne("SELECT COUNT(*) as c FROM inventory_batches")['c'] ?? 0,
            'email_count' => Database::selectOne("SELECT COUNT(*) as c FROM email_notification_logs")['c'] ?? 0,
        ];

        return [
            'db_driver' => $dbDriver,
            'api_key' => $apiKey,
            'model' => $model,
            'mail_config' => $mailConfig,
            'email_logs' => $emailLogs,
            'stats' => $stats,
        ];
    }

    public static function saveMailSettings(array $data, int $userId): void
    {
        $host = trim($data['mail_host'] ?? 'smtp.gmail.com');
        $port = trim($data['mail_port'] ?? '587');
        $user = trim($data['mail_username'] ?? '');
        $pass = trim($data['mail_password'] ?? '');
        $enc = trim($data['mail_encryption'] ?? 'tls');
        $recipients = trim($data['mail_recipients'] ?? 'ppic@duniakimia.co.id');
        $from = trim($data['mail_from'] ?? 'ppic@duniakimia.co.id');

        $envPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace('/^MAIL_HOST=.*$/m', 'MAIL_HOST=' . $host, $content);
            $content = preg_replace('/^MAIL_PORT=.*$/m', 'MAIL_PORT=' . $port, $content);
            $content = preg_replace('/^MAIL_USERNAME=.*$/m', 'MAIL_USERNAME=' . $user, $content);
            $content = preg_replace('/^MAIL_PASSWORD=.*$/m', 'MAIL_PASSWORD=' . $pass, $content);
            $content = preg_replace('/^MAIL_ENCRYPTION=.*$/m', 'MAIL_ENCRYPTION=' . $enc, $content);
            $content = preg_replace('/^MAIL_ALERT_RECIPIENTS=.*$/m', 'MAIL_ALERT_RECIPIENTS=' . $recipients, $content);
            $content = preg_replace('/^MAIL_FROM_ADDRESS=.*$/m', 'MAIL_FROM_ADDRESS=' . $from, $content);
            file_put_contents($envPath, $content);
        }

        AppConfig::set('MAIL_HOST', $host);
        AppConfig::set('MAIL_PORT', $port);
        AppConfig::set('MAIL_USERNAME', $user);
        AppConfig::set('MAIL_PASSWORD', $pass);
        AppConfig::set('MAIL_ENCRYPTION', $enc);
        AppConfig::set('MAIL_ALERT_RECIPIENTS', $recipients);
        AppConfig::set('MAIL_FROM_ADDRESS', $from);

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'UPDATE_MAIL_SETTINGS',
            'module' => 'Settings',
            'description' => "Memperbarui konfigurasi SMTP email & penerima notifikasi stok ({$recipients}).",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function saveSettings(array $data, int $userId): void
    {
        $apiKey = trim($data['gemini_api_key'] ?? '');
        $model = trim($data['gemini_model'] ?? 'gemini-1.5-flash');

        $envPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace('/^GEMINI_API_KEY=.*$/m', 'GEMINI_API_KEY=' . $apiKey, $content);
            $content = preg_replace('/^GEMINI_MODEL=.*$/m', 'GEMINI_MODEL=' . $model, $content);
            file_put_contents($envPath, $content);
        }

        AppConfig::set('GEMINI_API_KEY', $apiKey);
        AppConfig::set('GEMINI_MODEL', $model);

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'UPDATE_SETTINGS',
            'module' => 'Settings',
            'description' => 'Memperbarui konfigurasi sistem dan API key Google Gemini.',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function wipeAllData(int $userId): void
    {
        Seeder::wipeAllBusinessData();

        Database::insert('audit_logs', [
            'user_id' => $userId,
            'action' => 'WIPE_DATA',
            'module' => 'Settings',
            'description' => 'Mengosongkan seluruh data transaksi dan master untuk input data aktual baru.',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function importMaterialsCsv(string $csvContent, int $userId): int
    {
        $lines = explode("\n", str_replace("\r", "", trim($csvContent)));
        if (count($lines) < 2) return 0;

        $header = str_getcsv(array_shift($lines));
        $count = 0;

        Database::beginTransaction();
        try {
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $row = str_getcsv($line);
                if (count($row) < 3) continue;

                // Format: code, name, unit, min_stock, safety_stock, reorder_point, lead_time, moq, unit_cost
                $code = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                $unit = trim($row[2] ?? 'Kg');
                $min = (float) ($row[3] ?? 0);
                $safety = (float) ($row[4] ?? 0);
                $rop = (float) ($row[5] ?? 0);
                $lead = (int) ($row[6] ?? 7);
                $moq = (float) ($row[7] ?? 0);
                $cost = (float) ($row[8] ?? 0);

                if ($code === '' || $name === '') continue;

                // Check existing
                $existing = Database::selectOne("SELECT id FROM materials WHERE code = :code", ['code' => $code]);
                if ($existing) {
                    Database::update('materials', [
                        'name' => $name,
                        'unit' => $unit,
                        'min_stock' => $min,
                        'safety_stock' => $safety,
                        'reorder_point' => $rop,
                        'lead_time_days' => $lead,
                        'moq' => $moq,
                        'unit_cost' => $cost,
                    ], 'id = :id', ['id' => $existing['id']]);
                } else {
                    Database::insert('materials', [
                        'code' => $code,
                        'name' => $name,
                        'category_id' => 1,
                        'unit' => $unit,
                        'min_stock' => $min,
                        'safety_stock' => $safety,
                        'reorder_point' => $rop,
                        'lead_time_days' => $lead,
                        'moq' => $moq,
                        'order_multiple' => 1,
                        'unit_cost' => $cost,
                        'warehouse_id' => 1,
                        'is_active' => 1,
                    ]);
                }
                $count++;
            }

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'IMPORT_MATERIALS_CSV',
                'module' => 'Master Data',
                'description' => "Import {$count} bahan baku dari file CSV aktual.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
            return $count;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
