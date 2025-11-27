<?php
header('Content-Type: application/json; charset=utf-8');

require '../shared/config.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['code' => (string)$code, 'message' => $msg]);
    exit;
}

// Pastikan cuma boleh POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method tidak diizinkan, gunakan POST', 405);
}

// Ambil dari POST + JSON body
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$req = array_merge($_POST ?? [], $raw ?? []);

$action = $req['action'] ?? 'create'; // default create

// ================== POST CREATE ==================
if ($action === 'create') {
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $tipe_pembayaran = strtolower($tipe_pembayaran);
    $note            = trim($req['note'] ?? '');
    $nama_kredit     = trim($req['nama_kredit'] ?? '');

    // mapping tipe pembayaran
    $mapPembayaran = [
        'tunai'  => 'cash',
        'cash'   => 'cash',
        'kredit' => 'kredit',
        'credit' => 'kredit',
    ];
    if (isset($mapPembayaran[$tipe_pembayaran])) {
        $tipe_pembayaran = $mapPembayaran[$tipe_pembayaran];
    } else {
        $tipe_pembayaran = 'cash';
    }

    $harga_akhir = $req['harga_akhir'] ?? null;
    $kode_mobil  = trim($req['kode_mobil'] ?? '');
    $kode_user   = $req['kode_user'] ?? null;  
    $status      = trim($req['status'] ?? 'pending');

    // ==== jaminan dari request ====
    $jaminan_ktp      = intval($req['jaminan_ktp'] ?? 0);
    $jaminan_kk       = intval($req['jaminan_kk'] ?? 0);
    $jaminan_rekening = intval($req['jaminan_rekening'] ?? 0);

    // VALIDASI
    if ($nama_pembeli === '')      json_error('Field nama_pembeli wajib diisi');
    if ($no_hp === '')             json_error('Field no_hp wajib diisi');
    if ($tipe_pembayaran === '')   json_error('Field tipe_pembayaran wajib diisi');
    if (!is_numeric($harga_akhir)) json_error('Field harga_akhir wajib numeric');
    if ($kode_mobil === '')        json_error('Field kode_mobil wajib diisi');

    // NORMALISASI STATUS
    $status = strtolower($status);
    $mapStatus = [
        'selesai'   => 'completed',
        'done'      => 'completed',
        'success'   => 'completed',
        'pending'   => 'pending',
        'menunggu'  => 'pending',
        'cancel'    => 'cancelled',
        'canceled'  => 'cancelled',
        'cancelled' => 'cancelled',
        'batal'     => 'cancelled'
    ];
    if (isset($mapStatus[$status])) {
        $status = $mapStatus[$status];
    } elseif (!in_array($status, ['completed','pending','cancelled'], true)) {
        $status = 'pending';
    }

    // GENERATE KODE TRANSAKSI via FUNCTION MySQL
    $sqlKode = "SELECT generate_kode_transaksi() AS kode";
    $resKode = $conn->query($sqlKode);
    if (!$resKode) {
        json_error('Gagal generate kode transaksi: '.$conn->error, 500);
    }

    $rowKode = $resKode->fetch_assoc();
    $kode_transaksi = $rowKode['kode'] ?? null;

    if (!$kode_transaksi) {
        json_error('Gagal generate kode transaksi (hasil kosong)', 500);
    }

    // INSERT KE TABEL TRANSAKSI
    $sql = "INSERT INTO transaksi
        (kode_transaksi, nama_pembeli, no_hp, tipe_pembayaran, harga_akhir,
         kode_mobil, kode_user, status, note, nama_kredit, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare insert: '.$conn->error, 500);
    }

    // s = string, i = int
    $stmt->bind_param(
        'ssssisssss',
        $kode_transaksi,
        $nama_pembeli,
        $no_hp,
        $tipe_pembayaran,
        $harga_akhir,
        $kode_mobil,
        $kode_user,
        $status,
        $note,
        $nama_kredit
    );

    if (!$stmt->execute()) {
        json_error('Gagal menyimpan transaksi: '.$stmt->error, 500);
    }

    $stmt->close();

    // ================== UPDATE STATUS MOBIL ==================
    $status_mobil_baru = 'available';

    if ($status === 'completed') {
        $status_mobil_baru = 'sold';
    } elseif ($status === 'pending') {
        $status_mobil_baru = 'reserved';
    } elseif ($status === 'cancelled') {
        $status_mobil_baru = 'available';
    }

    $sqlMobil = "UPDATE mobil SET status = ? WHERE kode_mobil = ?";
    $stmtMobil = $conn->prepare($sqlMobil);
    if ($stmtMobil) {
        $stmtMobil->bind_param('ss', $status_mobil_baru, $kode_mobil);
        $stmtMobil->execute();
        $stmtMobil->close();
    }

    // ==== SIMPAN DETAIL JAMINAN (CREATE) ====
    $ID_JAMINAN_KTP      = 2;
    $ID_JAMINAN_KK       = 1;
    $ID_JAMINAN_REKENING = 3;

    $sqlJ = "INSERT INTO detail_jaminan (kode_transaksi, id_jaminan, keterangan)
             VALUES (?, ?, NULL)";
    $stmtJ = $conn->prepare($sqlJ);
    if (!$stmtJ) {
        json_error('Gagal prepare detail jaminan: '.$conn->error, 500);
    }

    if ($jaminan_ktp) {
        $idJ = $ID_JAMINAN_KTP;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }
    if ($jaminan_kk) {
        $idJ = $ID_JAMINAN_KK;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }
    if ($jaminan_rekening) {
        $idJ = $ID_JAMINAN_REKENING;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }

    $stmtJ->close();

    if ($status === 'completed') {
        $sqlM = "UPDATE mobil SET status = 'sold' WHERE kode_mobil = ?";
        $stmtM = $conn->prepare($sqlM);
        if ($stmtM) {
            $stmtM->bind_param('s', $kode_mobil);
            $stmtM->execute();
            $stmtM->close();
        }
    }

    echo json_encode([
        'code'    => '200',
        'message' => 'Transaksi berhasil dibuat',
        'data'    => [
            'kode_transaksi' => $kode_transaksi,
            'status'         => $status
        ]
    ]);
    exit;
}

