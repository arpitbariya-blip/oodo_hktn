<?php
class ReportController {
    
    public function getDashboard() {
        try {
            $db = Database::getConnection();

            // 1. KPIs
            // Total Asset Value (excluding disposed)
            $stmt = $db->query("SELECT SUM(acquisition_cost) as total_value FROM assets WHERE lifecycle_status != 'Disposed'");
            $totalValue = $stmt->fetchColumn() ?: 0;

            // Active Utilization (% of allocated/reserved out of all available/allocated/reserved)
            $stmt = $db->query("SELECT lifecycle_status, COUNT(*) as cnt FROM assets GROUP BY lifecycle_status");
            $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $available = $statusCounts['Available'] ?? 0;
            $allocated = $statusCounts['Allocated'] ?? 0;
            $reserved = $statusCounts['Reserved'] ?? 0;
            $underMaint = $statusCounts['Under Maintenance'] ?? 0;
            $idle = $available;
            
            $utilizationBase = $available + $allocated + $reserved;
            $utilizationPct = $utilizationBase > 0 ? round((($allocated + $reserved) / $utilizationBase) * 100, 1) : 0;

            // Maintenance Costs (Mocked for now since we don't have cost in maintenance table)
            // We'll base it off the number of maintenance requests to make it dynamic
            $stmt = $db->query("SELECT COUNT(*) FROM maintenance_requests");
            $maintCount = $stmt->fetchColumn();
            $maintCosts = $maintCount * 1250; // $1250 per request avg

            // 2. Department Allocation
            $stmt = $db->query("
                SELECT d.name as department_name, COUNT(a.id) as asset_count
                FROM assets a
                JOIN departments d ON a.department_id = d.id
                WHERE a.lifecycle_status IN ('Allocated', 'Reserved')
                GROUP BY d.id
            ");
            $deptAllocation = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Maintenance Frequency by Category
            $stmt = $db->query("
                SELECT c.name as category_name, COUNT(mr.id) as request_count
                FROM maintenance_requests mr
                JOIN assets a ON mr.asset_id = a.id
                JOIN asset_categories c ON a.category_id = c.id
                GROUP BY c.id
            ");
            $maintFrequency = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Utilization Trends (Mocked last 6 months for chart)
            $months = [];
            for($i=5; $i>=0; $i--) {
                $months[] = date('M Y', strtotime("-$i months"));
            }
            $utilTrends = [
                'labels' => $months,
                'data' => [65, 68, 72, 70, 78, $utilizationPct]
            ];

            // 5. Due for Maintenance
            $stmt = $db->query("
                SELECT a.id, a.name, a.asset_tag, a.location 
                FROM assets a 
                WHERE a.lifecycle_status = 'Under Maintenance' 
                OR a.id IN (SELECT asset_id FROM maintenance_requests WHERE status = 'Scheduled')
                LIMIT 5
            ");
            $dueMaint = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. Most Used vs Idle (Top 5 booked assets vs 5 assets never booked/allocated)
            $stmt = $db->query("
                SELECT a.name, a.asset_tag, COUNT(b.id) as usage_count
                FROM assets a
                JOIN bookings b ON a.id = b.asset_id
                GROUP BY a.id ORDER BY usage_count DESC LIMIT 5
            ");
            $mostUsed = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->query("
                SELECT a.name, a.asset_tag
                FROM assets a
                LEFT JOIN bookings b ON a.id = b.asset_id
                LEFT JOIN allocations al ON a.id = al.asset_id
                WHERE b.id IS NULL AND al.id IS NULL
                LIMIT 5
            ");
            $idleList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json([
                'kpis' => [
                    'totalValue' => $totalValue,
                    'utilizationPct' => $utilizationPct,
                    'idleAssets' => $idle,
                    'maintCosts' => $maintCosts
                ],
                'departmentAllocation' => $deptAllocation,
                'maintenanceFrequency' => $maintFrequency,
                'utilTrends' => $utilTrends,
                'dueMaintenance' => $dueMaint,
                'mostUsed' => $mostUsed,
                'idleList' => $idleList

            ]);

        } catch(PDOException $e) {
            Response::error('Database error: ' , 500);
        }
    }

    public function export() {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("
                SELECT a.asset_tag, a.name, c.name as category, d.name as department, a.lifecycle_status, a.acquisition_cost
                FROM assets a
                LEFT JOIN asset_categories c ON a.category_id = c.id
                LEFT JOIN departments d ON a.department_id = d.id
                ORDER BY a.id ASC
            ");
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="asset_report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Asset Tag', 'Name', 'Category', 'Department', 'Status', 'Cost']);
            
            foreach ($assets as $row) {
                fputcsv($output, [
                    $row['asset_tag'],
                    $row['name'],
                    $row['category'] ?: 'Uncategorized',
                    $row['department'] ?: 'Unassigned',
                    $row['lifecycle_status'],
                    $row['acquisition_cost']
                ]);
            }
            fclose($output);
            exit;
            
        } catch(PDOException $e) {
            echo "Error generating report.";
        }
    }
}
