<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM exam_results WHERE user_id = ? AND exam_id IN (SELECT id FROM exams WHERE class = ?)");
$stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['class']);
$stmt->execute();
$completed_exams_result = $stmt->get_result();
$completed_exam_ids = array();
while ($row = $completed_exams_result->fetch_assoc()) {
    $completed_exam_ids[] = $row['exam_id'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM exams WHERE class = ?");
$stmt->bind_param("s", $_SESSION['class']);
$stmt->execute();
$exams_result = $stmt->get_result();
$exams = $exams_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Exam - Examsis</title>
    <link rel="stylesheet" href="css/select_exam.css">
</head>
<body>
    <div class="container">
       
        <div class="header">
            <h1>Ujian yang tersedia</h1>
            <a href="dashboard.php" class="back-btn">←Kembali ke dashboard</a>
        </div>
        
   
        <div class="class-info">
            <h2>Class: <?php echo htmlspecialchars($_SESSION['class']); ?></h2>
        </div>
        
        <?php if (count($exams) > 0): ?>
            <div class="section">
                <table class="exams-table">
                    <thead>
                        <tr>
                            <th>Ujian</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong></td>
                            <td><?php echo $exam['duration_minutes']; ?> minutes</td>
                            <td>
                                <?php if (in_array($exam['id'], $completed_exam_ids)): ?>
                                    <span class="status-badge status-completed">Selesai</span>
                                <?php else: ?>
                                    <span class="status-badge status-available">Tersedia</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!in_array($exam['id'], $completed_exam_ids)): ?>
                                    <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" class="action-btn btn-primary">
                                        Kerjakan
                                    </a>
                                <?php else: ?>
                                    <a href="results.php" class="action-btn btn-secondary">
                                        Lihat hasil
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-exams">
                <h3>Tidak ada ujian yang tersedia</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>