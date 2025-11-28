<?php
require_once 'vendor/autoload.php';
require_once __DIR__ . "/includes/env.php";

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'diwali_db';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- START: CONVERTED FROM POST TO GET/DB FETCH ---
    if (!isset($_GET['order_id'])) {
        die("<p style='color: #dc2626;'>Error: Order ID missing in URL.</p>");
    }

    $orderId = intval($_GET['order_id']);

    // 1. Fetch Order Details (Customer Info and Totals)
    $orderStmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("<p style='color: #dc2626;'>Error: Order ID $orderId not found.</p>");
    }

    // Map DB fields to variables
    $customerName      = $order['name'];
    $customerMobile    = $order['mobile'];
    $customerEmail     = $order['email'];
    $customerState     = $order['state'];
    $customerCity      = $order['city'];
    $customerAddress   = $order['address'];
    $enquiryNumber     = $order['enquiry_no'];
    $orderedDateTime   = $order['order_date'];
    $packingchargeFromDB = $order['packing_charge'];
    $netTotalFromDB    = $order['net_total'];
    $overallTotalFromDB = $order['overall_total'];

    // 2. Fetch Order Items
    $itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $itemsBought = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$itemsBought) {
        die("<p style='color: #dc2626;'>Error: No items found for order ID $orderId.</p>");
    }

    // Since the original POST logic involved *re-calculating* totals, we will
    // stick to the original logic for generating the HTML to ensure consistency,
    // but the $packingcharge and $overallTotal used for display will be based
    // on the calculated values below, not the ones fetched from DB.
    // We only need $enquiryNumber, $customerName, etc., for the invoice header.

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

    // Calculate totals (MUST match the original POST calculation logic)
    $subtotal = 0;
    $totalDiscountAmount = 0;
    $netTotal = 0;
    $totalGst = 0;
    $packingcharge = 0;
    $packingpercent = 3;
    $rowsHtml = "";

    foreach ($itemsBought as $index => $item) {
        // Use 'price' for grossPrice and 'qty' for quantity from the order_items table
        $grossPrice = $item['price'];
        $itemDiscount = $item['discount']; // This is the percentage
        $itemQuantity = $item['qty'];

        // Calculations mimicking the original POST logic:
        $netPrice = $grossPrice; // Original code used $grossPrice / (1 + $gstRate / 100), but then commented it out
        
        $discountAmount = $grossPrice * ($itemDiscount / 100);
        $discountedNetPrice = $grossPrice - $discountAmount; // This is the discounted price
        
        $simpleDiscountedPrice = $discountedNetPrice; // Naming is confusing, use the discounted price
        
        $amount = $discountedNetPrice * $itemQuantity; // Total amount for this item

        // Accumulate totals based on item calculations
        $subtotal += $grossPrice * $itemQuantity;
        $totalDiscountAmount += $discountAmount * $itemQuantity;
        $netTotal += $amount; // This is the sum of discounted amounts for all items
        
        // $totalGst += ($discountedNetPrice * ($gstRate / 100)) * $itemQuantity; // This line was in original code but is unused in grand total

        $rowsHtml .= "<tr><td>" . ($index + 1) . "</td><td class='left'>" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "</td><td>" . number_format($grossPrice, 2) . "</td><td>" . number_format($discountAmount, 2) . " (" . number_format($itemDiscount, 2) . "%)</td><td>" . number_format($simpleDiscountedPrice, 2) . "</td><td>" . $itemQuantity . "</td><td>" . number_format($amount, 2) . "</td></tr>";
    }
    
    $packingcharge = ($netTotal * $packingpercent) / 100;
    $overallTotal = $netTotal + $packingcharge; // + $totalGst;

    // --- END: CONVERTED FROM POST TO GET/DB FETCH ---

    // The rest of the file is the PDF generation and email logic, which remains unchanged
    


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
						<tr><td colspan='6' class='right bold'>Packing Charge (3%)</td><td class='bold'>" . number_format($packingcharge, 2) . "</td></tr>
                        <tr><td colspan='6' class='right bold'>Overall Total</td><td class='bold'>" . number_format($overallTotal, 2) . "</td></tr>
                    </table>

                    <p class='bold'>Total Items: " . count($itemsBought) . "</p>
                </div>
                <div style='text-align: center; margin: 20px;'>
                 <a href='/Ecommerce/bills/estimate_$enquiryNumber.pdf' class='btn-success' download>Download PDF</a>
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