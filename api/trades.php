<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        respond(['error' => 'Invalid JSON body.'], 400);
    }

    return $decoded;
}

function normalizeTradeInput(array $input): array
{
    $asset = trim((string)($input['asset'] ?? ''));
    $type = strtolower(trim((string)($input['type'] ?? '')));
    $quantity = (float)($input['quantity'] ?? 0);
    $price = (float)($input['price'] ?? 0);
    $tradeDate = trim((string)($input['trade_date'] ?? ''));

    if ($asset === '') {
        respond(['error' => 'Asset is required.'], 422);
    }

    if (!in_array($type, ['buy', 'sell'], true)) {
        respond(['error' => 'Type must be buy or sell.'], 422);
    }

    if ($quantity <= 0 || $price <= 0) {
        respond(['error' => 'Quantity and price must be greater than zero.'], 422);
    }

    $dt = DateTime::createFromFormat('Y-m-d', $tradeDate);
    if (!$dt || $dt->format('Y-m-d') !== $tradeDate) {
        respond(['error' => 'trade_date must be in YYYY-MM-DD format.'], 422);
    }

    return [
        'asset' => $asset,
        'type' => $type,
        'quantity' => $quantity,
        'price' => $price,
        'trade_date' => $tradeDate,
    ];
}

function getPaginationInput(): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? 10)));
    return [
        'page' => $page,
        'page_size' => $pageSize,
        'offset' => ($page - 1) * $pageSize,
    ];
}

