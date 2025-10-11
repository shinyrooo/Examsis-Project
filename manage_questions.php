<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    echo "Ujian tidak ditemukan.";
    exit();
}

if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        $imported = 0;
        $skipped = 0;
        $stmt = $conn->prepare("SELECT COALESCE(MAX(number), 0) as last_number FROM questions WHERE exam_id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $last_number_result = $stmt->get_result()->fetch_assoc();
        $current_number = $last_number_result['last_number'] + 1; 
        $stmt->close();
        $firstRow = fgetcsv($handle, 1000, ",");
        if ($firstRow && stripos(implode(",", $firstRow), "question") !== false) {} else { rewind($handle); }
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 7) {
                $data = str_getcsv(implode(",", $data), ";");
            }
            if (count($data) >= 7) { 
                $question_text  = trim($data[0]);
                $option_a       = trim($data[1]);
                $option_b       = trim($data[2]);
                $option_c       = trim($data[3]);
                $option_d       = trim($data[4]);
                $option_e       = trim($data[5]); 
                $correct_answer = strtoupper(trim($data[6])); 
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'])) {
                    $skipped++;
                    continue;
                }
                $stmt = $conn->prepare("INSERT INTO questions 
                    (exam_id, number, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"); 
                $stmt->bind_param("iisssssss", 
                    $exam_id, 
                    $current_number, 
                    $question_text, 
                    $option_a, 
                    $option_b, 
                    $option_c, 
                    $option_d, 
                    $option_e, 
                    $correct_answer
                );
                if ($stmt->execute()) {
                    $imported++;
                    $current_number++; 
                } else {
                    $skipped++;
                }
                $stmt->close();
            } else {
                $skipped++;
            }
        }
        fclose($handle);
        $import_message = "Imported: $imported Soal, Skipped: $skipped Soal";
    } else {
        $import_error = "Tolong pilih file CSV yang benar";
    }
}


if (isset($_POST['add_question'])) {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(number), 0) + 1 as next_number FROM questions WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $next_number_result = $stmt->get_result()->fetch_assoc();
    $next_number = $next_number_result['next_number'];
    $stmt->close();
    $question_text = sanitize_input($_POST['question_text']);
    $option_a = sanitize_input($_POST['option_a']);
    $option_b = sanitize_input($_POST['option_b']);
    $option_c = sanitize_input($_POST['option_c']);
    $option_d = sanitize_input($_POST['option_d']);
    $option_e = sanitize_input($_POST['option_e']);
    $correct_answer = sanitize_input($_POST['correct_answer']);
    $stmt = $conn->prepare("INSERT INTO questions (exam_id, number, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $exam_id, $next_number, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_answer);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_questions.php?exam_id=" . $exam_id);
    exit();
}

if (isset($_POST['edit_question'])) {
    $question_id = intval($_POST['question_id']);
    $question_text = sanitize_input($_POST['question_text']);
    $option_a = sanitize_input($_POST['option_a']);
    $option_b = sanitize_input($_POST['option_b']);
    $option_c = sanitize_input($_POST['option_c']);
    $option_d = sanitize_input($_POST['option_d']);
    $option_e = sanitize_input($_POST['option_e']);
    $correct_answer = sanitize_input($_POST['correct_answer']);
    $stmt = $conn->prepare("UPDATE questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, option_e = ?, correct_answer = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_answer, $question_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_questions.php?exam_id=" . $exam_id);
    exit();
}

