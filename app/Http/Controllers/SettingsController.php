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

        return [
            'api_key' => $apiKey,
            'model' => $model,
            'mail_config' => $mailConfig,
            'email_logs' => $emailLogs,
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

    public static function importRawMaterialWorkbooks(string $leadTimePath, ?string $usagePath, int $userId): array
    {
        return \App\Services\RawMaterialWorkbookImportService::import(
            $leadTimePath,
            $usagePath,
            $userId
        );
    }

    public static function importMaterialsCsv(string $csvContent, int $userId): int
    {
        // XLSX files are ZIP containers and begin with PK. Do not let binary
        // bytes fall through to the legacy positional CSV parser.
        if (str_starts_with($csvContent, "PK\x03\x04")) {
            throw new \InvalidArgumentException('File yang dipilih adalah Excel (.xlsx), bukan CSV. Simpan atau ekspor file sebagai CSV terlebih dahulu.');
        }

        $lines = explode("\n", str_replace("\r", "", trim($csvContent)));
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('File CSV kosong atau hanya berisi header.');
        }

        // Excel may add a UTF-8 BOM to the first header cell.
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string) array_shift($lines));
        $header = array_map(
            static fn(string $column): string => strtolower(trim($column)),
            str_getcsv($firstLine, ',', '"', '')
        );
        $headerIndexes = array_flip($header);
        $hasNamedHeader = isset($headerIndexes['code']) && isset($headerIndexes['name']);
        if (!$hasNamedHeader && count($header) < 3) {
            throw new \InvalidArgumentException('Format CSV tidak dikenali. Gunakan template dengan kolom code, name, unit, min_stock, lead_time_days, moq, unit_cost.');
        }
        $count = 0;

        Database::beginTransaction();
        try {
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                // Pass an explicit empty escape character. PHP 8.4 deprecates
                // relying on str_getcsv()'s non-empty default escape value.
                $row = str_getcsv($line, ',', '"', '');
                if (count($row) < 3) continue;

                if ($hasNamedHeader) {
                    $value = static function (string $column, mixed $default = '') use ($headerIndexes, $row): mixed {
                        return isset($headerIndexes[$column]) ? ($row[$headerIndexes[$column]] ?? $default) : $default;
                    };
                    $code = trim((string) $value('code'));
                    $name = trim((string) $value('name'));
                    $unit = trim((string) $value('unit', 'Kg'));
                    $min = (float) $value('min_stock', $value('minimum_stock', 0));
                    $lead = (int) $value('lead_time_days', $value('lead_time', 7));
                    $moq = (float) $value('moq', 0);
                    $cost = (float) $value('unit_cost', 0);
                } else {
                    $code = trim($row[0] ?? '');
                    $name = trim($row[1] ?? '');
                    $unit = trim($row[2] ?? 'Kg');
                    $min = (float) ($row[3] ?? 0);
                    $legacyLayout = count($row) >= 9;
                    $lead = (int) ($row[$legacyLayout ? 6 : 4] ?? 7);
                    $moq = (float) ($row[$legacyLayout ? 7 : 5] ?? 0);
                    $cost = (float) ($row[$legacyLayout ? 8 : 6] ?? 0);
                }

                if ($code === '' || $name === '') continue;

                // Check existing
                $existing = Database::selectOne("SELECT id FROM materials WHERE code = :code", ['code' => $code]);
                if ($existing) {
                    Database::update('materials', [
                        'name' => $name,
                        'unit' => $unit,
                        'min_stock' => $min,
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

    public static function importMaterialsXlsx(string $path, int $userId): int
    {
        $sheets = \App\Services\RawMaterialWorkbookImportService::readWorkbookSheets($path);
        $csv = fopen('php://temp', 'r+');
        if ($csv === false) {
            throw new \RuntimeException('Tidak dapat menyiapkan data workbook untuk diimpor.');
        }

        $columns = ['code', 'name', 'unit', 'min_stock', 'lead_time_days', 'moq', 'unit_cost'];
        fputcsv($csv, $columns, ',', '"', '');
        $importedRows = 0;

        foreach ($sheets as $rows) {
            if (empty($rows)) continue;
            $indexes = [];
            foreach (array_keys($rows[0]) as $header) {
                $normalized = strtolower(trim((string) $header));
                $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized);
                $indexes[$normalized] = $header;
            }
            if (!isset($indexes['code'], $indexes['name'])) continue;

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $aliases = match ($column) {
                        'min_stock' => ['min_stock', 'minimum_stock'],
                        'lead_time_days' => ['lead_time_days', 'lead_time'],
                        default => [$column],
                    };
                    $value = '';
                    foreach ($aliases as $alias) {
                        if (isset($indexes[$alias])) {
                            $value = $row[$indexes[$alias]] ?? '';
                            break;
                        }
                    }
                    $values[] = $value;
                }
                if (trim((string) $values[0]) === '' && trim((string) $values[1]) === '') continue;
                fputcsv($csv, $values, ',', '"', '');
                $importedRows++;
            }
        }

        if ($importedRows === 0) {
            fclose($csv);
            throw new \InvalidArgumentException('Workbook tidak memiliki baris dengan header code dan name. Gunakan kolom code, name, unit, min_stock, lead_time_days, moq, unit_cost.');
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);
        if ($csvContent === false) {
            throw new \RuntimeException('Data workbook gagal dibaca.');
        }

        return self::importMaterialsCsv($csvContent, $userId);
    }
}
