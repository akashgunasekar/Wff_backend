<?php
function sendResponse($success, $data = null, $message = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success];
    
    if ($success) {
        if ($data !== null) $response['data'] = $data;
    } else {
        if ($message !== null) $response['message'] = $message;
    }
    
    $json = json_encode($response);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'JSON Encode Error: ' . json_last_error_msg()]);
    } else {
        echo $json;
    }
    exit;
}
