<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Database\Database;
use App\Services\InventoryService;
use App\Services\ProcurementService;

class PurchasingController extends Controller
{
    public static function prIndex(): array
    {
        $prs = Database::select("
            SELECT pr.*, u1.name as requester_name, u2.name as approver_name,
                   (SELECT COUNT(*) FROM purchase_requisition_items WHERE pr_id = pr.id) as item_count
            FROM purchase_requisitions pr
            LEFT JOIN users u1 ON u1.id = pr.requested_by
            LEFT JOIN users u2 ON u2.id = pr.approved_by
            ORDER BY pr.id DESC
        ");

        $materials = Database::select("SELECT * FROM materials ORDER BY name ASC");

        return [
            'prs' => $prs,
            'materials' => $materials,
        ];
    }

    public static function prShow(int $id): array
    {
        $pr = Database::selectOne("
            SELECT pr.*, u1.name as requester_name, u2.name as approver_name
            FROM purchase_requisitions pr
            LEFT JOIN users u1 ON u1.id = pr.requested_by
            LEFT JOIN users u2 ON u2.id = pr.approved_by
            WHERE pr.id = :id
        ", ['id' => $id]);

        if (!$pr) throw new \RuntimeException("PR tidak ditemukan.");

        $items = Database::select("
            SELECT pri.*, m.name as material_name, m.code as material_code, m.unit as material_unit
            FROM purchase_requisition_items pri
            JOIN materials m ON m.id = pri.material_id
            WHERE pri.pr_id = :pr_id
        ", ['pr_id' => $id]);

        return [
            'pr' => $pr,
            'items' => $items,
        ];
    }

    public static function createPrManual(array $data, int $userId): int
    {
        Database::beginTransaction();
        try {
            $materialId = (int) $data['material_id'];
            $qty = (float) $data['quantity'];
            $notes = trim($data['notes'] ?? 'Pengajuan manual');
            $priority = $data['priority'] ?? 'normal';

            $mat = Database::selectOne("SELECT * FROM materials WHERE id = :id", ['id' => $materialId]);
            if (!$mat) throw new \RuntimeException("Material tidak ditemukan.");

            $prNumber = 'PR-' . date('Ym') . '-' . str_pad((string) (Database::selectOne("SELECT COUNT(*) as c FROM purchase_requisitions")['c'] + 1), 3, '0', STR_PAD_LEFT);
            $cost = $qty * (float) $mat['unit_cost'];
            $reqDate = $data['required_date'] ?: date('Y-m-d', strtotime('+7 days'));

            $prId = Database::insert('purchase_requisitions', [
                'pr_number' => $prNumber,
                'source' => 'manual',
                'status' => 'submitted',
                'priority' => $priority,
                'required_date' => $reqDate,
                'total_estimated_cost' => $cost,
                'notes' => $notes,
                'requested_by' => $userId,
            ]);

            Database::insert('purchase_requisition_items', [
                'pr_id' => $prId,
                'material_id' => $materialId,
                'suggested_qty' => $qty,
                'approved_qty' => $qty,
                'unit' => $mat['unit'],
                'estimated_unit_price' => (float) $mat['unit_cost'],
                'reason' => $notes,
            ]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'CREATE_PR_MANUAL',
                'module' => 'Purchasing',
                'description' => "Membuat PR manual {$prNumber} untuk {$mat['name']} ({$qty} {$mat['unit']}).",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Database::commit();

            $userName = $_SESSION['user_name'] ?? 'User';
            $divSource = $_SESSION['user_division'] ?? 'Purchasing';
            $targetDiv = ($divSource === 'PPIC') ? 'Purchasing' : 'PPIC';
            \App\Services\NotificationService::record(
                $divSource,
                'PR_MANUAL',
                "Pengajuan PR Baru: {$prNumber}",
                "{$userName} ({$divSource}) mengajukan Purchase Requisition {$prNumber} untuk bahan {$mat['name']} sebanyak " . number_format($qty, 0, ',', '.') . " {$mat['unit']} senilai Rp " . number_format($cost, 0, ',', '.') . ". Menunggu review/approval.",
                "/purchasing/pr",
                $userId,
                $userName,
                true,
                $targetDiv
            );

            return $prId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function poIndex(): array
    {
        $pos = Database::select("
            SELECT po.*, s.name as supplier_name, s.code as supplier_code, s.phone as supplier_phone,
                   pr.pr_number, u.name as creator_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN purchase_requisitions pr ON pr.id = po.pr_id
            LEFT JOIN users u ON u.id = po.created_by
            ORDER BY po.id DESC
        ");

        $approvedPrs = Database::select("
            SELECT pr.* 
            FROM purchase_requisitions pr
            WHERE pr.status = 'approved'
            ORDER BY pr.id DESC
        ");

        return [
            'pos' => $pos,
            'approved_prs' => $approvedPrs,
        ];
    }

    public static function poShow(int $id): array
    {
        $po = Database::selectOne("
            SELECT po.*, s.name as supplier_name, s.code as supplier_code, s.phone as supplier_phone,
                   s.email as supplier_email, s.address as supplier_address,
                   pr.pr_number, u.name as creator_name
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN purchase_requisitions pr ON pr.id = po.pr_id
            LEFT JOIN users u ON u.id = po.created_by
            WHERE po.id = :id
        ", ['id' => $id]);

        if (!$po) throw new \RuntimeException("Purchase Order tidak ditemukan.");

        $items = Database::select("
            SELECT poi.*, m.name as material_name, m.code as material_code, m.unit as material_unit,
                   m.warehouse_id
            FROM purchase_order_items poi
            JOIN materials m ON m.id = poi.material_id
            WHERE poi.po_id = :po_id
        ", ['po_id' => $id]);

        $warehouses = Database::select("SELECT * FROM warehouses ORDER BY id ASC");

        return [
            'po' => $po,
            'items' => $items,
            'warehouses' => $warehouses,
        ];
    }

    public static function approvePr(int $prId, int $userId): void
    {
        ProcurementService::approvePr($prId, $userId);
        $pr = Database::selectOne("SELECT pr_number FROM purchase_requisitions WHERE id = :id", ['id' => $prId]);
        $prNo = $pr['pr_number'] ?? "PR-{$prId}";
        $userName = $_SESSION['user_name'] ?? 'Manager';
        \App\Services\NotificationService::record(
            'PPIC',
            'PR_APPROVED',
            "PR Disetujui (Approved): {$prNo}",
            "Purchase Requisition {$prNo} telah disetujui oleh {$userName}. Divisi Purchasing dapat segera menerbitkan Purchase Order (PO) resmi ke supplier.",
            "/purchasing/pr",
            $userId,
            $userName,
            true,
            'Purchasing'
        );
    }

    public static function generatePo(int $prId, int $userId): int
    {
        $poId = ProcurementService::generatePoFromPr($prId, $userId);
        $po = Database::selectOne("
            SELECT po.po_number, po.total_amount, s.name as supplier_name 
            FROM purchase_orders po 
            JOIN suppliers s ON s.id = po.supplier_id 
            WHERE po.id = :id
        ", ['id' => $poId]);
        $userName = $_SESSION['user_name'] ?? 'Purchasing';
        $poNo = $po['po_number'] ?? "PO-{$poId}";
        $suppName = $po['supplier_name'] ?? 'Supplier';
        $totalAmt = number_format((float)($po['total_amount'] ?? 0), 0, ',', '.');

        \App\Services\NotificationService::record(
            'Purchasing',
            'PO_GENERATED',
            "Penerbitan PO Resmi: {$poNo}",
            "Divisi Purchasing ({$userName}) telah menerbitkan Purchase Order resmi {$poNo} ke rekanan {$suppName} senilai Rp {$totalAmt}. Tim Gudang bersiap menerima barang.",
            "/purchasing/po/show?id={$poId}",
            $userId,
            $userName,
            true,
            ['Warehouse', 'PPIC']
        );

        return $poId;
    }

    public static function receivePo(int $poId, array $itemsData, int $userId): void
    {
        InventoryService::receivePurchaseOrder($poId, $itemsData, $userId);
        $po = Database::selectOne("SELECT po_number FROM purchase_orders WHERE id = :id", ['id' => $poId]);
        $poNo = $po['po_number'] ?? "PO-{$poId}";
        $userName = $_SESSION['user_name'] ?? 'Staf Gudang';

        \App\Services\NotificationService::record(
            'Warehouse',
            'PO_RECEIVED',
            "Barang Diterima & Update Stok PO: {$poNo}",
            "Divisi Gudang ({$userName}) telah menerima fisik barang dari PO {$poNo}. Stok fisik gudang dan data AI telah diperbarui.",
            "/inventory",
            $userId,
            $userName,
            true,
            ['PPIC', 'Purchasing']
        );
    }

    public static function deletePr(int $prId, int $userId): void
    {
        $pr = Database::selectOne("SELECT pr_number, status FROM purchase_requisitions WHERE id = :id", ['id' => $prId]);
        if (!$pr) throw new \RuntimeException("Purchase Requisition tidak ditemukan.");

        if ($pr['status'] === 'ordered') {
            throw new \RuntimeException("PR {$pr['pr_number']} sudah berstatus ORDERED (sudah terbit PO) dan tidak dapat dihapus.");
        }

        Database::beginTransaction();
        try {
            Database::statement("DELETE FROM purchase_requisition_items WHERE pr_id = :id", ['id' => $prId]);
            Database::statement("DELETE FROM purchase_requisitions WHERE id = :id", ['id' => $prId]);

            Database::insert('audit_logs', [
                'user_id' => $userId,
                'action' => 'DELETE_PR',
                'module' => 'Purchasing',
                'description' => "Menghapus Purchase Requisition: {$pr['pr_number']}.",
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
