<?php
require_once '../session.php';
include "../DBconnect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminName = $_SESSION['name'] ?? 'Admin';
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //ensure action insist
    $action = $_POST['action'] ?? null;

    if ($action === 'add') {
        $store_name = trim($_POST['store_name'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        if ($store_name && $location) {
            $stmt = $conn->prepare("INSERT INTO store (store_name, location) VALUES (?, ?)");
            $stmt->bind_param("ss", $store_name, $location);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Store added successfully!";
            } else {
                $_SESSION['error'] = "Error adding store.";
            }
            $stmt->close();
            header("Location: store.php");
            exit();
        } else {
            $_SESSION['error'] = "All fields are required.";
            header("Location: store.php");
            exit();
        }
    }

    elseif ($action === 'delete') {
        $store_id = (int)($_POST['store_id'] ?? 0);
        if ($store_id) {
            $stmt = $conn->prepare("DELETE FROM store WHERE store_id=?");
            $stmt->bind_param("i", $store_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Store deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting store.";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Invalid store ID.";
        }
        header("Location: store.php");
        exit();
    }

    elseif ($action === 'edit') {
        $store_id   = (int)($_POST['store_id'] ?? 0);
        $store_name = trim($_POST['store_name'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        if ($store_id && $store_name && $location) {
            $stmt = $conn->prepare("UPDATE store SET store_name=?, location=? WHERE store_id=?");
            $stmt->bind_param("ssi", $store_name, $location, $store_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Store updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating store.";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "All fields are required or invalid store ID.";
        }
        header("Location: store.php");
        exit();
    }

    else {
        //unknown or missing action
        $_SESSION['error'] = "Invalid action.";
        header("Location: store.php");
        exit();
    }
}

//fetch all stores
$stores_result = $conn->query("SELECT * FROM store ORDER BY store_id DESC");
$stores = [];
while ($row = $stores_result->fetch_assoc()) $stores[] = $row;
$store_count = count($stores);

// Edit prefill
$edit_store = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $estmt = $conn->prepare("SELECT * FROM store WHERE store_id=?");
    $estmt->bind_param("i", $edit_id);
    $estmt->execute();
    $edit_store = $estmt->get_result()->fetch_assoc();
    $estmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management - Mr. Softy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../Design/adminAddStore.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>

<!-- Top Bar -->
<div class="top-bar">
    <a href="adminDashboard.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
    <span class="top-bar-icon"><i class="fas fa-store"></i></span>
    <div>
        <h1>Store Management</h1>
        <div class="top-bar-sub">Manage Mr. Softy branch locations</div>
    </div>
</div>

<div class="page-wrap">
    <div class="form-card">
        <div class="form-logo">
            <img src="../img/mrsofty2.png" alt="Mr. Softy">
        </div>
        <div class="form-brand-name">Mr. Softy</div>
        <div class="form-tagline">Signature Creations</div>

        <div class="form-heading <?= $edit_store ? 'edit-heading' : '' ?>">
            <?= $edit_store ? 'Edit Store Details' : 'Add New Store / Branch' ?>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" id="storeForm">
            <input type="hidden" name="action" value="<?= $edit_store ? 'edit' : 'add' ?>">
            <?php if ($edit_store): ?>
                <input type="hidden" name="store_id" value="<?= $edit_store['store_id'] ?>">
            <?php endif; ?>

            <div class="field-wrap">
                <input type="text" name="store_name" class="field-input" placeholder="Store Name"
                    value="<?= $edit_store ? htmlspecialchars($edit_store['store_name']) : '' ?>" required>
            </div>
            <div class="field-wrap">
                <input type="text" name="location" class="field-input" placeholder="Location / Address"
                    value="<?= $edit_store ? htmlspecialchars($edit_store['location']) : '' ?>" required>
            </div>

            <button type="submit" class="btn-primary <?= $edit_store ? 'orange' : '' ?>">
                <?= $edit_store ? 'SAVE CHANGES' : 'ADD STORE' ?>
            </button>
        </form>

        <?php if ($edit_store): ?>
            <a href="store.php" class="btn-cancel-link">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="table-card">
        <div class="tbl-header">
            <div class="th"><i class="fas fa-hashtag"></i> ID</div>
            <div class="th"><i class="fas fa-store"></i> Store Name</div>
            <div class="th"><i class="fas fa-map-marker-alt"></i> Location</div>
            <div class="th"><i class="fas fa-cog"></i> Action</div>
        </div>

        <?php if (empty($stores)): ?>
            <div class="empty-state">
                <i class="fas fa-store-slash"></i>
                <p>No stores yet — add your first branch!</p>
            </div>
        <?php else: ?>
            <?php foreach ($stores as $store): ?>
            <div class="tbl-row">
                <div class="td-id"><?= $store['store_id'] ?></div>
                <div class="td-name"><?= htmlspecialchars($store['store_name']) ?></div>
                <div class="td-loc">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($store['location']) ?>
                </div>
                <div class="td-actions">
                    <a href="store.php?edit=<?= $store['store_id'] ?>" class="btn-edit-tbl" title="Edit">
                        <i class="fas fa-pen"></i>
                    </a>
                    <button class="btn-del-tbl" title="Delete"
                        onclick="confirmDelete(<?= $store['store_id'] ?>, '<?= addslashes(htmlspecialchars($store['store_name'])) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Delete Confirm Overlay -->
<div class="overlay" id="delOverlay">
    <div class="confirm-box">
        <div class="conf-icon"><i class="fas fa-trash"></i></div>
        <div class="conf-title">Delete Store?</div>
        <p class="conf-sub">You're about to delete <strong id="delName" style="color:#2c3e50;"></strong>.<br>This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="store_id" id="delId">
            <div class="conf-actions">
                <button type="button" class="btn-conf-no" onclick="closeDelete()">Cancel</button>
                <button type="submit" class="btn-conf-yes">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// DELETE SWEET ALERT
function confirmDelete(id, name) {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to delete " + name,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#e74c3c",
        cancelButtonColor: "#95a5a6",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "store.php";

            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete";

            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "store_id";
            idInput.value = id;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// ADD / EDIT SWEET ALERT
document.getElementById("storeForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let action = document.querySelector("input[name='action']").value;
    let titleText = action === "edit" ? "Save changes?" : "Add new store?";
    let confirmText = action === "edit" ? "Yes, save it!" : "Yes, add it!";

    Swal.fire({
        title: titleText,
        text: "Please confirm this action.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#2e9bd6",
        cancelButtonColor: "#95a5a6",
        confirmButtonText: confirmText
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("storeForm").submit();
        }
    });
});
</script>

<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire({
    icon: "success",
    title: "Success",
    text: "<?= $_SESSION['success']; ?>",
    confirmButtonColor: "#2e9bd6",
    timer: 1500,
    showConfirmButton: false,
    position: "center"
});
</script>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire({
    icon: "error",
    title: "Error",
    text: "<?= $_SESSION['error']; ?>",
    confirmButtonColor: "#e74c3c",
    position: "center"
});
</script>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>
</body>
</html>