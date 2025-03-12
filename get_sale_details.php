<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['invoice_id'])) {
        throw new Exception('Invoice ID is required');
    }

    $invoice_id = (int)$_GET['invoice_id'];

    // Get sale header information
    $stmt = $pdo->prepare("
        SELECT 
            s.invoice_id,
            s.invoice_date,
            s.total_amount,
            s.discount_id,
            s.net_total,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            d.discount_amount
        FROM sales s
        LEFT JOIN customer c ON s.customer_id = c.customer_id
        LEFT JOIN discount d ON s.discount_id = d.discount_id
        WHERE s.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception('Sale not found');
    }

    // Get sale items
    $stmt = $pdo->prepare("
        SELECT 
            sd.quantity,
            sd.unit_price,
            sd.subtotal,
            p.product_name
        FROM sales_details sd
        JOIN product p ON sd.product_id = p.product_id
        WHERE sd.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = array_merge($sale, ['items' => $items]);
    $response['invoice_date'] = date('F j, Y', strtotime($response['invoice_date']));

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 