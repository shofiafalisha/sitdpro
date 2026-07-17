<?php
/**
 * =========================================================================
 * DataStore.php - UNIVERSAL JSON CRUD HELPER
 * =========================================================================
 * Helper ini menggantikan koneksi MySQL untuk project autokoding.
 * Data disimpan di folder data/*.json — zero config, langsung jalan.
 *
 * CARA PAKAI:
 *   include 'helper/DataStore.php';
 *   $users = loadData('users');
 *   addRecord('users', ['nama' => 'Budi', 'email' => 'budi@mail.com']);
 *
 * Developer: Sikawarsito, S.Kom — Xieqa Autokoding Engine
 * =========================================================================
 */

// Tentukan path folder data relatif terhadap file yang meng-include
if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__ . '/../data/');
}

// Pastikan folder data ada
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

/**
 * Ambil path file JSON berdasarkan nama collection
 */
function getDataPath($collection) {
    return DATA_DIR . $collection . '.json';
}

/**
 * LOAD DATA: Baca semua record dari collection
 * @param string $collection Nama collection (tanpa .json)
 * @return array Array of records
 */
function loadData($collection) {
    $path = getDataPath($collection);
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    if (!$data || !isset($data['records'])) {
        return [];
    }
    return $data['records'];
}

/**
 * SAVE DATA: Simpan seluruh data ke collection
 * @param string $collection Nama collection
 * @param array $records Array of records
 * @return bool Success status
 */
function saveData($collection, $records) {
    $path = getDataPath($collection);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $data = [
        'collection' => $collection,
        'updated_at' => date('Y-m-d H:i:s'),
        'total' => count($records),
        'records' => array_values($records)
    ];
    
    // File locking untuk keamanan concurrent access
    $fp = fopen($path, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

/**
 * INIT DATA: Inisialisasi collection dengan data default (hanya jika belum ada)
 * @param string $collection Nama collection
 * @param array $defaultRecords Data default
 * @return bool True jika baru dibuat, false jika sudah ada
 */
function initData($collection, $defaultRecords = []) {
    $path = getDataPath($collection);
    if (file_exists($path)) {
        return false; // Sudah ada, jangan overwrite
    }
    saveData($collection, $defaultRecords);
    return true;
}

/**
 * FIND BY ID: Cari 1 record berdasarkan id
 * @param string $collection Nama collection
 * @param int $id ID yang dicari
 * @return array|null Record atau null jika tidak ditemukan
 */
function findById($collection, $id) {
    $records = loadData($collection);
    foreach ($records as $record) {
        if (isset($record['id']) && $record['id'] == $id) {
            return $record;
        }
    }
    return null;
}

/**
 * SEARCH DATA: Cari records berdasarkan field tertentu
 * @param string $collection Nama collection
 * @param string $field Nama field
 * @param mixed $value Nilai yang dicari
 * @return array Array of matching records
 */
function searchData($collection, $field, $value) {
    $records = loadData($collection);
    $results = [];
    foreach ($records as $record) {
        if (isset($record[$field]) && $record[$field] == $value) {
            $results[] = $record;
        }
    }
    return $results;
}

/**
 * SEARCH LIKE: Cari records yang mengandung kata kunci (case-insensitive)
 * @param string $collection Nama collection
 * @param string $field Nama field
 * @param string $keyword Kata kunci
 * @return array Array of matching records
 */
function searchLike($collection, $field, $keyword) {
    $records = loadData($collection);
    $results = [];
    foreach ($records as $record) {
        if (isset($record[$field]) && stripos($record[$field], $keyword) !== false) {
            $results[] = $record;
        }
    }
    return $results;
}

/**
 * ADD RECORD: Tambah record baru (auto-increment id)
 * @param string $collection Nama collection
 * @param array $newRecord Data record baru (tanpa 'id', akan di-generate otomatis)
 * @return array Record yang baru ditambahkan (dengan id)
 */
function addRecord($collection, $newRecord) {
    $records = loadData($collection);
    
    // Auto-increment ID
    $maxId = 0;
    foreach ($records as $r) {
        if (isset($r['id']) && $r['id'] > $maxId) {
            $maxId = $r['id'];
        }
    }
    $newRecord['id'] = $maxId + 1;
    $newRecord['created_at'] = date('Y-m-d H:i:s');
    
    $records[] = $newRecord;
    saveData($collection, $records);
    
    return $newRecord;
}

/**
 * UPDATE RECORD: Update record berdasarkan id
 * @param string $collection Nama collection
 * @param int $id ID record
 * @param array $updates Data yang diupdate (partial update)
 * @return bool True jika berhasil
 */
function updateRecord($collection, $id, $updates) {
    $records = loadData($collection);
    $found = false;
    
    foreach ($records as &$record) {
        if (isset($record['id']) && $record['id'] == $id) {
            foreach ($updates as $key => $value) {
                $record[$key] = $value;
            }
            $record['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    unset($record);
    
    if ($found) {
        saveData($collection, $records);
    }
    return $found;
}

/**
 * DELETE RECORD: Hapus record berdasarkan id
 * @param string $collection Nama collection
 * @param int $id ID record
 * @return bool True jika berhasil dihapus
 */
function deleteRecord($collection, $id) {
    $records = loadData($collection);
    $filtered = [];
    $found = false;
    
    foreach ($records as $record) {
        if (isset($record['id']) && $record['id'] == $id) {
            $found = true;
        } else {
            $filtered[] = $record;
        }
    }
    
    if ($found) {
        saveData($collection, $filtered);
    }
    return $found;
}

/**
 * COUNT RECORDS: Hitung jumlah record
 * @param string $collection Nama collection
 * @return int Jumlah records
 */
function countRecords($collection) {
    return count(loadData($collection));
}

/**
 * PAGINATE: Ambil data dengan pagination
 * @param string $collection Nama collection
 * @param int $page Halaman ke-
 * @param int $perPage Jumlah per halaman
 * @return array ['records' => [...], 'total' => N, 'page' => N, 'total_pages' => N]
 */
function paginate($collection, $page = 1, $perPage = 10) {
    $records = loadData($collection);
    $total = count($records);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    
    return [
        'records' => array_slice($records, $offset, $perPage),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}

/**
 * LOGIN CHECK: Verifikasi login (khusus collection users)
 * @param string $username
 * @param string $password
 * @return array|null User data jika valid, null jika gagal
 */
function verifyLogin($username, $password) {
    $users = loadData('users');
    foreach ($users as $user) {
        if (
            isset($user['username']) && isset($user['password']) &&
            $user['username'] === $username && $user['password'] === $password
        ) {
            return $user;
        }
    }
    return null;
}
?>
