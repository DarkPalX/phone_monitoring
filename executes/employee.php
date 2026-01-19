<?php
    include("config.php");

    // CREATE Employee
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $name = $_POST['name'] ?? '';
        if ($name) {
            $sql = "INSERT INTO employees (name, created_at, updated_at) VALUES (?, GETDATE(), GETDATE())";
            sqlsrv_query($conn, $sql, [$name]);
        }
        header("Location: ../index.php");
        exit;
    }

    // UPDATE Employee
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        if ($id && $name) {
            $sql = "UPDATE employees SET name=?, updated_at=GETDATE() WHERE id=?";
            sqlsrv_query($conn, $sql, [$name, $id]);
        }
        header("Location: ../index.php");
        exit;
    }

    // DELETE Employee
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $sql = "UPDATE employees SET deleted_at=GETDATE(), updated_at=GETDATE() WHERE id=?";
            // $sql = "DELETE FROM employees WHERE id=?";
            sqlsrv_query($conn, $sql, [$id]);
        }
        header("Location: ../index.php");
        exit;
    }
?>
