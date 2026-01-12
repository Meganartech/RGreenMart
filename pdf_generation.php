<?php
require_once 'vendor/autoload.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


try {
 $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // --- START: CONVERTED FROM POST TO GET/DB FETCH ---
    if (!isset($_GET['order_id'])) {
        die("<p style='color: #dc2626;'>Error: Order ID missing in URL.</p>");
    }

    $orderId = intval($_GET['order_id']);

    $orderStmt = $conn->prepare("
        SELECT o.*, u.name AS customer_name, u.mobile AS customer_mobile, u.email AS customer_email,
               a.address_line1, a.address_line2, a.city, a.state, a.pincode, a.landmark
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN user_addresses a ON a.id = o.address_id
        WHERE o.id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("<p style='color: #dc2626;'>Error: Order ID $orderId not found.</p>");
    }

   // Map order/customer/address fields
    $customerName    = $order['customer_name'];
    $customerMobile  = $order['customer_mobile'];
    $customerEmail   = $order['customer_email'];
    $customerAddress = trim($order['address_line1'] . ' ' . $order['address_line2']);
    $customerCity    = $order['city'];
    $customerState   = $order['state'];
    $customerPincode = $order['pincode'];
    $customerLandmark= $order['landmark'];
    $enquiryNumber   = $order['enquiry_no'];
    $orderedDateTime = $order['order_date'];
    $shippingCharge   = $order['shipping_charge'];
    $overallTotal    = $order['overall_total'];
    $subtotal        = $order['subtotal'];

    // 2. Fetch Order Items (schema-safe: only join item_variants if order_items.variant_id exists)
  $dbName = $_ENV['DB_NAME'] ?? $conn->query('select database()')->fetchColumn();
  $colsStmt = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = 'order_items'");
  $colsStmt->execute([$dbName]);
  $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
  $hasVariantId = in_array('variant_id', $cols);

  if ($hasVariantId) {
    $itemsSql = "
        SELECT oi.*, i.name AS product_name,
               v.weight_value AS variant_weight,
               v.weight_unit AS variant_unit,
               v.price AS variant_price,
               v.old_price AS variant_old_price,
               v.discount AS variant_discount
        FROM order_items oi
        JOIN items i ON i.id = oi.item_id
        LEFT JOIN item_variants v ON oi.variant_id = v.id
        WHERE oi.order_id = ?
    ";
  } else {
    // Variant info not stored on order_items in this schema; return nulls so downstream logic keeps working
    $itemsSql = "
        SELECT oi.*, i.name AS product_name,
               NULL AS variant_weight,
               NULL AS variant_unit,
               NULL AS variant_price,
               NULL AS variant_old_price,
               NULL AS variant_discount
        FROM order_items oi
        JOIN items i ON i.id = oi.item_id
        WHERE oi.order_id = ?
    ";
  }

    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->execute([$orderId]);
    $itemsBought = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$itemsBought) {
        die("<p style='color: #dc2626;'>Error: No items found for order ID $orderId.</p>");
    }

    // ---------------- Fetch Admin ------------------
    $adminStmt = $conn->query("SELECT name, phone, email, shopaddress FROM admin_details LIMIT 1");
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    $adminName = $admin['name'] ?? 'RGreenMart';
    $adminMobile = $admin['phone'] ?? '99524 24474';
    $adminEmail = $admin['email'] ?? 'sales@rgreenmart.com';
    $adminAddress = $admin['shopaddress'] ?? 'Chandragandhi Nagar, Madurai, Tamil Nadu';

    // Fetch GST rate from the settings table
    $stmt = $conn->prepare("SELECT gst_rate FROM settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $gstRate = isset($settings['gst_rate']) ? floatval($settings['gst_rate']) : 18;

    // Fetch SMTP credentials from environment variables
    $smtpEmail = $_ENV['SMTP_MAIL'] ?? '';
    $smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';

    if (empty($smtpEmail) || empty($smtpPassword)) {
        error_log("SMTP credentials missing: SMTP_MAIL=$smtpEmail");
        echo "<p style='color: #dc2626;'>Error: SMTP credentials are not configured.</p>";
        exit;
    }
      // ---------------- CALCULATE ITEM TOTALS ----------------
  $subtotal = 0;           // sum of original prices * quantity
$netTotal = 0;            // sum of discounted price * quantity
$totalDiscountAmount = 0; // sum of total discount

$rowsHtml = "";

foreach ($itemsBought as $index => $item) {
    $originalPrice = $item['original_price'];
    $discountPerc  = $item['discount_percentage'];
    // If variant_price exists use it as unit price, otherwise use discounted_price
    $discountedPrice = $item['variant_price'] ?? $item['discounted_price'];
    $quantity = $item['quantity'];

    $discountAmount = ($originalPrice - $discountedPrice); // per unit
    $totalDiscountAmount += $discountAmount * $quantity;

    $subtotal += $originalPrice * $quantity;
    $netTotal += $discountedPrice * $quantity;

    $productDisplay = htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8');
    if (!empty($item['variant_weight'])) {
        $productDisplay .= " - " . htmlspecialchars($item['variant_weight'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($item['variant_unit'], ENT_QUOTES, 'UTF-8');
    }

    $rowsHtml .= "<tr>
        <td>" . ($index + 1) . "</td>
        <td class='left'>" . $productDisplay . "</td>
        <td>" . number_format($originalPrice, 2) . "</td>
        <td>" . number_format($discountAmount,2) . " (" . number_format($discountPerc,2) . "%)</td>
        <td>" . number_format($discountedPrice,2) . "</td>
        <td>" . $quantity . "</td>
        <td>" . number_format($discountedPrice * $quantity,2) . "</td>
    </tr>";
}

$overallTotal = $netTotal + $shippingCharge;

        // Generate HTML for PDF
        $html = "
        <html>
        <head>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; }
                .invoice-box { border: 1px solid #000; padding: 5px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #000; padding: 6px; text-align: center; }
                th { font-weight: bold; }
                .header { width: 100%; border-bottom: 1px solid #000; margin-bottom: 5px; }
                .header td { border: none; padding: 2px 5px; }
                .bold { font-weight: bold; }
                .right { text-align: right; }
                .left { text-align: left; }
                .center { text-align: center; }
                .no-border td { border: none; font-weight: bold; text-align: center !important; }
                .section-title { font-weight: bold; margin-top: 5px; margin-bottom: 0px; }
                .btn-success {
                    background: #16a34a;
                    color: #ffffff;
                    padding: 0.75rem 1.5rem;
                    border-radius: 9999px;
                    text-decoration: none;
                    font-weight: bold;
                    transition: all 0.2s;
                    display: inline-block;
                }
                .btn-success:hover {
                    background: #15803d;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                }
            </style>
            <title>Estimate Invoice - $enquiryNumber</title>
        </head>
        <body>
            <div style='margin: 30px auto; max-width: 900px; border: 2px solid #4b5563; background: #fff; padding: 20px; box-shadow: 0 2px 8px #ccc;'>
                <div class='invoice-box'>
                    <table class='header'>
                        <tr>
                            <td class='left'>Invoice No: $enquiryNumber</td>
                            <td class='right'>Date: $orderedDateTime</td>
                        </tr>
                        <tr>
                            <td class='left bold'>Mobile: $adminMobile</td>
                            <td class='right bold'>E-mail: $adminEmail</td>
                        </tr>
                        <tr>
                            <td colspan='2' style='text-align: center; font-size: 16px; font-weight: bold;'>
                                $adminName<br>
                                <span style='font-weight: normal;'>$adminAddress</span>
                            </td>
                        </tr>
                    </table>

                    <h4 style='text-align: center; margin: 0;'>Customer Details</h4>
                    <table class='no-border' style='text-align: center;'>
                        <tr><td class='left'>$customerName</td></tr>
                        <tr><td class='left'>$customerMobile</td></tr>
                        <tr><td class='left'>$customerEmail</td></tr>
                        <tr><td class='left'>$customerAddress</td></tr>
                        <tr><td class='left'>$customerCity, $customerState</td></tr>
                    </table>

                    <table>
                        <tr>
                            <th>S.No</th>
                            <th>Product Name</th>
							<th>Price (Inc. GST)</th>
                            <th>Discount (₹ / %)</th>
                            <th>Discounted Price</th>
                            <th>QTY</th>
                            <th>Amount (₹)</th>
                        </tr>
                        $rowsHtml
						<tr><td colspan='6' class='right'>Gross Total</td><td>" . number_format($subtotal, 2) . "</td></tr>
                        <tr><td colspan='6' class='right'>Discount Amount (-90%)</td><td> - " . number_format($totalDiscountAmount, 2) . "</td></tr>
                        <tr><td colspan='6' class='right bold'>Net Amount</td><td class='bold'>" . number_format($netTotal, 2) . "</td></tr>
						<tr><td colspan='6' class='right bold'>Shipping Charge </td><td class='bold'>" . number_format($shippingCharge, 2) . "</td></tr>
                        <tr><td colspan='6' class='right bold'>Overall Total</td><td class='bold'>" . number_format($overallTotal, 2) . "</td></tr>
                    </table>

                    <p class='bold'>Total Items: " . count($itemsBought) . "</p>
                </div>
                <div style='text-align: center; margin: 20px;'>
                 <a href='/bills/estimate_$enquiryNumber.pdf' class='btn-success' download>Download PDF</a>
                </div>
            </div>
        </body>
        </html>";

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $billsDir = __DIR__ . '/bills';
        if (!is_dir($billsDir)) {
            mkdir($billsDir, 0777, true);
        }

        $filePath = "$billsDir/estimate_$enquiryNumber.pdf";
        file_put_contents($filePath, $dompdf->output());

        // Send PDF to user and admin email
        function send_invoice_pdf($pdfPath, $userEmail, $userName, $adminEmail, $adminName, $smtpEmail, $smtpPassword) {
            $mail = new PHPMailer(true);
            try {
                $mail->SMTPDebug = 0; // Set to 2 for debugging
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug [$level]: $str");
                };

                // SMTP configuration
                $mail->isSMTP();
                $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $smtpEmail;
                $mail->Password = $smtpPassword;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Sender and recipients
                $mail->setFrom($smtpEmail, $adminName);
                $mail->addAddress($userEmail, $userName);
                $mail->addBCC($adminEmail, $adminName);

                // Add attachment
                $mail->addAttachment($pdfPath);

                // Email content
                $mail->isHTML(true);
                $mail->Subject = 'Your Estimate Invoice';
                $downloadLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/bills/" . basename($pdfPath);
                $mail->Body = 'Dear ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',<br><br>Thank you for your order. Please find your estimate invoice attached.<br><br>' .
                              'If you cannot view the PDF in your mail, please download the attachment to your device and open it.<br>' .
                              'Alternatively, you can download your invoice directly from this link:<br>' .
                              '<a href="' . $downloadLink . '" target="_blank">Download Invoice PDF</a><br><br>Regards,<br>' . htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');
                $mail->AltBody = 'Dear ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ",\n\nThank you for your order. Please find your estimate invoice attached.\n\n" .
                                 'Download your invoice from: ' . $downloadLink . "\n\nRegards,\n" . htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8');

                // Send email
                $mail->send();
                error_log("Email sent successfully to $userEmail and BCC to $adminEmail");
            } catch (Exception $e) {
                error_log("Failed to send email. Error: " . $mail->ErrorInfo);
                echo "<p style='color: #dc2626;'>Failed to send email. Error: " . htmlspecialchars($mail->ErrorInfo, ENT_QUOTES, 'UTF-8') . "</p>";
                exit;
            }
        }

        // Send the email
        send_invoice_pdf($filePath, $customerEmail, $customerName, $adminEmail, $adminName, $smtpEmail, $smtpPassword);

        // Output the HTML bill
        echo $html;

        // Close database connection
        $conn = null;
   
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo "<p style='color: #dc2626;'>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    exit;
}
?>
<script>
    // 1. ADD THE MISSING FUNCTION DEFINITION
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        // Returns the value of the parameter, or an empty string if not found
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // 2. EXECUTE THE CART CLEARING LOGIC
    // Check for the success flag
    var shouldClearCart = getUrlParameter('cart_cleared'); 

    // If the flag is explicitly 'true' AND the 'cart' item exists, remove it.
    if (shouldClearCart === 'true' && localStorage.getItem("cart")) {
        localStorage.removeItem("cart"); 
        console.log("Shopping cart cleared due to successful payment.");
    }
</script>