// ================== POST UPDATE ==================
elseif ($action === 'update') {
    // ambil kode_transaksi yang mau di-update
    $kode_transaksi = trim($req['kode_transaksi'] ?? '');
    if ($kode_transaksi === '') {
        json_error('kode_transaksi wajib dikirim untuk update');
    }

    // ambil field lain sama kayak create
    $nama_pembeli    = trim($req['nama_pembeli'] ?? '');
    $no_hp           = trim($req['no_hp'] ?? '');
    $tipe_pembayaran = trim($req['tipe_pembayaran'] ?? '');
    $tipe_pembayaran = strtolower($tipe_pembayaran);
    $note            = trim($req['note'] ?? '');
    $nama_kredit     = trim($req['nama_kredit'] ?? '');

    $mapPembayaran = [
        'tunai'  => 'cash',
        'cash'   => 'cash',
        'kredit' => 'kredit',
        'credit' => 'kredit',
    ];
    if (isset($mapPembayaran[$tipe_pembayaran])) {
        $tipe_pembayaran = $mapPembayaran[$tipe_pembayaran];
    } else {
        $tipe_pembayaran = 'cash';
    }

    $harga_akhir = $req['harga_akhir'] ?? null;
    $kode_mobil  = trim($req['kode_mobil'] ?? '');
    $kode_user   = $req['kode_user'] ?? null;
    $status      = trim($req['status'] ?? 'pending');

    $jaminan_ktp      = intval($req['jaminan_ktp'] ?? 0);
    $jaminan_kk       = intval($req['jaminan_kk'] ?? 0);
    $jaminan_rekening = intval($req['jaminan_rekening'] ?? 0);

    // ambil data lama dulu (status, kode_mobil & kode_user sebelumnya)
    $sqlOld = "SELECT status, kode_mobil, kode_user FROM transaksi WHERE kode_transaksi = ?";
    $stmtOld = $conn->prepare($sqlOld);
    if (!$stmtOld) {
        json_error('Gagal prepare select transaksi lama: '.$conn->error, 500);
    }
    $stmtOld->bind_param('s', $kode_transaksi);
    $stmtOld->execute();
    $resultOld = $stmtOld->get_result();
    $old = $resultOld->fetch_assoc();
    $stmtOld->close();

    if (!$old) {
        json_error('Transaksi tidak ditemukan (data lama)', 404);
    }

    $oldStatus    = strtolower($old['status'] ?? '');
    $oldKodeMobil = $old['kode_mobil'] ?? '';
    $oldKodeUser  = $old['kode_user'] ?? null;

    // VALIDASI
    if ($nama_pembeli === '')      json_error('Field nama_pembeli wajib diisi');
    if ($no_hp === '')             json_error('Field no_hp wajib diisi');
    if ($tipe_pembayaran === '')   json_error('Field tipe_pembayaran wajib diisi');
    if (!is_numeric($harga_akhir)) json_error('Field harga_akhir wajib numeric');
    if ($kode_mobil === '')        json_error('Field kode_mobil wajib diisi');

    // NORMALISASI STATUS
    $status = strtolower($status);
    $mapStatus = [
        'selesai'   => 'completed',
        'done'      => 'completed',
        'success'   => 'completed',
        'pending'   => 'pending',
        'menunggu'  => 'pending',
        'cancel'    => 'cancelled',
        'canceled'  => 'cancelled',
        'cancelled' => 'cancelled',
        'batal'     => 'cancelled'
    ];
    if (isset($mapStatus[$status])) {
        $status = $mapStatus[$status];
    } elseif (!in_array($status, ['completed','pending','cancelled'], true)) {
        $status = 'pending';
    }

    // kalau request tidak kirim kode_user / kosong, pakai kode_user lama
    if ($kode_user === null || $kode_user === '') {
        $kode_user = $oldKodeUser;
    }

    // QUERY UPDATE TRANSAKSI
    $sql = "UPDATE transaksi
            SET nama_pembeli    = ?,
                no_hp           = ?,
                tipe_pembayaran = ?,
                harga_akhir     = ?,
                kode_mobil      = ?,
                kode_user       = ?,
                status          = ?,
                note            = ?,
                nama_kredit     = ?
            WHERE kode_transaksi = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_error('Gagal prepare update: '.$conn->error, 500);
    }

    $stmt->bind_param(
        'sssissssss',
        $nama_pembeli,
        $no_hp,
        $tipe_pembayaran,
        $harga_akhir,
        $kode_mobil,
        $kode_user,
        $status,
        $note,
        $nama_kredit,
        $kode_transaksi
    );

    if (!$stmt->execute()) {
        json_error('Gagal mengupdate transaksi: '.$stmt->error, 500);
    }

    $rows = $stmt->affected_rows; // hanya untuk info (bukan error)
    $stmt->close();

    // ==== UPDATE DETAIL JAMINAN (UPDATE) ====
    // hapus semua jaminan lama untuk transaksi ini
    $sqlDelJ = "DELETE FROM detail_jaminan WHERE kode_transaksi = ?";
    $stmtDelJ = $conn->prepare($sqlDelJ);
    if ($stmtDelJ) {
        $stmtDelJ->bind_param('s', $kode_transaksi);
        $stmtDelJ->execute();
        $stmtDelJ->close();
    }

    // ID jaminan (sesuaikan dengan tabel jaminan milikmu)
    $ID_JAMINAN_KTP      = 1;
    $ID_JAMINAN_KK       = 2;
    $ID_JAMINAN_REKENING = 3;

    $sqlJ = "INSERT INTO detail_jaminan (kode_transaksi, id_jaminan, keterangan)
             VALUES (?, ?, NULL)";
    $stmtJ = $conn->prepare($sqlJ);
    if (!$stmtJ) {
        json_error('Gagal prepare detail jaminan (update): '.$conn->error, 500);
    }

    if ($jaminan_ktp) {
        $idJ = $ID_JAMINAN_KTP;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }
    if ($jaminan_kk) {
        $idJ = $ID_JAMINAN_KK;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }
    if ($jaminan_rekening) {
        $idJ = $ID_JAMINAN_REKENING;
        $stmtJ->bind_param('si', $kode_transaksi, $idJ);
        $stmtJ->execute();
    }

    $stmtJ->close();

    // ====== update status mobil kalau perlu ======
    if ($status === 'completed' && $oldStatus !== 'completed') {
        $sqlM = "UPDATE mobil SET status = 'sold' WHERE kode_mobil = ?";
        $stmtM = $conn->prepare($sqlM);
        if ($stmtM) {
            $stmtM->bind_param('s', $kode_mobil);
            $stmtM->execute();
            $stmtM->close();
        }
    }

    if ($oldStatus === 'completed' && $status !== 'completed') {
        $sqlM2 = "UPDATE mobil SET status = 'available' WHERE kode_mobil = ?";
        $stmtM2 = $conn->prepare($sqlM2);
        if ($stmtM2) {
            $stmtM2->bind_param('s', $oldKodeMobil);
            $stmtM2->execute();
            $stmtM2->close();
        }
    }

    echo json_encode([
        'code'    => '200',
        'message' => $rows > 0 
            ? 'Transaksi berhasil diupdate'
            : 'Tidak ada perubahan pada data transaksi',
        'data'    => [
            'kode_transaksi' => $kode_transaksi,
            'status'         => $status,
            'changed_rows'   => $rows,
        ],
    ]);
    exit;
}

json_error('Action tidak valid untuk POST', 400);
