<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
$no = 1;

// Ambil parameter filter
$filter_class = isset($_GET['filter_class']) ? $_GET['filter_class'] : '';

if (isset($_POST['add_user'])) {
    $username = sanitize_input($_POST['username']);
    $name = sanitize_input($_POST['name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $class = sanitize_input($_POST['class']);
    $role = 'user';
    
    $stmt = $conn->prepare("INSERT INTO users (username, name, password, class, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $name, $password, $class, $role);
    
    if ($stmt->execute()) {
        echo "<div class='message success'>Berhasil Menambahkan user</div>";
    } else {
        echo "<div class='message error'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $username = sanitize_input($_POST['username']);
    $name = sanitize_input($_POST['name']);
    $class = sanitize_input($_POST['class']);
    
    $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, class = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $name, $class, $user_id);
    
    if ($stmt->execute()) {
        echo "<div class='message success'>Berhasil memperbarui user.</div>";
    } else {
        echo "<div class='message error'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $user_id);
    
    if ($stmt->execute()) {
        echo "<div class='message success'>Kata sandi berhasil diperbarui</div>";
    } else {
        echo "<div class='message error'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

if (isset($_POST['import_users'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        $firstRow = fgetcsv($handle, 1000, ",");
        if ($firstRow && (stripos(implode(",", $firstRow), "username") !== false || 
                          stripos(implode(",", $firstRow), "name") !== false)) {
        } else {
            rewind($handle);
        }
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 3) {
                $data = str_getcsv(implode(",", $data), ";");
            }
            
            if (count($data) >= 3) {
                $username = trim($data[0]);
                $name = trim($data[1]);
                $class = trim($data[2]);
                $password = isset($data[3]) ? trim($data[3]) : '123456'; 
                
                if (empty($username) || empty($name) || empty($class)) {
                    $skipped++;
                    $errors[] = "Baris skipped: Data tidak lengkap - " . implode(", ", $data);
                    continue;
                }
                
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $skipped++;
                    $errors[] = "Username sudah ada: " . $username;
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();
                
                $valid_classes = ['XI RPL', 'XI DKV', 'XI TKJ','XI PSPT','XI MP', 'XI BD', 'XI AK', 'XI ULW', 'XI ANM'];
                if (!in_array($class, $valid_classes)) {
                    $skipped++;
                    $errors[] = "Kelas tidak valid: " . $class . " untuk user " . $username;
                    continue;
                }
                if (strlen($password) < 4) {
                    $skipped++;
                    $errors[] = "Password terlalu pendek untuk user: " . $username . " (minimal 4 karakter)";
                    continue;
                }
           
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
               
                $stmt = $conn->prepare("INSERT INTO users (username, name, password, class, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->bind_param("ssss", $username, $name, $hashed_password, $class);
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                    $errors[] = "Error inserting: " . $username . " - " . $stmt->error;
                }
                $stmt->close();
            } else {
                $skipped++;
                $errors[] = "Format data tidak valid: " . implode(", ", $data);
            }
        }
        fclose($handle);
        
        $import_message = "Imported: $imported users, Skipped: $skipped users";
        if (!empty($errors)) {
            $import_message .= "<br><details style='margin-top: 10px;'><summary>Detail Errors:</summary><ul style='color: #dc2626;'>";
            foreach (array_slice($errors, 0, 10) as $error) { 
                $import_message .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            if (count($errors) > 10) {
                $import_message .= "<li>... and " . (count($errors) - 10) . " more errors</li>";
            }
            $import_message .= "</ul></details>";
        }
    } else {
        $import_error = "Tolong pilih file CSV yang benar";
    }
}

if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            echo "<div class='message success'>User berhasil dihapus</div>";
        } else {
            echo "<div class='message error'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}


$users_query = "SELECT * FROM users WHERE 1=1";
if ($filter_class) {
    $users_query .= " AND class = '" . $conn->real_escape_string($filter_class) . "'";
}
$users_query .= " ORDER BY class, name";

$users = $conn->query($users_query);


$classes_query = "SELECT DISTINCT class FROM users WHERE class IS NOT NULL AND class != '' ORDER BY class";
$classes_result = $conn->query($classes_query);
$classes = $classes_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kelola User - Examsis</title>
     <link rel="stylesheet" href="css/manage_user.css">
</head>
<body>
    
        <div class="header">
            <h1>Kelola User</h1>
            <a href="dashboard.php" class="back-btn">← Kembali ke Dashboard</a>
        </div>
        
        <?php if (isset($import_message)): ?>
            <div class="message success"><?php echo $import_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($import_error)): ?>
            <div class="message error"><?php echo $import_error; ?></div>
        <?php endif; ?>
     
    
        <div class="import">
            <h2>Import Users dari CSV</h2>
            <form method="post" enctype="multipart/form-data" id="csvForm">
                <div class="form-group">
                    <label for="csv_file">Pilih file CSV:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required 
                           class="file-input" onchange="showFileName()">
                    <span id="fileName" class="file-name">Belum ada file</span>
                </div>
                <button type="submit" name="import_users" id="importBtn" class="btn btn-primary">Import Users</button>
            </form>
        </div>
        
        <div class="section" id="addForm">
            <h2>Tambah User Baru</h2>
            <form method="post">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Nama:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Kata Sandi:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Kelas:</label>
                    <select id="class" name="class" required>
                        <option value="XI RPL">XI RPL</option>
                        <option value="XI DKV">XI DKV</option>
                        <option value="XI TKJ">XI TKJ</option>
                        <option value="XI PSPT">XI PSPT</option>
                        <option value="XI MP">XI MP</option>
                        <option value="XI BD">XI BD</option>
                        <option value="XI AK">XI AK</option>
                        <option value="XI ANM">XI ANM</option>
                        <option value="XI ULW">XI ULW</option>
                    </select>
                </div>
                
                <button type="submit" name="add_user" class="btn btn-primary">Tambahkan User</button>
            </form>
        </div>
        
        
        <div class="section hidden-form" id="editForm">
            <h2>Edit User</h2>
            <form method="post">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username:</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_name">Nama:</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_class">Kelas:</label>
                    <select id="edit_class" name="class" required>
                        <option value="XI RPL">XI RPL</option>
                        <option value="XI DKV">XI DKV</option>
                        <option value="XI TKJ">XI TKJ</option>
                        <option value="XI PSPT">XI PSPT</option>
                        <option value="XI MP">XI MP</option>
                        <option value="XI BD">XI BD</option>
                        <option value="XI AK">XI AK</option>
                        <option value="XI ANM">XI ANM</option>
                        <option value="XI ULW">XI ULW</option>
                    </select>
                </div>
                
                <button type="submit" name="edit_user" class="btn btn-primary">Perbarui User</button>
                <button type="button" onclick="showAddForm()" class="btn btn-secondary">Batalkan</button>
            </form>
        </div>
        
        <div class="section hidden-form" id="resetForm">
            <h2>Ubah Kata Sandi <span id="reset_user_name"></span></h2>
            <form method="post">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label for="new_password">Kata Sandi Baru:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-primary">Ubah Kata Sandi</button>
                <button type="button" onclick="showAddForm()" class="btn btn-secondary">Batalkan</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Daftar User (<?php echo $users->num_rows; ?> users)</h2>
            
      <div class="filter-section">
    <h3>pilih berdasarkan kelas</h3>
    <form method="get" class="filter-form">
        <label for="filter_class">Pilih Kelas:</label>
        <select name="filter_class" id="filter_class">
            <option value="">Semua Kelas</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo $class['class']; ?>" 
                    <?php echo ($filter_class == $class['class']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($class['class']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="submit" value="Terapkan Filter">
    </form>
</div>

            
            <?php if ($users->num_rows > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Peran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td>
                                <span class="class-badge"><?php echo htmlspecialchars($user['class']); ?></span>
                            </td>
                            <td>
                                <span class="role-badge <?php echo $user['role'] == 'admin' ? 'role-admin' : 'role-user'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="action-buttons">
                                        <button onclick="showEditForm(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', '<?php echo addslashes($user['name']); ?>', '<?php echo $user['class']; ?>')" 
                                                class="action-btn btn-edit">
                                            Edit
                                        </button>
                                        <button onclick="showResetForm(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>')" 
                                                class="action-btn btn-reset">
                                            Reset Password
                                        </button>
                                        <a href="?delete=<?php echo $user['id']; ?><?php echo $filter_class ? '&filter_class=' . urlencode($filter_class) : ''; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('Hapus user ini?')">
                                            Hapus
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-style: italic;">(User Saat Ini)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Tidak ada user ditemukan<?php echo $filter_class ? " untuk kelas $filter_class" : ""; ?>.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showEditForm(userId, username, name, classVal) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_class').value = classVal;
            
            document.getElementById('editForm').classList.remove('hidden-form');
            document.getElementById('addForm').style.display = 'none';
            document.getElementById('resetForm').classList.add('hidden-form');
            document.getElementById('csvForm').style.display = 'none';
            
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showResetForm(userId, name) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').innerText = name;
            
            document.getElementById('resetForm').classList.remove('hidden-form');
            document.getElementById('addForm').style.display = 'none';
            document.getElementById('editForm').classList.add('hidden-form');
            document.getElementById('csvForm').style.display = 'none';
            
            document.getElementById('resetForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showAddForm() {
            document.getElementById('editForm').classList.add('hidden-form');
            document.getElementById('resetForm').classList.add('hidden-form');
            document.getElementById('addForm').style.display = 'block';
            document.getElementById('csvForm').style.display = 'block';
            
            document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showFileName() {
            const input = document.getElementById('csv_file');
            const fileName = input.files.length > 0 ? input.files[0].name : "Belum ada file";
            document.getElementById('fileName').innerText = fileName;
        }
        
        window.onload = function() {
            document.getElementById('csv_file').value = "";
            document.getElementById('fileName').innerText = "Belum ada file";
        };
    </script>
</body>
</html>