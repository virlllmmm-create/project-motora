<?php
declare(strict_types=1);

function action_referer_same_origin(array $parts, array $server): bool
{
    if (!isset($parts['host'])) {
        return !isset($parts['scheme']) && !isset($parts['port']);
    }
    $scheme = !empty($server['HTTPS']) && strcasecmp((string) $server['HTTPS'], 'off') !== 0
        ? 'https' : 'http';
    $request = parse_url($scheme . '://' . (string) ($server['HTTP_HOST'] ?? ''));
    $refererScheme = strtolower((string) ($parts['scheme'] ?? $scheme));
    if (!$request || !isset($request['host']) || $refererScheme !== $scheme) {
        return false;
    }
    $requestPort = (int) ($request['port'] ?? ($scheme === 'https' ? 443 : 80));
    $refererPort = (int) ($parts['port'] ?? ($refererScheme === 'https' ? 443 : 80));
    return strcasecmp((string) $parts['host'], (string) $request['host']) === 0
        && $refererPort === $requestPort;
}

function handle_action(): void
{
    csrf_check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $action = (string) ($_POST['action'] ?? '');
    $user = current_user();
    $uid = (int) ($user['id_user'] ?? 0);
    $pdo = db();
    $uploadedFiles = [];
    if (!$user && !in_array($action, ['register', 'login', 'forgot', 'reset', 'logout'], true)) {
        go('login');
    }
    try {
        if (str_starts_with($action, 'community_')) {
            [$message, $returnPage, $returnParams] = community_handle_action($pdo, $uid, $user, $action, $_POST, $uploadedFiles);
            flash($message);
            go($returnPage, $returnParams);
        }
        switch ($action) {
            case 'register':
                $name = trim((string) ($_POST['nama'] ?? ''));
                $username = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                $city = trim((string) ($_POST['city'] ?? ''));
                $pass = (string) ($_POST['password'] ?? '');
                if (
                    mb_strlen($name) < 2 ||
                    !preg_match('/^[a-z0-9_]{3,30}$/', $username) ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    mb_strlen($city) < 2 ||
                    strlen($pass) < 10 ||
                    $pass !== ($_POST['confirm_password'] ?? '')
                ) {
                    throw new RuntimeException(
                        'Periksa nama, username (3–30 karakter), email, kota, dan password minimal 10 karakter.',
                    );
                }
                $q = $pdo->prepare(
                    'INSERT INTO `user`(nama,username,email,password_hash,role,city) VALUES(?,?,?,?,?,?)',
                );
                $q->execute([
                    $name,
                    $username,
                    $email,
                    password_hash($pass, PASSWORD_DEFAULT),
                    'rider',
                    $city,
                ]);
                $new = (int) $pdo->lastInsertId();
                ensure_settings($new);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $new;
                flash('Akun MOTORA siap. Selamat datang, ' . $name . '!');
                go('dashboard');
            case 'login':
                $q = $pdo->prepare(
                    'SELECT id_user,password_hash FROM `user` WHERE email=? OR username=? LIMIT 1',
                );
                $identity = trim((string) ($_POST['identity'] ?? ''));
                $q->execute([$identity, $identity]);
                $found = $q->fetch();
                if (
                    !$found ||
                    !password_verify((string) ($_POST['password'] ?? ''), $found['password_hash'])
                ) {
                    throw new RuntimeException('Email/username atau password tidak cocok.');
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $found['id_user'];
                ensure_settings((int) $found['id_user']);
                flash('Berhasil masuk.');
                go('dashboard');
            case 'logout':
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $p['path'],
                        $p['domain'],
                        $p['secure'],
                        $p['httponly'],
                    );
                }
                session_destroy();
                header('Location: index.php?page=login');
                exit();
            case 'forgot':
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                $q = $pdo->prepare('SELECT id_user FROM `user` WHERE email=?');
                $q->execute([$email]);
                $target = $q->fetchColumn();
                $message = 'Jika email akun terdaftar, instruksi reset lokal akan tersedia bila fitur pengembangan diaktifkan.';
                unset($_SESSION['local_reset_url']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash($message);
                    go('forgot');
                }
                if (local_reset_links_enabled()) {
                    if ($target) {
                        $token = create_password_reset_token($pdo, (int) $target);
                        if ($token !== null) {
                            $_SESSION['local_reset_url'] = url('reset', ['token' => $token]);
                        }
                    } else {
                        usleep(random_int(75000, 150000));
                    }
                }
                flash($message);
                go('forgot');
            case 'reset':
                if (!local_reset_links_enabled()) {
                    throw new RuntimeException('Tautan reset tidak valid atau sudah kedaluwarsa.');
                }
                $token = (string) ($_POST['token'] ?? '');
                $pass = (string) ($_POST['password'] ?? '');
                if (strlen($pass) < 10 || $pass !== ($_POST['confirm_password'] ?? '')) {
                    throw new RuntimeException(
                        'Password harus minimal 10 karakter dan kedua isian harus sama.',
                    );
                }
                $completed = complete_password_reset(
                    $pdo,
                    $token,
                    password_hash($pass, PASSWORD_DEFAULT),
                );
                if (!$completed) {
                    throw new RuntimeException('Tautan reset tidak valid atau sudah kedaluwarsa.');
                }
                unset($_SESSION['local_reset_url']);
                flash('Password berhasil diperbarui. Silakan masuk.');
                go('login');
            case 'motor_add':
            case 'motor_edit':
                $id = (int) ($_POST['id'] ?? 0);
                $brand = trim((string) ($_POST['brand'] ?? ''));
                $model = trim((string) ($_POST['model'] ?? ''));
                if (!$brand || !$model) {
                    throw new RuntimeException('Merk dan model wajib diisi.');
                }
                $yearInput = trim((string) ($_POST['year'] ?? ''));
                $year =
                    $yearInput === ''
                        ? null
                        : filter_var($yearInput, FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 1900, 'max_range' => (int) date('Y') + 1],
                        ]);
                if ($yearInput !== '' && $year === false) {
                    throw new RuntimeException(
                        'Tahun motor harus antara 1900 dan ' . ((int) date('Y') + 1) . '.',
                    );
                }
                $values = [
                    $brand,
                    $model,
                    trim((string) ($_POST['type'] ?? '')),
                    $year ?: null,
                ];
                $old = null;
                $photo = null;
                $photoFiles = $_FILES['photos'] ?? null;
                if ($photoFiles !== null && (!is_array($photoFiles) || !is_array($photoFiles['name'] ?? null) || count($photoFiles['name']) > 20)) {
                    throw new RuntimeException('Pilih maksimal 20 foto tambahan sekali unggah.');
                }
                $pdo->beginTransaction();
                try {
                    $userLock = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE');
                    $userLock->execute([$uid]);
                    if (!$userLock->fetchColumn()) {
                        throw new RuntimeException('Pengguna tidak ditemukan.');
                    }
                    if ($action === 'motor_add') {
                        $photo = tracked_upload_image($uploadedFiles, 'main_photo', 'motor');
                        $hasMotor = $pdo->prepare('SELECT 1 FROM motorcycles WHERE user_id=? LIMIT 1');
                        $hasMotor->execute([$uid]);
                        $primary = !empty($_POST['is_primary']) || !$hasMotor->fetchColumn();
                        if ($primary) {
                            $pdo->prepare('UPDATE motorcycles SET is_primary=0 WHERE user_id=?')->execute([$uid]);
                        }
                        $q = $pdo->prepare(
                            'INSERT INTO motorcycles(user_id,brand,model,type,year,main_photo,is_primary) VALUES(?,?,?,?,?,?,?)',
                        );
                        $q->execute(array_merge([$uid], $values, [$photo, (int) $primary]));
                        $id = (int) $pdo->lastInsertId();
                    } else {
                        $q = $pdo->prepare(
                            'SELECT main_photo,is_primary FROM motorcycles WHERE id=? AND user_id=? FOR UPDATE',
                        );
                        $q->execute([$id, $uid]);
                        $oldRow = $q->fetch();
                        if (!$oldRow) {
                            throw new RuntimeException('Motor tidak ditemukan.');
                        }
                        $old = $oldRow['main_photo'];
                        $photo = tracked_upload_image($uploadedFiles, 'main_photo', 'motor');
                        if (!empty($_POST['is_primary'])) {
                            $pdo->prepare('UPDATE motorcycles SET is_primary=0 WHERE user_id=?')->execute([$uid]);
                        }
                        $sql =
                            'UPDATE motorcycles SET brand=?,model=?,type=?,year=?,is_primary=?' .
                            ($photo ? ',main_photo=?' : '') .
                            ' WHERE id=? AND user_id=?';
                        $args = array_merge($values, [(int) !empty($_POST['is_primary'])]);
                        if ($photo) {
                            $args[] = $photo;
                        }
                        $args[] = $id;
                        $args[] = $uid;
                        $pdo->prepare($sql)->execute($args);
                        if (empty($_POST['is_primary']) && !empty($oldRow['is_primary'])) {
                            $next = $pdo->prepare(
                                'SELECT id FROM motorcycles WHERE user_id=? AND id<>? ORDER BY created_at,id LIMIT 1',
                            );
                            $next->execute([$uid, $id]);
                            $nextId = $next->fetchColumn();
                            $pdo->prepare('UPDATE motorcycles SET is_primary=1 WHERE id=? AND user_id=?')->execute([
                                $nextId ?: $id,
                                $uid,
                            ]);
                        }
                    }
                    if ($photo) {
                        if ($action === 'motor_edit' && $old && $old !== $photo) {
                            $oldPhotoRef = $pdo->prepare('SELECT 1 FROM motorcycle_photos WHERE motorcycle_id=? AND path=? LIMIT 1');
                            $oldPhotoRef->execute([$id, $old]);
                            if (!$oldPhotoRef->fetchColumn()) {
                                $pdo->prepare('INSERT INTO motorcycle_photos(motorcycle_id,path) VALUES(?,?)')->execute([$id, $old]);
                            }
                        }
                        $photoInsert = $pdo->prepare('INSERT INTO motorcycle_photos(motorcycle_id,path) VALUES(?,?)');
                        $photoInsert->execute([$id, $photo]);
                        if ($photoInsert->rowCount() !== 1) {
                            throw new RuntimeException('Foto utama tidak tercatat di album motor.');
                        }
                    }
                    if ($photoFiles !== null) {
                        $files = $photoFiles;
                        foreach ($files['name'] as $i => $unused) {
                            foreach (['error', 'tmp_name', 'size', 'type', 'name'] as $key) {
                                if (!isset($files[$key][$i]) || is_array($files[$key][$i])) {
                                    throw new RuntimeException('Daftar foto tambahan tidak lengkap.');
                                }
                            }
                            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                                continue;
                            }
                            $_FILES['_one'] = array_intersect_key($files, array_flip(['name', 'type', 'tmp_name', 'error', 'size']));
                            foreach (array_keys($_FILES['_one']) as $key) {
                                $_FILES['_one'][$key] = $files[$key][$i];
                            }
                            try {
                                $path = tracked_upload_image($uploadedFiles, '_one', 'motor');
                                if ($path !== null) {
                                    $photoInsert = $pdo->prepare('INSERT INTO motorcycle_photos(motorcycle_id,path) VALUES(?,?)');
                                    $photoInsert->execute([$id, $path]);
                                    if ($photoInsert->rowCount() !== 1) {
                                        throw new RuntimeException('Foto tambahan tidak tersimpan di album motor.');
                                    }
                                }
                            } finally {
                                unset($_FILES['_one']);
                            }
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $uploadedFiles = [];
                flash('Data motor tersimpan.');
                go('garage');
            case 'motor_delete':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->beginTransaction();
                try {
                    $ownerLock = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE');
                    $ownerLock->execute([$uid]);
                    $lock = $pdo->prepare('SELECT main_photo,is_primary FROM motorcycles WHERE id=? AND user_id=? FOR UPDATE');
                    $lock->execute([$id, $uid]);
                    $motor = $lock->fetch();
                    if (!$motor) {
                        throw new RuntimeException('Motor tidak ditemukan.');
                    }
                    $photos = $pdo->prepare('SELECT path FROM motorcycle_photos WHERE motorcycle_id=? FOR UPDATE');
                    $photos->execute([$id]);
                    $paths = $photos->fetchAll(PDO::FETCH_COLUMN);
                    $pdo->prepare('DELETE FROM motorcycles WHERE id=? AND user_id=?')->execute([
                        $id,
                        $uid,
                    ]);
                    if (!empty($motor['is_primary'])) {
                        $next = $pdo->prepare(
                            'SELECT id FROM motorcycles WHERE user_id=? ORDER BY created_at,id LIMIT 1',
                        );
                        $next->execute([$uid]);
                        $nextId = $next->fetchColumn();
                        if ($nextId) {
                            $pdo->prepare('UPDATE motorcycles SET is_primary=1 WHERE id=?')->execute([
                                $nextId,
                            ]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $allPaths = array_unique(array_filter(array_merge([$motor['main_photo']], $paths)));
                foreach ($allPaths as $path) {
                    delete_upload_if_unreferenced($pdo, $path);
                }
                flash('Motor dihapus.');
                go('garage');
            case 'photo_delete':
                $id = (int) ($_POST['photo_id'] ?? 0);
                $pdo->beginTransaction();
                try {
                    $ownerLock = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE');
                    $ownerLock->execute([$uid]);
                    $lock = $pdo->prepare('SELECT p.path,m.id FROM motorcycle_photos p JOIN motorcycles m ON m.id=p.motorcycle_id WHERE p.id=? AND m.user_id=? FOR UPDATE');
                    $lock->execute([$id, $uid]);
                    $row = $lock->fetch();
                    if (!$row) {
                        throw new RuntimeException('Foto tidak ditemukan.');
                    }
                    $pdo->prepare('DELETE FROM motorcycle_photos WHERE id=?')->execute([$id]);
                    $pdo->prepare(
                        'UPDATE motorcycles SET main_photo=NULL WHERE id=? AND main_photo=?',
                    )->execute([$row['id'], $row['path']]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                delete_upload_if_unreferenced($pdo, $row['path']);
                flash('Foto dihapus.');
                go('motorcycle', ['id' => $row['id']]);
            case 'friend_request':
                $to = (int) ($_POST['user_id'] ?? 0);
                if ($to === $uid || $to < 1) {
                    throw new RuntimeException('Permintaan ini tidak valid.');
                }
                $acceptedRequestId = null;
                $createdRequestId = null;
                $pdo->beginTransaction();
                try {
                    lock_user_pair($pdo, $uid, $to);
                    if (is_blocked($uid, $to) || !can_view($to, 'profile_visibility') || (int) setting($to, 'is_searchable', 1) !== 1) {
                        throw new RuntimeException('Permintaan teman tidak dapat dikirim karena interaksi dibatasi.');
                    }
                    $reverse = $pdo->prepare("SELECT id FROM friend_requests WHERE sender_id=? AND receiver_id=? AND status='pending' FOR UPDATE");
                    $reverse->execute([$to, $uid]);
                    $reverseRequestId = (int) $reverse->fetchColumn();
                    if (are_friends($uid, $to)) {
                        throw new RuntimeException('Kalian sudah berteman.');
                    }
                    if ($reverseRequestId > 0) {
                        $pdo->prepare("UPDATE friend_requests SET status='accepted' WHERE id=? AND status='pending'")->execute([$reverseRequestId]);
                        $acceptedRequestId = $reverseRequestId;
                    } else {
                        $existing = $pdo->prepare('SELECT id,status FROM friend_requests WHERE sender_id=? AND receiver_id=? FOR UPDATE');
                        $existing->execute([$uid, $to]);
                        $prior = $existing->fetch();
                        if ($prior && $prior['status'] === 'blocked') {
                            throw new RuntimeException('Permintaan teman tidak dapat dikirim.');
                        }
                        if ($prior && $prior['status'] === 'pending') {
                            throw new RuntimeException('Permintaan teman Anda masih menunggu jawaban.');
                        }
                        $pdo->prepare("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES(?,?,'pending') ON DUPLICATE KEY UPDATE status='pending',updated_at=NOW()")->execute([$uid, $to]);
                        $request = $pdo->prepare('SELECT id FROM friend_requests WHERE sender_id=? AND receiver_id=?');
                        $request->execute([$uid, $to]);
                        $createdRequestId = (int) $request->fetchColumn();
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                if ($acceptedRequestId !== null) {
                    notify($to, $uid, 'friend_accepted', $acceptedRequestId, $user['nama'] . ' menerima permintaan teman.', 'notify_friend_requests');
                    flash('Permintaan teman diterima otomatis.');
                } else {
                    notify($to, $uid, 'friend_request', $createdRequestId, $user['nama'] . ' ingin berteman dengan Anda.', 'notify_friend_requests');
                    flash('Permintaan teman dikirim.');
                }
                go('profile', ['id' => $to]);
            case 'friend_block':
                $other = (int) ($_POST['user_id'] ?? 0);
                if ($other < 1 || $other === $uid) {
                    throw new RuntimeException('Pengguna tidak valid.');
                }
                $pdo->beginTransaction();
                try {
                    $target = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=?');
                    $target->execute([$other]);
                    if (!$target->fetchColumn()) {
                        throw new RuntimeException('Rider tidak ditemukan.');
                    }
                    block_user($pdo, $uid, $other);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                flash('Rider diblokir. Permintaan teman dan percakapan baru tidak dapat dimulai.');
                go('profile', ['id' => $other]);
            case 'friend_action':
                $id = (int) ($_POST['request_id'] ?? 0);
                $op = (string) ($_POST['op'] ?? '');
                $pdo->beginTransaction();
                try {
                    $request = $pdo->prepare(
                        'SELECT * FROM friend_requests WHERE id=? AND (receiver_id=? OR sender_id=?)',
                    );
                    $request->execute([$id, $uid, $uid]);
                    $relation = $request->fetch();
                    if (!$relation) {
                        throw new RuntimeException('Permintaan tidak ditemukan.');
                    }
                    $other = (int) $relation['sender_id'] === $uid
                        ? (int) $relation['receiver_id']
                        : (int) $relation['sender_id'];
                    $acceptedRequest = false;
                    lock_user_pair($pdo, $uid, $other);
                    $currentQuery = $pdo->prepare(
                        'SELECT * FROM friend_requests WHERE id=? AND (receiver_id=? OR sender_id=?) FOR UPDATE',
                    );
                    $currentQuery->execute([$id, $uid, $uid]);
                    $current = $currentQuery->fetch();
                    if (!$current) {
                        throw new RuntimeException('Status pertemanan sudah berubah. Muat ulang halaman.');
                    }
                    $isSender = (int) $current['sender_id'] === $uid;
                    $isReceiver = (int) $current['receiver_id'] === $uid;
                    $blocked = is_blocked($uid, $other);

                    if ($op === 'accept' && $isReceiver && $current['status'] === 'pending' && !$blocked) {
                        $pdo->prepare("UPDATE friend_requests SET status='accepted' WHERE id=? AND status='pending'")->execute([$id]);
                        $acceptedRequest = true;
                    } elseif ($op === 'reject' && $isReceiver && $current['status'] === 'pending' && !$blocked) {
                        $pdo->prepare("UPDATE friend_requests SET status='rejected' WHERE id=? AND status='pending'")->execute([$id]);
                    } elseif ($op === 'cancel' && $isSender && $current['status'] === 'pending' && !$blocked) {
                        $pdo->prepare("DELETE FROM friend_requests WHERE id=? AND status='pending'")->execute([$id]);
                    } elseif ($op === 'remove' && $current['status'] === 'accepted') {
                        $pdo->prepare("DELETE FROM friend_requests WHERE id=? AND status='accepted'")->execute([$id]);
                    } elseif ($op === 'unblock' && $current['status'] === 'blocked' && $isSender) {
                        $pdo->prepare("DELETE FROM friend_requests WHERE id=? AND sender_id=? AND status='blocked'")->execute([$id, $uid]);
                    } elseif ($op === 'block' && !($current['status'] === 'blocked' && $isSender)) {
                        block_user($pdo, $uid, $other);
                    } else {
                        throw new RuntimeException('Aksi pertemanan tidak berlaku atau interaksi dibatasi.');
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                if ($acceptedRequest) {
                    notify(
                        $other,
                        $uid,
                        'friend_accepted',
                        $id,
                        $user['nama'] . ' menerima permintaan teman.',
                        'notify_friend_requests',
                    );
                }
                flash('Status pertemanan diperbarui.');
                go('friends');
            case 'profile_save':
                $profileValues = [];
                $profileLabels = [
                    'nama' => 'Nama',
                    'username' => 'Username',
                    'email' => 'Email',
                    'city' => 'Kota',
                    'region' => 'Daerah',
                    'bio' => 'Bio',
                ];
                $invalidProfileField = null;
                foreach ($profileLabels as $field => $label) {
                    $submitted = $_POST[$field] ?? '';
                    if (!is_string($submitted)) {
                        $invalidProfileField ??= $label;
                        $submitted = '';
                    }
                    $profileValues[$field] = trim($submitted);
                }
                $profileValues['username'] = mb_strtolower($profileValues['username']);
                $profileValues['email'] = mb_strtolower($profileValues['email']);
                $_SESSION['profile_draft'] = ['user_id' => $uid, 'values' => $profileValues];
                if ($invalidProfileField !== null) {
                    throw new RuntimeException($invalidProfileField . ' harus diisi dengan teks yang valid.');
                }
                $name = $profileValues['nama'];
                $username = $profileValues['username'];
                $email = $profileValues['email'];
                if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                    throw new RuntimeException('Nama harus berisi 2–100 karakter.');
                }
                if (!preg_match('/^[a-z0-9_]{3,30}$/D', $username)) {
                    throw new RuntimeException('Username harus berisi 3–30 karakter: huruf, angka, atau garis bawah.');
                }
                if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Masukkan email yang valid, maksimal 150 karakter.');
                }
                foreach (['city' => 100, 'region' => 100, 'bio' => 1000] as $field => $limit) {
                    if (mb_strlen($profileValues[$field]) > $limit) {
                        throw new RuntimeException($profileLabels[$field] . ' maksimal ' . $limit . ' karakter.');
                    }
                }
                $duplicateQuery = $pdo->prepare(
                    'SELECT username,email FROM `user` WHERE id_user<>? AND (username=? OR email=?)',
                );
                $duplicateQuery->execute([$uid, $username, $email]);
                foreach ($duplicateQuery->fetchAll() as $duplicate) {
                    if (strcasecmp((string) $duplicate['username'], $username) === 0) {
                        throw new RuntimeException('Username ini sudah digunakan rider lain. Pilih username lain.');
                    }
                    if (strcasecmp((string) $duplicate['email'], $email) === 0) {
                        throw new RuntimeException('Email ini sudah digunakan akun lain. Gunakan email lain.');
                    }
                }
                $photo = null;
                $oldPhotoPath = null;
                try {
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare('SELECT profile_photo FROM `user` WHERE id_user=? FOR UPDATE');
                    $lock->execute([$uid]);
                    $oldPhotoPath = $lock->fetchColumn();
                    if ($oldPhotoPath === false) {
                        throw new RuntimeException('Akun tidak ditemukan. Masuk kembali untuk mengedit profil.');
                    }
                    $photo = tracked_upload_image($uploadedFiles, 'profile_photo', 'profile');
                    $sql =
                        'UPDATE `user` SET nama=?,username=?,email=?,city=?,region=?,bio=?' .
                        ($photo ? ',profile_photo=?' : '') .
                        ' WHERE id_user=?';
                    $args = [
                        $name,
                        $username,
                        $email,
                        $profileValues['city'] !== '' ? $profileValues['city'] : null,
                        $profileValues['region'] !== '' ? $profileValues['region'] : null,
                        $profileValues['bio'] !== '' ? $profileValues['bio'] : null,
                    ];
                    if ($photo) {
                        $args[] = $photo;
                    }
                    $args[] = $uid;
                    $pdo->prepare($sql)->execute($args);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                unset($_SESSION['profile_draft']);
                if ($photo) {
                    mark_upload_persisted($uploadedFiles, $photo);
                    if ($oldPhotoPath && $oldPhotoPath !== $photo) {
                        delete_upload_if_unreferenced($pdo, $oldPhotoPath);
                    }
                }
                flash('Profil akun diperbarui.');
                go('settings');
            case 'password_change':
                $q = $pdo->prepare('SELECT password_hash FROM `user` WHERE id_user=?');
                $q->execute([$uid]);
                if (
                    !password_verify(
                        (string) ($_POST['current_password'] ?? ''),
                        (string) $q->fetchColumn(),
                    )
                ) {
                    throw new RuntimeException('Password saat ini tidak cocok.');
                }
                $p = (string) ($_POST['new_password'] ?? '');
                if (strlen($p) < 10 || $p !== ($_POST['confirm_password'] ?? '')) {
                    throw new RuntimeException('Password baru minimal 10 karakter dan harus sama.');
                }
                $pdo->prepare('UPDATE `user` SET password_hash=? WHERE id_user=?')->execute([
                    password_hash($p, PASSWORD_DEFAULT),
                    $uid,
                ]);
                flash('Password diperbarui.');
                go('settings');
            case 'settings_save':
                $allowed = ['public', 'friends', 'private'];
                $pv = (string) ($_POST['profile_visibility'] ?? 'public');
                $mv = (string) ($_POST['motorcycles_visibility'] ?? 'public');
                $gv = (string) ($_POST['gallery_visibility'] ?? 'public');
                if (
                    !in_array($pv, $allowed, true) ||
                    !in_array($mv, $allowed, true) ||
                    !in_array($gv, $allowed, true)
                ) {
                    throw new RuntimeException('Pengaturan privasi tidak valid.');
                }
                ensure_settings($uid);
                $pdo->prepare(
                    'UPDATE user_settings SET profile_visibility=?,motorcycles_visibility=?,gallery_visibility=?,show_city=?,is_searchable=?,notify_friend_requests=?,notify_community=?,notify_messages=?,notify_activity=? WHERE user_id=?',
                )->execute([
                    $pv,
                    $mv,
                    $gv,
                    (int) !empty($_POST['show_city']),
                    (int) !empty($_POST['is_searchable']),
                    (int) !empty($_POST['notify_friend_requests']),
                    (int) !empty($_POST['notify_community']),
                    (int) !empty($_POST['notify_messages']),
                    (int) !empty($_POST['notify_activity']),
                    $uid,
                ]);
                flash('Privasi dan notifikasi diperbarui.');
                go('settings');
            case 'message_send':
                $to = (int) ($_POST['to_user'] ?? 0);
                $conversation = (int) ($_POST['conversation_id'] ?? 0);
                $body = trim((string) ($_POST['body'] ?? ''));
                if (!$body || mb_strlen($body) > 4000) {
                    throw new RuntimeException('Pesan harus berisi 1–4000 karakter.');
                }
                $conversationId = 0;
                if ($conversation) {
                    $pdo->beginTransaction();
                    try {
                        $membersQuery = $pdo->prepare('SELECT user_id FROM conversation_members WHERE conversation_id=? ORDER BY user_id FOR UPDATE');
                        $membersQuery->execute([$conversation]);
                        $memberIds = array_map('intval', $membersQuery->fetchAll(PDO::FETCH_COLUMN));
                        if (count($memberIds) !== 2 || !in_array($uid, $memberIds, true)) {
                            throw new RuntimeException('Percakapan tidak ditemukan atau strukturnya tidak valid.');
                        }
                        $other = $memberIds[0] === $uid ? $memberIds[1] : $memberIds[0];
                        lock_user_pair($pdo, $uid, $other);
                        if (is_blocked($uid, $other) || !can_view($other, 'profile_visibility')) {
                            throw new RuntimeException('Pesan tidak dapat dikirim karena interaksi dengan rider ini dibatasi.');
                        }
                        $pdo->prepare(
                            'INSERT INTO messages(conversation_id,sender_id,body) VALUES(?,?,?)',
                        )->execute([$conversation, $uid, $body]);
                        notify($other, $uid, 'message', $conversation, 'Pesan baru dari ' . $user['nama'] . '.', 'notify_messages', $pdo);
                        $conversationId = $conversation;
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } else {
                    if (!$to || $to === $uid) {
                        throw new RuntimeException('Penerima pesan tidak valid.');
                    }
                    if (!can_view($to, 'profile_visibility') || is_blocked($uid, $to)) {
                        throw new RuntimeException(
                            'Profil rider tersebut tidak menerima pesan baru.',
                        );
                    }
                    $block = $pdo->prepare(
                        "SELECT 1 FROM friend_requests WHERE status='blocked' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))",
                    );
                    $block->execute([$uid, $to, $to, $uid]);
                    if ($block->fetchColumn()) {
                        throw new RuntimeException('Pesan tidak dapat dikirim.');
                    }
                    $pdo->beginTransaction();
                    try {
                        lock_user_pair($pdo, $uid, $to);
                        if (is_blocked($uid, $to) || !can_view($to, 'profile_visibility')) {
                            throw new RuntimeException('Pesan tidak dapat dikirim karena interaksi dengan rider ini dibatasi.');
                        }
                        $conversation = find_or_create_direct_conversation($pdo, $uid, $to);
                        $pdo->prepare(
                            'INSERT INTO messages(conversation_id,sender_id,body) VALUES(?,?,?)',
                        )->execute([$conversation, $uid, $body]);
                        notify($to, $uid, 'message', $conversation, 'Pesan baru dari ' . $user['nama'] . '.', 'notify_messages', $pdo);
                        $conversationId = $conversation;
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }
                go('messages', ['conversation' => $conversationId]);
            default:
                throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($uploadedFiles as $uploadedFile) {
            delete_upload($uploadedFile);
        }
        $uploadedFiles = [];
        if ((string) $e->getCode() === '23000') {
            flash(
                'Email atau username sudah digunakan, atau data terkait tidak tersedia.',
                'error',
            );
        } else {
            error_log($e->getMessage());
            flash('Terjadi kesalahan database. Periksa setup dan coba lagi.', 'error');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($uploadedFiles as $uploadedFile) {
            delete_upload($uploadedFile);
        }
        flash($e->getMessage(), 'error');
    }
    if (in_array($action, ['profile_save', 'settings_save', 'password_change'], true)) {
        go('settings');
    }
    $fallback = $user ? 'dashboard' : 'login';
    $returnPage = $fallback;
    $returnParams = [];
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $parts = $referer ? parse_url($referer) : false;
    $sameHost = $parts && action_referer_same_origin($parts, $_SERVER);
    $sameScript =
        $parts &&
        (!isset($parts['path']) ||
            rtrim((string) $parts['path'], '/') ===
                rtrim((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/'));
    if ($sameHost && $sameScript) {
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $candidate = $query['page'] ?? $fallback;
        $allowed = [
            'login',
            'register',
            'forgot',
            'reset',
            'dashboard',
            'search',
            'communities',
            'community',
            'community_settings',
            'garage',
            'motorcycle',
            'profile',
            'friends',
            'messages',
            'notifications',
            'settings',
        ];
        if (is_string($candidate) && in_array($candidate, $allowed, true)) {
            $returnPage = $candidate;
            foreach (['id', 'edit', 'p', 'p_friends', 'p_incoming', 'p_outgoing', 'p_blocked', 'conversation', 'to'] as $key) {
                if (
                    isset($query[$key]) &&
                    is_scalar($query[$key]) &&
                    ctype_digit((string) $query[$key])
                ) {
                    $returnParams[$key] = (int) $query[$key];
                }
            }
            foreach (['q', 'city', 'region', 'type', 'brand', 'model', 'email'] as $key) {
                if (isset($query[$key]) && is_string($query[$key])) {
                    $returnParams[$key] = mb_substr($query[$key], 0, 100);
                }
            }
        }
    }
    go($returnPage, $returnParams);
}