if (isset($_GET['delete_question'])) {
    $question_id = intval($_GET['delete_question']);
    $stmt = $conn->prepare("SELECT number FROM questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $question_to_delete = $stmt->get_result()->fetch_assoc();
    $deleted_number = $question_to_delete['number'];
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE questions SET number = number - 1 WHERE exam_id = ? AND number > ?");
    $stmt->bind_param("ii", $exam_id, $deleted_number);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_questions.php?exam_id=" . $exam_id);
    exit();
}

function reorderQuestions($conn, $exam_id) {
    $stmt = $conn->prepare("SELECT id FROM questions WHERE exam_id = ? ORDER BY number");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $number = 1;
    foreach ($questions as $question) {
        $stmt = $conn->prepare("UPDATE questions SET number = ? WHERE id = ?");
        $stmt->bind_param("ii", $number, $question['id']);
        $stmt->execute();
        $stmt->close();
        $number++;
    }
}

$stmt = $conn->prepare("
    SELECT COUNT(*) as gap_count 
    FROM questions q1 
    WHERE exam_id = ? AND NOT EXISTS (
        SELECT 1 FROM questions q2 
        WHERE q2.exam_id = q1.exam_id AND q2.number = q1.number - 1
    ) AND q1.number > 1
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$gap_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($gap_result['gap_count'] > 0) {
    reorderQuestions($conn, $exam_id);
}

$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY number");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examsis soal</title>
    <link rel="stylesheet" href="css/manage_questions.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Kelola Soal</h1>
            <div>
                <a href="manage_exams.php" class="btn btn-secondary">← Kembali ke kelola ujian</a>
                <a href="dashboard.php" class="btn btn-secondary">← Kembali ke Dashboard</a>
            </div>
        </div>
        <div class="section">
            <h2>Ujian: <?php echo htmlspecialchars($exam['exam_name']); ?></h2>
            <p><strong>Kelas:</strong> <?php echo htmlspecialchars($exam['class']); ?> | 
               <strong>Waktu:</strong> <?php echo $exam['duration_minutes']; ?> menit</p>
        </div>
        <?php if (isset($import_message)): ?>
            <div class="message success"><?php echo $import_message; ?></div>
        <?php endif; ?>
        <?php if (isset($import_error)): ?>
            <div class="message error"><?php echo $import_error; ?></div>
        <?php endif; ?>
<div class="section import-section">
    <h2>Import soal dari CSV</h2>
    <form method="post" enctype="multipart/form-data" id="csvForm">
        <div class="form-group">
            <label for="csv_file">Pilih file CSV:</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv" required 
                   class="file-input" onchange="showFileName()">
            <span id="fileName" class="file-name">Belum ada file</span>
        </div>
        <button type="submit" name="import_csv" id="importBtn" class="btn btn-primary">Import</button>
    </form>
</div>

<div class="section" id="questionForm">
            <h2 id="formTitle">Tambah soal baru</h2>
            <form method="post">
                <input type="hidden" name="question_id" id="question_id">
                <div class="form-group">
                    <label for="question_text">Soal:</label>
                    <textarea id="question_text" name="question_text" required></textarea>
                </div>
                <div class="form-group">
                    <label for="option_a">Pilihan A:</label>
                    <input type="text" id="option_a" name="option_a" required>
                </div>
                <div class="form-group">
                    <label for="option_b">Pilihan B:</label>
                    <input type="text" id="option_b" name="option_b" required>
                </div>
                <div class="form-group">
                    <label for="option_c">Pilihan C:</label>
                    <input type="text" id="option_c" name="option_c" required>
                </div>
                <div class="form-group">
                    <label for="option_d">Pilihan D:</label>
                    <input type="text" id="option_d" name="option_d" required>
                </div>
                <div class="form-group">
                    <label for="option_e">Pilihan E:</label> 
                    <input type="text" id="option_e" name="option_e" required>
                </div>
                <div class="form-group">
                    <label for="correct_answer">Jawaban yang benar:</label>
                    <select id="correct_answer" name="correct_answer" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
                <button type="submit" id="submitBtn" name="add_question" class="btn btn-primary">Tambahkan Soal</button>
                <button type="button" id="cancelBtn" onclick="resetForm()" class="btn btn-secondary" style="display:none;">Batalkan</button>
            </form>
</div>
        <div class="section">
            <h2>soal yang tersedia (<?php echo count($questions); ?>)</h2>
            <?php if (count($questions) > 0): ?>
                <table class="questions-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Soal</th>
                            <th>Pilihan</th>
                            <th>Jawaban yang benar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo $question['number']; ?></td>
                            <td><div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div></td>
                            <td>
                                <ul class="options-list">
                                    <li><strong>A:</strong> <?php echo htmlspecialchars($question['option_a']); ?></li>
                                    <li><strong>B:</strong> <?php echo htmlspecialchars($question['option_b']); ?></li>
                                    <li><strong>C:</strong> <?php echo htmlspecialchars($question['option_c']); ?></li>
                                    <li><strong>D:</strong> <?php echo htmlspecialchars($question['option_d']); ?></li>
                                    <li><strong>E:</strong> <?php echo htmlspecialchars($question['option_e']); ?></li>
                                </ul>
                            </td>
                            <td><span class="correct-answer"><?php echo $question['correct_answer']; ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="showEditForm(<?php echo $question['id']; ?>, '<?php echo addslashes($question['question_text']); ?>', '<?php echo addslashes($question['option_a']); ?>', '<?php echo addslashes($question['option_b']); ?>', '<?php echo addslashes($question['option_c']); ?>', '<?php echo addslashes($question['option_d']); ?>', '<?php echo addslashes($question['option_e']); ?>', '<?php echo $question['correct_answer']; ?>')" 
                                            class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                        Edit
                                    </button>
                                    <a href="?exam_id=<?php echo $exam_id; ?>&delete_question=<?php echo $question['id']; ?>" 
                                       class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;"
                                       onclick="return confirm('Delete this question?')">
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Tidak ada soal yang ditemukan</p>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function showEditForm(questionId, questionText, optionA, optionB, optionC, optionD, optionE, correctAnswer) { 
        document.getElementById('question_id').value = questionId;
        document.getElementById('question_text').value = questionText;
        document.getElementById('option_a').value = optionA;
        document.getElementById('option_b').value = optionB;
        document.getElementById('option_c').value = optionC;
        document.getElementById('option_d').value = optionD;
        document.getElementById('option_e').value = optionE; 
        document.getElementById('correct_answer').value = correctAnswer;
        document.getElementById('formTitle').innerText = "Edit Soal";
        document.getElementById('submitBtn').innerText = "Perbarui";
        document.getElementById('submitBtn').name = "edit_question";
        document.getElementById('cancelBtn').style.display = "inline-block";
        document.getElementById('questionForm').scrollIntoView({ behavior: 'smooth' });
    }
    function resetForm() {
        document.getElementById('question_id').value = "";
        document.getElementById('question_text').value = "";
        document.getElementById('option_a').value = "";
        document.getElementById('option_b').value = "";
        document.getElementById('option_c').value = "";
        document.getElementById('option_d').value = "";
        document.getElementById('option_e').value = ""; 
        document.getElementById('correct_answer').value = "A";
        document.getElementById('formTitle').innerText = "Tambah soal baru";
        document.getElementById('submitBtn').innerText = "Tambahkan Soal";
        document.getElementById('submitBtn').name = "add_question";
        document.getElementById('cancelBtn').style.display = "none";
        document.getElementById('questionForm').scrollIntoView({ behavior: 'smooth' });
    }

    function showFileName() {
        const input = document.getElementById('csv_file');
        const fileName = input.files.length > 0 ? input.files[0].name : "Belum ada file";
        document.getElementById('fileName').innerText = fileName;
        document.getElementById('importBtn').disabled = (input.files.length === 0);
    }

    <?php if (isset($import_message) || isset($import_error)): ?>
        document.getElementById('csv_file').value = "";
        document.getElementById('fileName').innerText = "Belum ada file";
    <?php endif; ?>
    </script>
</body>
</html>