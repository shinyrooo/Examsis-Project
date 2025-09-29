<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_GET['user_id']);
$exam_id = intval($_GET['exam_id']);

if ($_SESSION['role'] == 'user' && $user_id != $_SESSION['user_id']) {
    header("Location: unauthorized.php");
    exit();
}

$stmt = $conn->prepare("SELECT u.username, u.class, e.exam_name FROM users u, exams e WHERE u.id = ? AND e.id = ?");
$stmt->bind_param("ii", $user_id, $exam_id);
$stmt->execute();
$details = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, 
                       q.correct_answer, ua.user_answer, ua.is_correct 
                       FROM user_answers ua 
                       JOIN questions q ON ua.question_id = q.id 
                       WHERE ua.user_id = ? AND ua.exam_id = ?");
$stmt->bind_param("ii", $user_id, $exam_id);
$stmt->execute();
$answers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Answers</title>
    <link rel="stylesheet" href="css/detail_user_answers.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Detail Jawaban User</h1>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="admin_results.php" class="back-btn">← Kembali</a>
            <?php else: ?>
                <a href="results.php" class="back-btn">← Kembali</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <h2>Informasi Jawaban Siswa</h2>
            <div class="user-details">
                <div class="detail-item">
                    <strong>Username:</strong> <?php echo htmlspecialchars($details['username']); ?>
                </div>
                <div class="detail-item">
                    <strong>Kelas:</strong> <?php echo htmlspecialchars($details['class']); ?>
                </div>
                <div class="detail-item">
                    <strong>Ujian:</strong> <?php echo htmlspecialchars($details['exam_name']); ?>
                </div>
            </div>
        </div>
        
        <div class="answers-section">
            <table class="answers-table">
                <thead>
                    <tr>
                        <th width="25%">Pertanyaan</th>
                        <th width="30%">Pilihan</th>
                        <th width="10%">Jawaban Siswa</th>
                        <th width="10%">Jawaban benar</th>
                        <th width="10%">Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($answers as $index => $answer): ?>
                    <tr>
                        <td>
                            <div class="question-text">Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></div>
                        </td>
                        <td>
                            <ul class="options-list">
                                <li><strong>A:</strong> <?php echo htmlspecialchars($answer['option_a']); ?></li>
                                <li><strong>B:</strong> <?php echo htmlspecialchars($answer['option_b']); ?></li>
                                <li><strong>C:</strong> <?php echo htmlspecialchars($answer['option_c']); ?></li>
                                <li><strong>D:</strong> <?php echo htmlspecialchars($answer['option_d']); ?></li>
                            </ul>
                        </td>
                        <td>
                            <span class="user-answer"><?php echo htmlspecialchars($answer['user_answer']); ?></span>
                        </td>
                        <td>
                            <span class="correct-answer"><?php echo htmlspecialchars($answer['correct_answer']); ?></span>
                        </td>
                        <td>
                            <span class="result-status <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                <?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>