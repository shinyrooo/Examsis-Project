<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
$no = 1;

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

$users = $conn->query("SELECT * FROM users ORDER BY class, name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Examsis</title>
         <link rel="stylesheet" href="css/manage_user.css">
</head>
<body>
    <div class="container">
       
        <div class="header">
            <h1>Manage Users</h1>
            <a href="dashboard.php" class="back-btn">← Kembali ke dashboard</a>
        </div>

    
        <div class="section" id="addForm">
            <h2>Tambah user baru</h2>
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
                    <label for="password">Kata sandi:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Class:</label>
                    <select id="class" name="class" required>
                        <option value="XI RPL">XI RPL</option>
                        <option value="XI DKV">XI DKV</option>
                        <option value="XI TKJ">XI TKJ</option>
                    </select>
                </div>
                
                <button type="submit" name="add_user" class="btn btn-primary">Tambahkan</button>
            </form>
        </div>
        
        <div class="section hidden-form" id="editForm">
            <h2>Edit user</h2>
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
                    <label for="edit_class">Class:</label>
                    <select id="edit_class" name="class" required>
                        <option value="XI RPL">XI RPL</option>
                        <option value="XI DKV">XI DKV</option>
                        <option value="XI TKJ">XI TKJ</option>
                    </select>
                </div>
                
                <button type="submit" name="edit_user" class="btn btn-primary">Perbarui</button>
                <button type="button" onclick="showAddForm()" class="btn btn-secondary">Batalkan</button>
            </form>
        </div>
    
        <div class="section hidden-form" id="resetForm">
            <h2>Ubah kata sandi <span id="reset_user_name"></span></h2>
            <form method="post">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="form-group">
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-primary">Ubah</button>
                <button type="button" onclick="showAddForm()" class="btn btn-secondary">Batalkan</button>
            </form>
        </div>

        <div class="section">
            <h2>User List (<?php echo $users->num_rows; ?> users)</h2>
        
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
                            <td><span class="class-badge"><?php echo htmlspecialchars($user['class']); ?></span></td>
                            <td>
                                <span class="role-badge <?php echo $user['role'] == 'admin' ? 'role-admin' : 'role-user'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="action-buttons">
                                        <button onclick="showEditForm(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', '<?php echo $user['name']; ?>', '<?php echo $user['class']; ?>')" 
                                                class="action-btn btn-edit">
                                            Edit
                                        </button>
                                        <button onclick="showResetForm(<?php echo $user['id']; ?>, '<?php echo $user['name']; ?>')" 
                                                class="action-btn btn-reset">
                                            Ubah Kata Sandi
                                        </button>
                                        <a href="?delete=<?php echo $user['id']; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('Delete this user?')">
                                            Hapus
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #6b7280; font-style: italic;">(Current User)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>pengguna tidak ditemukan.</p>
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
            
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showResetForm(userId, name) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').innerText = name;
            
            document.getElementById('resetForm').classList.remove('hidden-form');
            document.getElementById('addForm').style.display = 'none';
            document.getElementById('editForm').classList.add('hidden-form');
            
            document.getElementById('resetForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showAddForm() {
            document.getElementById('editForm').classList.add('hidden-form');
            document.getElementById('resetForm').classList.add('hidden-form');
            document.getElementById('addForm').style.display = 'block';
            
            document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>