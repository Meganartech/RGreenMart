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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {
        // Sanitize and validate POST data
        $customerName = htmlspecialchars($_POST['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerMobile = htmlspecialchars($_POST['customer_mobile'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerEmail = htmlspecialchars($_POST['customer_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerState = htmlspecialchars($_POST['customer_state'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerCity = htmlspecialchars($_POST['customer_city'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerAddress = htmlspecialchars($_POST['customer_address'] ?? '', ENT_QUOTES, 'UTF-8');
        $itemsBought = json_decode($_POST['items_bought'] ?? '[]', true);
        $orderedDateTime = isset($_POST['ordered_date_time']) ? htmlspecialchars($_POST['ordered_date_time'], ENT_QUOTES, 'UTF-8') : date('Y-m-d H:i:s');

        // Validate required fields
        if (empty($customerName) || empty($customerMobile) || empty($customerEmail) || empty($customerState) || empty($customerCity) || empty($customerAddress)) {
            echo "<p style='color: #dc2626;'>Error: All required fields must be filled.</p>";
            exit;
        }

        // Validate emails
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            echo "<p style='color: #dc2626;'>Error: Invalid customer email address.</p>";
            exit;
        }

        if (!is_array($itemsBought) || empty($itemsBought)) {
            echo "<p style='color: #dc2626;'>Error: No items selected for the bill.</p>";
            exit;
        }

        // Fetch and increment enquiry number
        try {
            $conn->beginTransaction();
            $stmt = $conn->query("SELECT last_enquiry_number FROM settings LIMIT 1 FOR UPDATE");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $enquiryNumber = $row ? (int)$row['last_enquiry_number'] + 1 : 1001;
            $conn->exec("UPDATE settings SET last_enquiry_number = $enquiryNumber");
            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Failed to generate enquiry number: " . $e->getMessage());
            echo "<p style='color: #dc2626;'>Error: Failed to generate enquiry number.</p>";
            exit;
        }

        // Fetch admin details from DB
        $adminStmt = $conn->query("SELECT name, phone, email, shopaddress FROM admin_details LIMIT 1");
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
        $adminName = $admin['name'] ?? 'RGreenMart';
        $adminMobile = $admin['phone'] ?? '99524 24474';
        $adminEmail = $admin['email'] ?? 'sales@rgreenmart.com';
        $adminAddress = $admin['shopaddress'] ?? 'Chandragandhi Nagar, Madurai, Tamil Nadu';

        // Validate admin email
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid admin email: $adminEmail");
            echo "<p style='color: #dc2626;'>Error: Invalid admin email address in database.</p>";
            exit;
        }

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

        // Calculate totals
        $subtotal = 0;
        $totalDiscountAmount = 0;
        $netTotal = 0;
        $totalGst = 0;
        $rowsHtml = "";

        foreach ($itemsBought as $index => $item) {
            $grossPrice = $item['grossPrice'];
            $netPrice = $grossPrice / (1 + $gstRate / 100);
            $gstAmount = $grossPrice - $netPrice;
            $discountAmount = $netPrice * ($item['discount'] / 100);
            $discountedNetPrice = $netPrice - $discountAmount;
            $simpleDiscountedPrice = $grossPrice * (1 - ($item['discount'] / 100));
            $amount = ($discountedNetPrice + ($discountedNetPrice * ($gstRate / 100))) * $item['quantity'];

            $subtotal += $grossPrice * $item['quantity'];
            $totalDiscountAmount += $discountAmount * $item['quantity'];
            $netTotal += $discountedNetPrice * $item['quantity'];
            $totalGst += ($discountedNetPrice * ($gstRate / 100)) * $item['quantity'];

            $rowsHtml .= "<tr><td>" . ($index + 1) . "</td><td class='left'>" . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . "</td><td>" . number_format($grossPrice, 2) . "</td><td>" . number_format($discountAmount, 2) . " (" . number_format($item['discount'], 2) . "%)</td><td>" . number_format($simpleDiscountedPrice, 2) . "</td><td>" . $item['quantity'] . "</td><td>" . number_format($amount, 2) . "</td></tr>";
        }
        $overallTotal = $netTotal + $totalGst;

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
                            <td class='left'>Enquiry No: $enquiryNumber</td>
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
                        <tr><td colspan='6' class='right'>You Saved</td><td>" . number_format($totalDiscountAmount, 2) . "</td></tr>
                        <tr><td colspan='6' class='right'>Sub Total</td><td>" . number_format($subtotal, 2) . "</td></tr>
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
                $mail->Subject = "Your Estimate Invoice - $enquiryNumber";
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
    } else {
        echo "<p style='color: #dc2626;'>Error: Invalid request.</p>";
        exit;
    }
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo "<p style='color: #dc2626;'>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    exit;
}
?>