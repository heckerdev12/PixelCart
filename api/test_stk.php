<?php
// C:\xampp\htdocs\PixelCart\api\test_stk.php

$data = json_encode([
    'phone'  => '254708374149', // Party A number here
    'amount' => 1               // test with KES 1
]);

$ch = curl_init('http://localhost/PixelCart/api/mpesa_stk.php');
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $data);
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo curl_exec($ch);
curl_close($ch);
