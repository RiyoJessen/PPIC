<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\AppConfig;
use App\Database\Database;
use RuntimeException;

class EmailNotificationService
{
    /**
     * Otomatis memeriksa dan mengirimkan email jika ada barang habis dalam 15-30 hari
     * Berjalan otomatis saat ada aktivitas sistem (maksimal 1x per 24 jam untuk mencegah spam)
     */
    public static function autoCheckAndSendIfDue(): ?array
    {
        $enabled = filter_var(AppConfig::get('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return null;
        }

        // Cek apakah notifikasi otomatis sudah dikirim dalam 24 jam terakhir
        $recentAlert = Database::selectOne("
            SELECT id, created_at FROM email_notification_logs 
            WHERE created_at >= " . (AppConfig::getDbDriver() === 'mysql' ? "DATE_SUB(NOW(), INTERVAL 24 HOUR)" : "datetime('now', '-24 hours')") . "
            AND status IN ('sent', 'simulated')
            ORDER BY id DESC LIMIT 1
        ");

        if ($recentAlert) {
            return null; // Sudah terkirim dalam 24 jam terakhir (cooldown aktif)
        }

        // Jalankan pengecekan dan pengiriman otomatis
        return self::sendCriticalStockAlert();
    }

    public static function sendCriticalStockAlert(?string $customRecipient = null, int $maxDaysThreshold = 30): array
    {
        $materials = InventoryService::getMaterialStockSummary();
        $alertItems = [];
        $criticalUnder15 = [];
        $warning15To30 = [];

        foreach ($materials as $m) {
            $rec = MaterialPlanningService::calculateRecommendation((int) $m['id']);
            $dailyUsage = (float) $rec['daily_usage'];
            $available = (float) $m['available_stock'];
            $leadTime = (int) $m['lead_time_days'];

            // Hitung Days of Supply (Sisa Hari Persediaan sebelum habis menurut forecast)
            $daysOfSupply = ($dailyUsage > 0) ? round($available / $dailyUsage, 1) : 999.0;
            $estimatedEmptyDate = date('Y-m-d', strtotime('+' . max(1, (int)round($daysOfSupply)) . ' days'));
            $depletionFormatted = date('d M Y', strtotime($estimatedEmptyDate));

            // Hitung waktu tindak lanjut berdasarkan lead time acuan supplier.
            $reviewDaysLeft = max(0, (int)round($daysOfSupply) - $leadTime);
            $latestReviewDate = date('d M Y', strtotime('+' . $reviewDaysLeft . ' days'));

            // Kriteria: Stok habis kurang dari 15-30 hari ATAU status Kritis
            if ($daysOfSupply <= $maxDaysThreshold || $m['stock_status'] === 'Kritis') {
                $m['recommended_q'] = $rec['final_recommended_qty'];
                $m['daily_usage'] = $dailyUsage;
                $m['days_of_supply'] = $daysOfSupply;
                $m['estimated_empty_date'] = $estimatedEmptyDate;
                $m['depletion_date_formatted'] = $depletionFormatted;
                $m['review_days_left'] = $reviewDaysLeft;
                $m['latest_review_date'] = $latestReviewDate;
                $m['is_review_due_today'] = ($reviewDaysLeft === 0);

                if ($daysOfSupply < 15 || $m['stock_status'] === 'Kritis') {
                    $m['alert_tier'] = 'Kritis (< 15 Hari)';
                    $m['tier_color'] = '#dc2626';
                    $criticalUnder15[] = $m;
                } else {
                    $m['alert_tier'] = 'Waspada (15–30 Hari)';
                    $m['tier_color'] = '#d97706';
                    $warning15To30[] = $m;
                }
                $alertItems[] = $m;
            }
        }

        if (empty($alertItems)) {
            return [
                'success' => true,
                'sent' => false,
                'message' => 'Seluruh stok bahan baku dalam kondisi aman (> 30 hari persediaan). Tidak ada email peringatan yang dikirim.',
                'critical_count' => 0,
            ];
        }

        // Urutkan bahan baku berdasarkan sisa hari paling sedikit (paling darurat di atas)
        usort($alertItems, fn($a, $b) => $a['days_of_supply'] <=> $b['days_of_supply']);
        usort($criticalUnder15, fn($a, $b) => $a['days_of_supply'] <=> $b['days_of_supply']);
        usort($warning15To30, fn($a, $b) => $a['days_of_supply'] <=> $b['days_of_supply']);

        $earliestItem = $alertItems[0];
        $earliestDate = $earliestItem['depletion_date_formatted'];
        $earliestDays = $earliestItem['days_of_supply'];
        $reviewsDueToday = count(array_filter($alertItems, fn($i) => $i['is_review_due_today']));

        $recipients = $customRecipient ?: (string) AppConfig::get('MAIL_ALERT_RECIPIENTS', 'riyo.nurmalaya22@student.uisi.ac.id');
        
        // Subjek email dinamis sesuai tanggal aktual forecast AI
        if ($reviewsDueToday > 0) {
            $subject = "⚠️ [AI FORECAST PPIC] {$reviewsDueToday} Bahan Perlu Ditinjau Hari Ini (Stok Habis Mulai {$earliestDate}) — PT Dunia Kimia Jaya Gresik";
        } else {
            $subject = "⚠️ [AI FORECAST PPIC] Bahan Baku Habis Mulai {$earliestDate} ({$earliestDays} Hari Lagi) — PT Dunia Kimia Jaya Gresik";
        }

        $html = self::buildHtmlEmail($criticalUnder15, $warning15To30, count($alertItems), $earliestItem, $reviewsDueToday);

        $host = (string) AppConfig::get('MAIL_HOST', 'smtp.gmail.com');
        $port = (int) AppConfig::get('MAIL_PORT', 587);
        $user = (string) AppConfig::get('MAIL_USERNAME', '');
        $pass = (string) AppConfig::get('MAIL_PASSWORD', '');
        $from = (string) AppConfig::get('MAIL_FROM_ADDRESS', 'ppic@duniakimia.co.id');
        $fromName = (string) AppConfig::get('MAIL_FROM_NAME', 'PPIC Alert');

        $status = 'simulated';
        $errorMsg = null;

        // If credentials are provided, attempt real SMTP transmission
        if ($user !== '' && $pass !== '') {
            try {
                self::sendSmtp($host, $port, $user, $pass, $from, $fromName, $recipients, $subject, $html);
                $status = 'sent';
            } catch (\Throwable $e) {
                $status = 'failed';
                $errorMsg = $e->getMessage();
            }
        }

        // Save log to email_notification_logs
        Database::insert('email_notification_logs', [
            'recipients' => $recipients,
            'subject' => $subject,
            'critical_items_count' => count($alertItems),
            'summary_html' => $html,
            'status' => $status,
            'error_message' => $errorMsg,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Audit log
        Database::insert('audit_logs', [
            'user_id' => $_SESSION['user_id'] ?? 1,
            'action' => 'AUTO_EMAIL_STOCK_ALERT',
            'module' => 'Notification',
            'description' => "Notifikasi peringatan stok 15-30 hari otomatis terkirim ke {$recipients} (" . count($criticalUnder15) . " kritis <15 hari, " . count($warning15To30) . " waspada 15-30 hari).",
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => ($status !== 'failed'),
            'status' => $status,
            'recipients' => $recipients,
            'subject' => $subject,
            'total_alert_count' => count($alertItems),
            'critical_under_15_count' => count($criticalUnder15),
            'warning_15_30_count' => count($warning15To30),
            'items' => $alertItems,
            'html' => $html,
            'error' => $errorMsg,
            'message' => ($status === 'sent')
                ? "Email notifikasi peringatan stok 15-30 hari berhasil dikirim ke {$recipients}!"
                : "Notifikasi otomatis berhasil dibuat & tercatat ke sistem (Mode Simulasi Aktif untuk {$recipients}).",
        ];
    }

    public static function buildHtmlEmail(
        array $criticalUnder15,
        array $warning15To30,
        int $totalCount,
        ?array $earliestItem = null,
        int $reviewsDueToday = 0
    ): string {
        $renderTableRows = function(array $items) {
            $rows = '';
            foreach ($items as $item) {
                $avail = number_format((float)$item['available_stock'], 0, ',', '.');
                $usage = number_format((float)($item['daily_usage'] ?? 0), 1, ',', '.');
                $q = number_format((float)($item['recommended_q'] ?? 0), 0, ',', '.');
                $unit = htmlspecialchars($item['unit']);
                $name = htmlspecialchars($item['name']);
                $code = htmlspecialchars($item['code']);
                $supplier = htmlspecialchars($item['supplier_name'] ?? 'Supplier Terdaftar');
                $lead = (int) $item['lead_time_days'];
                $days = $item['days_of_supply'];
                $emptyDate = $item['depletion_date_formatted'] ?? date('d M Y', strtotime($item['estimated_empty_date']));
                $latestReviewDate = $item['latest_review_date'] ?? date('d M Y');
                $reviewDaysLeft = $item['review_days_left'] ?? 0;
                $isReviewDueToday = !empty($item['is_review_due_today']);

                $badgeBg = ($days < 15) ? '#fee2e2' : '#fef3c7';
                $badgeText = ($days < 15) ? '#991b1b' : '#92400e';

                $reviewBadgeBg = $isReviewDueToday ? '#fecaca' : (($reviewDaysLeft <= 2) ? '#fed7aa' : '#f1f5f9');
                $reviewBadgeText = $isReviewDueToday ? '#991b1b' : (($reviewDaysLeft <= 2) ? '#9a3412' : '#334155');
                $reviewLabel = $isReviewDueToday ? "⚠️ HARI INI ({$latestReviewDate})" : "{$latestReviewDate} ({$reviewDaysLeft} hr lagi)";

                $rows .= <<<ROW
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px; font-weight: bold; color: #1e293b;">
                        {$name}<br>
                        <span style="font-family: monospace; font-size: 11px; color: #64748b;">{$code}</span>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #0f172a; font-family: monospace;">
                        <strong>{$avail}</strong> {$unit}<br>
                        <span style="font-size: 10px; color: #64748b;">Laju: {$usage} {$unit}/hr</span>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background-color: {$badgeBg}; color: {$badgeText}; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; font-family: monospace;">
                            {$emptyDate}
                        </span><br>
                        <span style="font-size: 11px; font-weight: 600; color: {$badgeText};">Sisa {$days} Hari</span>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background-color: {$reviewBadgeBg}; color: {$reviewBadgeText}; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; font-family: monospace;">
                            {$reviewLabel}
                        </span><br>
                        <span style="font-size: 10px; color: #64748b;">Lead Time: {$lead} hr</span>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #2563eb; font-weight: bold; font-family: monospace;">
                        {$q} {$unit}<br>
                        <span style="font-size: 10px; color: #64748b; font-weight: normal;">{$supplier}</span>
                    </td>
                </tr>
ROW;
            }
            return $rows;
        };

        $rowsUnder15 = $renderTableRows($criticalUnder15);
        $rows15To30 = $renderTableRows($warning15To30);
        $dateStr = date('d F Y, H:i') . ' WIB';
        $appUrl = AppConfig::get('APP_URL', 'http://127.0.0.1:8000');
        $countCrit = count($criticalUnder15);
        $countWarn = count($warning15To30);

        $earliestDate = $earliestItem['depletion_date_formatted'] ?? (!empty($criticalUnder15[0]['depletion_date_formatted']) ? $criticalUnder15[0]['depletion_date_formatted'] : date('d M Y', strtotime('+4 days')));
        $earliestDays = $earliestItem['days_of_supply'] ?? (!empty($criticalUnder15[0]['days_of_supply']) ? $criticalUnder15[0]['days_of_supply'] : 3.4);
        $earliestItemName = $earliestItem['name'] ?? (!empty($criticalUnder15[0]['name']) ? $criticalUnder15[0]['name'] : 'Bahan Kimia Teknis');
        $earliestReviewDate = $earliestItem['latest_review_date'] ?? date('d M Y');

        $urgentReviewBox = ($reviewsDueToday > 0) ? <<<BOX
        <div style="background-color: #fef2f2; border: 1px solid #f87171; border-left: 5px solid #dc2626; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="font-weight: bold; color: #991b1b; font-size: 14px; margin-bottom: 4px;">
                🚨 TINJAU RENCANA KEBUTUHAN HARI INI: {$reviewsDueToday} Bahan Baku
            </div>
            <div style="font-size: 12px; color: #7f1d1d; line-height: 1.4;">
                Bahan baku <strong>{$earliestItemName}</strong> dan bahan lainnya diproyeksikan habis mulai tanggal <strong>{$earliestDate} (sisa {$earliestDays} hari)</strong>. Tinjau rekomendasi AI dan rencana produksi dengan mempertimbangkan lead time acuan.
            </div>
        </div>
BOX : '';

        $sectionUnder15 = !empty($criticalUnder15) ? <<<SEC1
        <div style="margin-bottom: 24px;">
            <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 10px 14px; font-weight: bold; color: #991b1b; font-size: 13px; margin-bottom: 8px; border-radius: 4px;">
                🔴 ZONA KRITIS: Diproyeksikan Habis dalam &lt; 15 Hari ({$countCrit} Bahan Baku)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
                <thead>
                    <tr style="background-color: #f8fafc; text-align: left; color: #475569; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 8px 10px;">Bahan Baku</th>
                        <th style="padding: 8px 10px; text-align: right;">Stok Bebas &amp; Laju</th>
                        <th style="padding: 8px 10px; text-align: center;">Estimasi Habis (AI)</th>
                        <th style="padding: 8px 10px; text-align: center;">Waktu Tindak Lanjut</th>
                        <th style="padding: 8px 10px; text-align: right;">Rekomendasi (Q)</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsUnder15}
                </tbody>
            </table>
        </div>
SEC1 : '';

        $section15To30 = !empty($warning15To30) ? <<<SEC2
        <div style="margin-bottom: 24px;">
            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 10px 14px; font-weight: bold; color: #92400e; font-size: 13px; margin-bottom: 8px; border-radius: 4px;">
                🟡 ZONA WASPADA: Diproyeksikan Habis dalam 15 – 30 Hari ({$countWarn} Bahan Baku)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
                <thead>
                    <tr style="background-color: #f8fafc; text-align: left; color: #475569; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 8px 10px;">Bahan Baku</th>
                        <th style="padding: 8px 10px; text-align: right;">Stok Bebas &amp; Laju</th>
                        <th style="padding: 8px 10px; text-align: center;">Estimasi Habis (AI)</th>
                        <th style="padding: 8px 10px; text-align: center;">Waktu Tindak Lanjut</th>
                        <th style="padding: 8px 10px; text-align: right;">Rekomendasi (Q)</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows15To30}
                </tbody>
            </table>
        </div>
SEC2 : '';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Proyeksi Forecast AI Tanggal Habis Stok</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b;">
            <div style="max-width: 760px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 24px; color: #ffffff; text-align: left; border-bottom: 4px solid #dc2626;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px;">
                        PT DUNIA KIMIA JAYA GRESIK &bull; PPIC &amp; AI PREDICTIVE CONTROL
                    </div>
                    <h2 style="margin: 0; font-size: 20px; font-weight: bold; color: #ffffff;">
                        📊 EARLY WARNING: PROYEKSI KETERSEDIAAN STOK
                    </h2>
                    <div style="font-size: 12px; color: #cbd5e1; margin-top: 6px;">
                        Model Forecast Tanggal: {$dateStr} &bull; Total Teridentifikasi: {$totalCount} Bahan Baku
                    </div>
                </div>

                <!-- Body Content -->
                <div style="padding: 24px;">
                    <!-- Key Highlight Metric Card -->
                    <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px; margin-bottom: 20px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <tr>
                                <td style="width: 33%; padding: 6px 12px; border-right: 1px solid #e2e8f0;">
                                    <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">📅 Habis Terdekat (AI)</div>
                                    <div style="font-size: 16px; font-weight: bold; color: #b91c1c; margin-top: 2px;">{$earliestDate}</div>
                                    <div style="font-size: 11px; color: #dc2626; font-weight: bold;">(Tersisa {$earliestDays} Hari Lagi)</div>
                                </td>
                                <td style="width: 33%; padding: 6px 12px; border-right: 1px solid #e2e8f0;">
                                    <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">⚡ Waktu Tindak Lanjut</div>
                                    <div style="font-size: 16px; font-weight: bold; color: #c2410c; margin-top: 2px;">{$earliestReviewDate}</div>
                                    <div style="font-size: 11px; color: #ea580c;">Bahan: {$earliestItemName}</div>
                                </td>
                                <td style="width: 33%; padding: 6px 12px;">
                                    <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">📦 Rincian Kategori</div>
                                    <div style="font-size: 16px; font-weight: bold; color: #1e293b; margin-top: 2px;">{$totalCount} Bahan Menipis</div>
                                    <div style="font-size: 11px; color: #475569;">{$countCrit} Kritis (&lt;15 hr) &bull; {$countWarn} Waspada</div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    {$urgentReviewBox}

                    <p style="font-size: 13px; line-height: 1.5; color: #334155; margin-top: 0;">
                        Kepada Yth. <strong>Tim PPIC PT Dunia Kimia Jaya Gresik</strong>,
                    </p>
                    <p style="font-size: 13px; line-height: 1.5; color: #334155;">
                        Berdasarkan pemodelan laju konsumsi aktual, berikut proyeksi ketersediaan stok dan waktu yang disarankan untuk meninjau kebutuhan produksi:
                    </p>

                    <!-- Sections -->
                    {$sectionUnder15}
                    {$section15To30}

                    <!-- Button Action -->
                    <div style="text-align: center; margin: 24px 0 16px 0;">
                        <a href="{$appUrl}/ai/forecast" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; font-weight: bold; font-size: 14px; border-radius: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.3);">
                            Buka Analisis AI &amp; Tinjau Rekomendasi &rarr;
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <div style="background-color: #f8fafc; padding: 16px 24px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b; text-align: center;">
                    Notifikasi Proyeksi Kebutuhan ini dihitung secara real-time oleh <strong>Sistem PPIC &bull; PT Dunia Kimia Jaya Gresik</strong>.<br>
                    Rumus: Sisa Hari = Stok Bebas / Laju Konsumsi &bull; Waktu Tindak Lanjut = Tanggal Habis - Lead Time Acuan.
                </div>
            </div>
        </body>
        </html>
HTML;
    }

    public static function sendDivisionActivityAlert(
        string $divisionSource,
        string $type,
        string $title,
        string $message,
        string $userName,
        string $link = '/'
    ): array {
        $enabled = filter_var(AppConfig::get('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
        $host = AppConfig::get('MAIL_HOST', 'smtp.gmail.com');
        $port = (int) AppConfig::get('MAIL_PORT', 587);
        $user = AppConfig::get('MAIL_USERNAME', '');
        $pass = AppConfig::get('MAIL_PASSWORD', '');
        $from = AppConfig::get('MAIL_FROM_ADDRESS', 'ppic.alert@duniakimia.co.id');
        $fromName = AppConfig::get('MAIL_FROM_NAME', 'PPIC PT Dunia Kimia Jaya');
        $recipients = AppConfig::get('MAIL_ALERT_RECIPIENTS', 'riyo.nurmalaya22@student.uisi.ac.id');

        $divBadgeColor = match($divisionSource) {
            'PPIC' => '#ef4444',
            'Warehouse' => '#0ea5e9',
            'QC' => '#a855f7',
            'RD' => '#f59e0b',
            default => '#3b82f6'
        };

        $subject = "[NOTIFIKASI DIVISI {$divisionSource}] {$title} — PT Dunia Kimia Jaya";
        $now = date('d F Y, H:i');

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 20px; }
                .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                .header { background: #0f172a; color: #ffffff; padding: 22px 25px; border-bottom: 4px solid {$divBadgeColor}; }
                .body { padding: 25px; line-height: 1.6; }
                .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #ffffff; background-color: {$divBadgeColor}; }
                .alert-box { background: #f8fafc; border-left: 4px solid {$divBadgeColor}; padding: 15px; margin: 18px 0; border-radius: 4px; border: 1px solid #e2e8f0; border-left-width: 4px; }
                .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px; margin-top: 15px; }
                .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 15px 25px; font-size: 12px; color: #64748b; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <span class="badge">Aktivitas Divisi {$divisionSource}</span>
                    <h2 style="margin: 8px 0 0 0; font-size: 20px; color: #ffffff;">{$title}</h2>
                    <small style="color: #94a3b8;">Sistem Koordinasi Terpadu &bull; PT Dunia Kimia Jaya Gresik</small>
                </div>
                <div class="body">
                    <p style="margin-top: 0;">Halo Tim PPIC dan R&amp;D,</p>
                    <p>Telah tercatat pembaruan data yang dapat menjadi bahan tinjauan rencana produksi dan analisis:</p>
                    <div class="alert-box">
                        <strong style="color: #0f172a; font-size: 15px;">Keterangan Aktivitas:</strong>
                        <p style="margin: 8px 0 10px 0; font-size: 14px; color: #334155;">{$message}</p>
                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 10px 0;">
                        <small style="color: #64748b;">
                            <strong>User Penginput:</strong> {$userName} &bull; 
                            <strong>Divisi:</strong> {$divisionSource} &bull; 
                            <strong>Waktu:</strong> {$now} WIB
                        </small>
                    </div>
                    <p style="font-size: 13px; color: #475569; margin-bottom: 5px;">
                        Tinjau informasi ini bersama rekomendasi AI dan data stok aktual sebelum memperbarui rencana produksi.
                    </p>
                    <a href="http://127.0.0.1:8000{$link}" class="btn">Buka Halaman Terkait di PPIC &rarr;</a>
                </div>
                <div class="footer">
                    Email ini dikirim otomatis oleh PPIC &amp; AI Forecasting Engine &bull; PT Dunia Kimia Jaya Gresik
                </div>
            </div>
        </body>
        </html>
        HTML;

        if ($enabled && !empty($user) && !empty($pass)) {
            try {
                self::sendSmtp($host, $port, $user, $pass, $from, $fromName, $recipients, $subject, $html);
                Database::insert('email_notification_logs', [
                    'recipients' => $recipients,
                    'subject' => $subject,
                    'critical_items_count' => 1,
                    'summary_html' => $html,
                    'status' => 'sent',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return ['success' => true, 'message' => "Email notifikasi berhasil dikirim ke {$recipients}."];
            } catch (\Throwable $e) {
                Database::insert('email_notification_logs', [
                    'recipients' => $recipients,
                    'subject' => $subject,
                    'critical_items_count' => 1,
                    'summary_html' => $html,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => false, 'error' => 'MAIL_ENABLED tidak aktif atau kredensial SMTP belum lengkap.'];
    }

    private static function sendSmtp(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $from,
        string $fromName,
        string $recipients,
        string $subject,
        string $html
    ): void {
        $timeout = 15;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new RuntimeException("Tidak dapat terhubung ke SMTP server {$host}:{$port} ({$errstr})");
        }

        $readResp = function() use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        $sendCommand = function(string $cmd, ?string $expectedPrefix = null) use ($socket, $readResp): string {
            fputs($socket, $cmd . "\r\n");
            $resp = $readResp();
            if ($expectedPrefix !== null && !str_starts_with($resp, $expectedPrefix)) {
                throw new RuntimeException("SMTP Command [{$cmd}] gagal. Respon server: " . trim($resp));
            }
            return $resp;
        };

        // 1. Banner
        $banner = $readResp();
        if (!str_starts_with($banner, '220')) {
            throw new RuntimeException("Koneksi ditolak banner SMTP: " . trim($banner));
        }

        // 2. Initial EHLO
        $sendCommand("EHLO localhost", '250');

        // 3. STARTTLS if port 587
        if ($port === 587) {
            $sendCommand("STARTTLS", '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("Gagal mengaktifkan enkripsi TLS pada koneksi SMTP.");
            }
            $sendCommand("EHLO localhost", '250');
        }

        // 4. AUTH LOGIN
        $sendCommand("AUTH LOGIN", '334');
        $sendCommand(base64_encode($user), '334');

        // Bersihkan spasi dari Google App Password jika ada
        $cleanPass = str_replace(' ', '', $pass);
        $authResp = $sendCommand(base64_encode($cleanPass));
        if (!str_starts_with($authResp, '235')) {
            throw new RuntimeException("Autentikasi gagal (Username atau App Password salah): " . trim($authResp));
        }

        // 5. MAIL FROM (Gunakan alamat akun pengirim terotentikasi agar tidak di-drop oleh Google)
        $envelopeFrom = (!empty($user) && filter_var($user, FILTER_VALIDATE_EMAIL)) ? $user : $from;
        $sendCommand("MAIL FROM: <{$envelopeFrom}>", '250');

        // 6. RCPT TO
        $recipientList = array_map('trim', explode(',', $recipients));
        $validRcpt = 0;
        foreach ($recipientList as $rcpt) {
            if ($rcpt !== '') {
                $sendCommand("RCPT TO: <{$rcpt}>", '250');
                $validRcpt++;
            }
        }
        if ($validRcpt === 0) {
            throw new RuntimeException("Tidak ada alamat email penerima yang valid.");
        }

        // 7. DATA
        $sendCommand("DATA", '354');

        // 8. Headers & Body
        $displayFrom = "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$envelopeFrom}>";
        $headers = "From: {$displayFrom}\r\n";
        $headers .= "Reply-To: <{$from}>\r\n";
        $headers .= "To: {$recipients}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "X-Mailer: PPIC-Mailer\r\n";

        // Normalisasi format line-ending ke CRLF untuk kepatuhan RFC SMTP
        $cleanHtml = str_replace(["\r\n", "\r", "\n"], "\r\n", $html);
        $body = $headers . "\r\n" . $cleanHtml . "\r\n.\r\n";

        fputs($socket, $body);
        $dataResp = $readResp();
        if (!str_starts_with($dataResp, '250')) {
            throw new RuntimeException("Pengiriman isi email gagal. Respon server: " . trim($dataResp));
        }

        // 9. QUIT
        $sendCommand("QUIT");
        @fclose($socket);
    }
}
