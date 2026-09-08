<?php
function sendResponse($success, $data = null, $message = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success];
    
    if ($success) {
        if ($data !== null) $response['data'] = $data;
    } else {
        if ($message !== null) $response['message'] = $message;
    }
    
    echo json_encode($response);
    exit;
}
