<?php 
include 'config.php';
$no = 1;
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['add_exam'])) {
    $exam_name = sanitize_input($_POST['exam_name']);
    $class = sanitize_input($_POST['class']);
    $duration = intval($_POST['duration']);
    
    $stmt = $conn->prepare("INSERT INTO exams (exam_name, class, duration_minutes) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $exam_name, $class, $duration);
    
    if ($stmt->execute()) {
        $message = "Ujian berhsil ditambahkan!";
    } else {
        $error = "Ada yang salah: " . $stmt->error;
    }
    $stmt->close();
}

if (isset($_GET['delete_exam'])) {
    $exam_id = intval($_GET['delete_exam']);
    $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
    $stmt->bind_param("i", $exam_id);
    
    if ($stmt->execute()) {
        $message = "Ujian berhasil dihapus!";
    } else {
        $error = "Ada yang salah: " . $stmt->error;
    }
    $stmt->close();
}

$exams = $conn->query("SELECT * FROM exams ORDER BY class, exam_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - Examsis</title>
    <link rel="stylesheet" href="css/manage_exams.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kelola Ujian</h1>
            <a href="dashboard.php" class="back-btn">← kembali ke dashboard</a>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>Tambah Ujian</h2>
            <form method="post">
                <div class="form-group">
                    <label for="exam_name">Nama ujian:</label>
                    <input type="text" id="exam_name" name="exam_name" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Kelas:</label>
                    <select id="class" name="class" required>
                        <option value="XI RPL">XI RPL</option>
                        <option value="XI DKV">XI DKV</option>
                        <option value="XI TKJ">XI TKJ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="duration">Durasi (minutes):</label>
                    <input type="number" id="duration" name="duration" value="60" min="1" required>
                </div>
                
                <button type="submit" name="add_exam" class="submit-btn">Tambahkan</button>
            </form>
        </div>
        
        <div class="table-section">
            <h2>Ujian yang tersedia</h2>
            
            <?php if ($exams->num_rows > 0): ?>
                <table class="exams-table">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>Nama ujain</th>
                            <th>Kelas</th>
                            <th>Durasi</th>
                            <th>Dibuat pada</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exam = $exams->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($exam['class']); ?> </td>
                            <td><?php echo $exam['duration_minutes']; ?> Menit</td>
                            <td><?php echo date('M j, Y g:i A', strtotime(datetime: $exam['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="manage_questions.php?exam_id=<?php echo $exam['id']; ?>" class="action-btn btn-primary">
                                        Kelola pertanyaan
                                    </a>
                                    <a href="?delete_exam=<?php echo $exam['id']; ?>" 
                                       class="action-btn btn-danger"
                                       onclick="return confirm('serius mau menghapus ujian ini? semua pertanyaan juga akan ikut terhapus')">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-exams">
                    <h3>Tidak ada ujian yang tersedia</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDelete() {
            return confirm('serius mau menghapus ujian ini? semua pertanyaan juga akan ikut terhapus');
        }
    </script>
</body>
</html>