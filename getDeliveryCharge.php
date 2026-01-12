<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api/shiprocket.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {

    /* ----------------------------------------------------
       1. Fetch pickup pincode
    ---------------------------------------------------- */
    $stmt = $conn->query("SELECT pickuplocation_pincode FROM settings LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $pickupPincode = trim($row['pickuplocation_pincode'] ?? '');
    if ($pickupPincode === '') {
        $pickupPincode = '625005';
    }

    /* ----------------------------------------------------
       2. Inputs
    ---------------------------------------------------- */
    $deliveryPincode = trim($_POST['pincode'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0.5);
    $isCOD = intval($_POST['cod'] ?? 0);

    if ($deliveryPincode === '') {
        throw new Exception("Delivery pincode missing");
    }

    /* ----------------------------------------------------
       3. Call Shiprocket serviceability API (GET)
    ---------------------------------------------------- */
    $shiprocket = new Shiprocket($conn);
    $response = $shiprocket->request(
        'GET',
        '/courier/serviceability/',
        [
            'pickup_postcode'   => $pickupPincode,
            'delivery_postcode' => $deliveryPincode,
            'weight'            => $weight,
            'length'            => 10,
            'breadth'           => 10,
            'height'            => 5,
            'cod'               => $isCOD
        ]
    );

    $couriers = $response['data']['available_courier_companies'] ?? [];

    if (empty($couriers)) {
        throw new Exception("No courier available for this pincode");
    }

    /* ----------------------------------------------------
       4. Get Shiprocket recommended courier ID
    ---------------------------------------------------- */
    $recommendedCourierId =
        $response['data']['shiprocket_recommended_courier_id']
        ?? $response['data']['recommended_courier_company_id']
        ?? null;

    $selectedCourier = null;

    /* ----------------------------------------------------
       5. FIRST: Try Shiprocket recommended courier
    ---------------------------------------------------- */
    if ($recommendedCourierId) {
        foreach ($couriers as $courier) {
            if ((int)$courier['courier_company_id'] === (int)$recommendedCourierId) {
                $selectedCourier = $courier;
                break;
            }
        }
    }

    /* ----------------------------------------------------
       6. FALLBACK: Pick cheapest courier
    ---------------------------------------------------- */
    if (!$selectedCourier) {
        $lowestRate = PHP_FLOAT_MAX;

        foreach ($couriers as $courier) {
            if (!empty($courier['blocked'])) continue;

            $rate = floatval($courier['rate'] ?? 0);
            $codCharges = $isCOD ? floatval($courier['cod_charges'] ?? 0) : 0;
            $finalRate = $rate + $codCharges;

            if ($finalRate > 0 && $finalRate < $lowestRate) {
                $lowestRate = $finalRate;
                $selectedCourier = $courier;
            }
        }
    }

    if (!$selectedCourier) {
        throw new Exception("Unable to determine courier");
    }

    /* ----------------------------------------------------
       7. Final price calculation
    ---------------------------------------------------- */
    $rate = floatval($selectedCourier['rate'] ?? 0);
    $codCharges = $isCOD ? floatval($selectedCourier['cod_charges'] ?? 0) : 0;
    $finalRate = round($rate + $codCharges, 2);

    /* ----------------------------------------------------
       8. Send FINAL response
    ---------------------------------------------------- */
    echo json_encode([
        'success' => true,
        'courier_id' => $selectedCourier['courier_company_id'],
        'courier_name' => $selectedCourier['courier_name'],
        'rate' => $finalRate,
        'estimated_delivery_days' => $selectedCourier['estimated_delivery_days'] ?? '',
        'etd' => $selectedCourier['etd'] ?? '',
        'is_shiprocket_recommended' => (
            $recommendedCourierId &&
            (int)$selectedCourier['courier_company_id'] === (int)$recommendedCourierId
        )
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
