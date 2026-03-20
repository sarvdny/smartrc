<?php
// ============================================================
//  auth/login/getpostdata.php
// ============================================================

function getPostData(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