function buildFilterParts(int $userId): array
{
    $asset = trim((string)($_GET['asset'] ?? ''));
    $fromDate = trim((string)($_GET['from_date'] ?? ''));
    $toDate = trim((string)($_GET['to_date'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));

    $where = ['user_id = :userId'];
    $params = ['userId' => $userId];

    if ($asset !== '') {
        $where[] = 'asset = :asset';
        $params['asset'] = $asset;
    }
    if ($fromDate !== '') {
        $where[] = 'trade_date >= :fromDate';
        $params['fromDate'] = $fromDate;
    }
    if ($toDate !== '') {
        $where[] = 'trade_date <= :toDate';
        $params['toDate'] = $toDate;
    }
    if ($search !== '') {
        $where[] = '(asset LIKE :search OR type LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    return [
        'where_sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function fetchTrades(PDO $pdo, int $userId, bool $ignorePagination = false): array
{
    $filters = buildFilterParts($userId);
    $pagination = getPaginationInput();

    $sql = 'SELECT id, asset, type, quantity, price, trade_date, created_at, updated_at
            FROM trades
            WHERE ' . $filters['where_sql'] . '
            ORDER BY trade_date ASC, id ASC';

    if (!$ignorePagination) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($filters['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    if (!$ignorePagination) {
        $stmt->bindValue(':limit', $pagination['page_size'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $trades = $stmt->fetchAll();

    return array_map(static function (array $trade): array {
        $trade['quantity'] = (float)$trade['quantity'];
        $trade['price'] = (float)$trade['price'];
        $trade['total'] = $trade['quantity'] * $trade['price'];
        return $trade;
    }, $trades);
}

function getTotalTradeCount(PDO $pdo, int $userId): int
{
    $filters = buildFilterParts($userId);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total_count FROM trades WHERE ' . $filters['where_sql']);
    $stmt->execute($filters['params']);
    $row = $stmt->fetch();
    return (int)($row['total_count'] ?? 0);
}

function getProfitSeries(array $trades): array
{
    $runningProfit = 0.0;
    $series = [];

    foreach ($trades as $trade) {
        $amount = (float)$trade['total'];
        if ($trade['type'] === 'buy') {
            $runningProfit -= $amount;
        } else {
            $runningProfit += $amount;
        }

        $series[] = [
            'trade_date' => $trade['trade_date'],
            'profit' => $runningProfit,
        ];
    }

    return $series;
}

function getRealizedPlByAsset(array $allTrades): array
{
    $assets = [];
    foreach ($allTrades as $trade) {
        $asset = (string)$trade['asset'];
        if (!isset($assets[$asset])) {
            $assets[$asset] = [
                'asset' => $asset,
                'position_qty' => 0.0,
                'position_cost' => 0.0,
                'realized_pl' => 0.0,
                'sold_qty' => 0.0,
            ];
        }

        $qty = (float)$trade['quantity'];
        $price = (float)$trade['price'];
        $value = $qty * $price;

        if ($trade['type'] === 'buy') {
            $assets[$asset]['position_qty'] += $qty;
            $assets[$asset]['position_cost'] += $value;
            continue;
        }

        $currentQty = (float)$assets[$asset]['position_qty'];
        $currentCost = (float)$assets[$asset]['position_cost'];
        $avgCost = $currentQty > 0 ? ($currentCost / $currentQty) : 0.0;
        $matchedQty = min($qty, $currentQty);
        $matchedCost = $matchedQty * $avgCost;
        $realized = ($matchedQty * $price) - $matchedCost;

        $assets[$asset]['realized_pl'] += $realized;
        $assets[$asset]['sold_qty'] += $qty;
        $assets[$asset]['position_qty'] = max(0.0, $currentQty - $matchedQty);
        $assets[$asset]['position_cost'] = max(0.0, $currentCost - $matchedCost);
    }

    return array_values(array_map(static function (array $asset): array {
        return [
            'asset' => $asset['asset'],
            'sold_qty' => round((float)$asset['sold_qty'], 8),
            'open_qty' => round((float)$asset['position_qty'], 8),
            'realized_pl' => round((float)$asset['realized_pl'], 2),
        ];
    }, $assets));
}

try {
    $pdo = getDbConnection();
    $userId = requireAuthUserId();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $trades = fetchTrades($pdo, $userId, true);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="trades_export.csv"');

            $output = fopen('php://output', 'wb');
            fputcsv($output, ['id', 'asset', 'type', 'quantity', 'price', 'total', 'trade_date', 'created_at', 'updated_at']);
            foreach ($trades as $trade) {
                fputcsv($output, [
                    $trade['id'],
                    $trade['asset'],
                    $trade['type'],
                    $trade['quantity'],
                    $trade['price'],
                    $trade['total'],
                    $trade['trade_date'],
                    $trade['created_at'],
                    $trade['updated_at'],
                ]);
            }
            fclose($output);
            exit;
        }

        $trades = fetchTrades($pdo, $userId, false);
        $allFilteredTrades = fetchTrades($pdo, $userId, true);
        $totalCount = getTotalTradeCount($pdo, $userId);
        $pagination = getPaginationInput();
        $totals = [
            'buy_total' => 0.0,
            'sell_total' => 0.0,
            'net_profit' => 0.0,
        ];

        foreach ($trades as $trade) {
            if ($trade['type'] === 'buy') {
                $totals['buy_total'] += (float)$trade['total'];
            } else {
                $totals['sell_total'] += (float)$trade['total'];
            }
        }
        $totals['net_profit'] = $totals['sell_total'] - $totals['buy_total'];

        respond([
            'trades' => $trades,
            'totals' => $totals,
            'profit_series' => getProfitSeries($allFilteredTrades),
            'realized_pl_by_asset' => getRealizedPlByAsset($allFilteredTrades),
            'pagination' => [
                'page' => $pagination['page'],
                'page_size' => $pagination['page_size'],
                'total_count' => $totalCount,
                'total_pages' => (int)max(1, ceil($totalCount / $pagination['page_size'])),
            ],
        ]);
    }

    if ($method === 'POST') {
        $input = normalizeTradeInput(parseJsonBody());
        $stmt = $pdo->prepare('
            INSERT INTO trades (user_id, asset, type, quantity, price, trade_date)
            VALUES (:user_id, :asset, :type, :quantity, :price, :trade_date)
        ');
        $input['user_id'] = $userId;
        $stmt->execute($input);
        respond(['message' => 'Trade created successfully.'], 201);
    }

    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            respond(['error' => 'Valid id is required for update.'], 422);
        }

        $input = normalizeTradeInput(parseJsonBody());
        $input['id'] = $id;

        $stmt = $pdo->prepare('
            UPDATE trades
            SET asset = :asset,
                type = :type,
                quantity = :quantity,
                price = :price,
                trade_date = :trade_date
            WHERE id = :id AND user_id = :user_id
        ');
        $input['user_id'] = $userId;
        $stmt->execute($input);

        if ($stmt->rowCount() === 0) {
            respond(['error' => 'Trade not found.'], 404);
        }

        respond(['message' => 'Trade updated successfully.']);
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            respond(['error' => 'Valid id is required for delete.'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM trades WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            respond(['error' => 'Trade not found.'], 404);
        }

        respond(['message' => 'Trade deleted successfully.']);
    }

    respond(['error' => 'Method not allowed.'], 405);
} catch (Throwable $error) {
    respond(['error' => $error->getMessage()], 500);
}
