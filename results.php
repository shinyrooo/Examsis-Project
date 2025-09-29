<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT er.*, e.exam_name, e.class FROM exam_results er 
                       JOIN exams e ON er.exam_id = e.id 
                       WHERE er.user_id = ? ORDER BY er.completed_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - Examsis</title>
     <link rel="stylesheet" href="css/results.css">

</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Jawabanku</h1>
            <a href="dashboard.php" class="back-btn">← Kembali ke dashboard</a>
        </div>
        
        <?php if (count($results) > 0): ?>
            <div class="section">
                <h2>Hasil ujian</h2>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Ujian</th>
                            <th>Kelas</th>
                            <th>Nilai</th>
                            <th>Total soal</th>
                            <th>Persen</th>
                            <th>Selesai pada</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): 
                            $percentage = round(($result['score'] / $result['total_questions']) * 100, 2);
                            $percentageClass = 'percentage-high';
                            if ($percentage < 60) $percentageClass = 'percentage-low';
                            elseif ($percentage < 80) $percentageClass = 'percentage-medium';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($result['exam_name']); ?></strong></td>
                            <td>
                                <span class="class-badge"><?php echo htmlspecialchars($result['class']); ?></span>
                            </td>
                            <td><strong><?php echo $result['score']; ?></strong></td>
                            <td><?php echo $result['total_questions']; ?></td>
                            <td>
                                <span class="percentage <?php echo $percentageClass; ?>">
                                    <?php echo $percentage; ?>%
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($result['completed_at'])); ?></td>
                            <td>
                                <button class="btn btn-primary" 
                                  onclick="window.location.href='detail_user_answers.php?user_id=<?php echo $_SESSION['user_id']; ?>&exam_id=<?php echo $result['exam_id']; ?>'">
                                   Lihat Detail
                                </button>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php else: ?>
            <div class="no-results">
                <h3>Tidak ada hasil ujian yang ditemukan.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>