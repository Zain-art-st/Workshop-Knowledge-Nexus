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

// 4. Fungsi Automatik untuk Menjana Slug Unik (Sesuai dengan struktur tabel subcommunities)
function generateSlug($string)
{
    // Tukar huruf besar ke huruf kecil
    $slug = strtolower($string);
    // Gantikan simbol & atau karakter bukan alfa-numerik kepada sempang (-)
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

$slug = generateSlug($name);

if (empty($slug)) {
    $slug = 'sub-' . rand(1000, 9999);
}

// 5. Semakan Duplikasi: Pastikan 'name' dan 'slug' belum wujud di database (Sebab UNIQUE KEY di setup.sql)
$check_stmt = $conn->prepare("SELECT id FROM subcommunities WHERE name = ? OR slug = ? LIMIT 1");
$check_stmt->bind_param("ss", $name, $slug);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nama atau pautan komuniti ini sudah pun digunakan. Sila pilih nama lain!']);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// 6. Melaksanakan Kemasukan Data (INSERT INTO) ke Tabel `subcommunities`
// Kolom sepadan: name, slug, description, topic, creator_id, member_count
$insert_query = "INSERT INTO subcommunities (name, slug, description, topic, creator_id, member_count) VALUES (?, ?, ?, ?, ?, 1)";
$stmt = $conn->prepare($insert_query);

if ($stmt) {
    // Sesiapa yang mencipta automatik dikira mempunyai member_count = 1
    $stmt->bind_param("ssssi", $name, $slug, $description, $topic, $creator_id);

    if ($stmt->execute()) {
        // Jika berjaya, pulangkan respon kejayaan bersama nilai slug baharu
        echo json_encode([
            'status' => 'success',
            'message' => 'Sub-komuniti berjaya dicipta!',
            'slug' => $slug
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