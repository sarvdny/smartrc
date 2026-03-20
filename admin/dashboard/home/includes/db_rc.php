<?php
// ============================================================
//  admin/dashboard/home/includes/db_rc.php
//  Procedural MySQLi helpers for RC_DATA database
// ============================================================

function getRcDB()
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = mysqli_connect('localhost', 'root', '', 'RC_DATA');
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

// Prepared SELECT → all rows
function db_fetch_all($conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Prepared SELECT → first row only
function db_fetch_one($conn, string $sql, string $types = '', array $params = [])
{
    $rows = db_fetch_all($conn, $sql, $types, $params);
    return $rows[0] ?? false;
}

// Prepared INSERT / UPDATE / DELETE
function db_execute($conn, string $sql, string $types, array $params): bool
{
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Single scalar value (COUNT etc.)
function db_scalar($conn, string $sql)
{
    $result = mysqli_query($conn, $sql);
    $row    = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return $row[0] ?? null;
}
