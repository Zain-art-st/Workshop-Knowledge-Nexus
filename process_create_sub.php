<?php
// process_create_sub.php
session_start();
header('Content-Type: application/json');

// 1. Konfigurasi Sambungan Pangkalan Data MySQL
include "db.php";

// 2. Pengesahan Kaedah Permintaan (Hanya benarkan POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Kaedah permintaan tidak sah.']);
    exit();
}

// 3. Mengambil & Membersihkan Data Input
$topic = isset($_POST['topic']) ? trim($_POST['topic']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

// Kenal pasti ID pencipta jika ada sesi aktif, jika tiada set kepada NULL (sepadan dengan setup.sql)
$creator_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

// Validation ringkas di bahagian backend
if (empty($topic) || empty($name) || empty($description)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua medan maklumat wajib diisi.']);
    exit();
}

// 5. Semakan Duplikasi: Pastikan 'name' belum wujud di database (Sebab UNIQUE KEY di setup.sql)
$check_stmt = $conn->prepare("SELECT id FROM subcommunities WHERE name = ? LIMIT 1");
$check_stmt->bind_param("s", $name);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nama komuniti ini sudah pun digunakan. Sila pilih nama lain!']);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// 6. Melaksanakan Kemasukan Data (INSERT INTO) ke Tabel `subcommunities`
// Kolom sepadan: name, description, topic, creator_id, member_count
$insert_query = "INSERT INTO subcommunities (name, description, topic, creator_id, member_count) VALUES (?, ?, ?, ?, 0)";
$stmt = $conn->prepare($insert_query);

if ($stmt) {
    // Sesiapa yang mencipta automatik dikira mempunyai member_count = 0
    $stmt->bind_param("sssi", $name, $description, $topic, $creator_id);

    if ($stmt->execute()) {
        // Jika berjaya, pulangkan respon kejayaan bersama nilai id baharu
        echo json_encode([
            'status' => 'success',
            'message' => 'Sub-komuniti berjaya dicipta!',
            'id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memasukkan data ke pangkalan data: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Ralat penyediaan SQL: ' . $conn->error]);
}

$conn->close();
?>