<?php
include 'config.php';

$no = 1;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}
$classes_query = "SELECT DISTINCT class FROM users WHERE role = 'user' ORDER BY class";
$classes_result = $conn->query($classes_query);
$classes = $classes_result->fetch_all(MYSQLI_ASSOC);


$exams_query = "SELECT DISTINCT id, exam_name FROM exams ORDER BY exam_name";
$exams_result = $conn->query($exams_query);
$exams = $exams_result->fetch_all(MYSQLI_ASSOC);


$filter_class = isset($_GET['class']) ? $_GET['class'] : '';
$filter_exam  = isset($_GET['exam']) ? $_GET['exam'] : '';

$query = "SELECT er.*, u.username, u.name, u.class, e.exam_name 
          FROM exam_results er 
          JOIN users u ON er.user_id = u.id 
          JOIN exams e ON er.exam_id = e.id 
          WHERE 1=1";

$params = [];
$types = '';

if ($filter_class) {
    $query .= " AND u.class = ?";
    $params[] = $filter_class;
    $types .= 's';
}

if ($filter_exam) {
    $query .= " AND e.id = ?";
    $params[] = $filter_exam;
    $types .= 'i';
}

$query .= " ORDER BY u.class, u.username, er.completed_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hasil ujian Untuk Admin</title>
    <link rel="stylesheet" href="css/admin_results.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Hasil ujian</h2>
            <a href="dashboard.php" class="back-btn">← Kembali ke dashboard</a>
        </header>
        
        <div class="filter-section">
            <h3>Pilih berdasarkan kelas</h3>
            <form method="get" class="filter-form">
                <select name="class">
                    <option value="">Semua kelas</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class']; ?>" <?php echo ($filter_class == $class['class']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="exam" value="<?php echo $filter_exam; ?>">
                <input type="submit" value="Terapkan">
            </form>
        </div>

        <div class="filter-section">
            <h3>Pilih berdasarkan ujian</h3>
            <form method="get" class="filter-form">
                <select name="exam">
                    <option value="">Semua ujian</option>
                    <?php foreach ($exams as $exam): ?>
                        <option value="<?php echo $exam['id']; ?>" <?php echo ($filter_exam == $exam['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['exam_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="class" value="<?php echo $filter_class; ?>">
                <input type="submit" value="Terapkan">
            </form>
        </div>

        <div class="results-section">
            <?php if ($result->num_rows > 0): ?>
                <h3>
                    Hasil ulangan 
                    <?php 
                        if ($filter_class) echo "kelas $filter_class "; 
                        if ($filter_exam) {
                            foreach ($exams as $exam) {
                                if ($exam['id'] == $filter_exam) {
                                    echo "- ujian " . htmlspecialchars($exam['exam_name']);
                                    break;
                                }
                            }
                        }
                        if (!$filter_class && !$filter_exam) echo "untuk semua kelas dan semua ujian";
                    ?>
                </h3>
                
                <table>
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Ujian</th>
                            <th>Nilai</th>
                            <th>Total</th>
                            <th>Persen</th>
                            <th>Selesai pada</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): 
                            $percentage = round(($row['score'] / $row['total_questions']) * 100, 2);
                            $percentageClass = '';
                            if ($percentage >= 80) $percentageClass = 'percentage-high';
                            elseif ($percentage >= 60) $percentageClass = 'percentage-medium';
                            else $percentageClass = 'percentage-low';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['class']); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_name']); ?></td>
                            <td><?php echo $row['score']; ?></td>
                            <td><?php echo $row['total_questions']; ?></td>
                            <td class="percentage <?php echo $percentageClass; ?>"><?php echo $percentage; ?>%</td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['completed_at'])); ?></td>
                            <td>
                                <form action="detail_user_answers.php" method="get" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <input type="hidden" name="exam_id" value="<?php echo $row['exam_id']; ?>">
                                    <button type="submit" class="view-btn">Lihat Detail</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <p>Tidak ada ujian yang ditemukan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
