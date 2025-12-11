<?php
session_start();
require_once $_SERVER["DOCUMENT_ROOT"] . "/dbconf.php";

$user_id = $_SESSION["user_id"];

$is_default = isset($_POST['is_default']) ? 1 : 0;

if ($is_default) {
    $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")
         ->execute([$user_id]);
}

$stmt = $conn->prepare("
    INSERT INTO user_addresses (
        user_id, contact_name, contact_mobile, address_line1, address_line2,
        city, state, pincode, landmark, is_default
    ) VALUES (?,?,?,?,?,?,?,?,?,?)
");

$stmt->execute([
    $user_id,
    $_POST['contact_name'],
    $_POST['contact_mobile'],
    $_POST['address_line1'],
    $_POST['address_line2'],
    $_POST['city'],
    $_POST['state'],
    $_POST['pincode'],
    $_POST['landmark'],
    $is_default
]);

header("Location: add_delivery_address.php");
exit;
