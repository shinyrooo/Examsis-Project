<?php 
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['exam_id'])) {
    header("Location: exam_selection.php");
    exit();
}

$exam_id = intval($_GET['exam_id']);

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND class = ?");
$stmt->bind_param("is", $exam_id, $_SESSION['class']);
$stmt->execute();
$exam_result = $stmt->get_result();

if ($exam_result->num_rows == 0) {
    echo "Ujian tidak ditemukan.";
    exit();
}

$exam = $exam_result->fetch_assoc();
$exam_duration = $exam['duration_minutes'] * 60;
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM exam_results WHERE user_id = ? AND exam_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Kamu sudah menyelesaikan ujian ini. <a href='results.php'>View Results</a>";
    exit();
}
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY number");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);
$total_questions = count($questions);
$stmt->close();

if ($total_questions == 0) {
    echo "Pertanyaan belum tersedia untuk ujian ini.";
    exit();
}

if (!isset($_SESSION['exam_start_time']) || $_SESSION['exam_id'] != $exam_id) {
    $_SESSION['exam_start_time'] = time();
    $_SESSION['exam_duration'] = $exam_duration;
    $_SESSION['exam_id'] = $exam_id;
    $_SESSION['user_answers'] = array_fill(1, $total_questions, '');
}

$time_elapsed = time() - $_SESSION['exam_start_time'];
$time_left = max(0, $_SESSION['exam_duration'] - $time_elapsed);

if ($time_left <= 0) {
    $score = 0;
    
    foreach ($questions as $index => $question) {
        $question_num = $index + 1;
        $user_answer = $_SESSION['user_answers'][$question_num];
        
        if (!empty($user_answer)) {
            $is_correct = ($user_answer == $question['correct_answer']);
            if ($is_correct) $score++;
            
            $stmt = $conn->prepare("INSERT INTO user_answers (user_id, exam_id, question_id, user_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisi", $_SESSION['user_id'], $exam_id, $question['id'], $user_answer, $is_correct);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO exam_results (user_id, exam_id, score, total_questions) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $_SESSION['user_id'], $exam_id, $score, $total_questions);
    $stmt->execute();
    $stmt->close();
    
    unset($_SESSION['exam_start_time']);
    unset($_SESSION['exam_duration']);
    unset($_SESSION['exam_id']);
    unset($_SESSION['user_answers']);
    
    echo "<h2>Waktu sudah habis!</h2>";
    echo "<p>Nilai kamu: $score/$total_questions</p>";
    echo "<a href='results.php'>View Results</a>";
    exit();
}

$current_question = isset($_GET['q']) ? max(1, min($total_questions, intval($_GET['q']))) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['answer'])) {
        $_SESSION['user_answers'][$current_question] = $_POST['answer'];
    }
    
    if (isset($_POST['next']) && $current_question < $total_questions) {
        $current_question++;
        header("Location: take_exam.php?exam_id=$exam_id&q=$current_question");
        exit();
    } elseif (isset($_POST['prev']) && $current_question > 1) {
        $current_question--;
        header("Location: take_exam.php?exam_id=$exam_id&q=$current_question");
        exit();
    } elseif (isset($_POST['goto_question'])) {
        $goto_question = intval($_POST['goto_question']);
        if ($goto_question >= 1 && $goto_question <= $total_questions) {
            header("Location: take_exam.php?exam_id=$exam_id&q=$goto_question");
            exit();
        }
    } elseif (isset($_POST['submit_exam'])) {
        $score = 0;
        
        foreach ($questions as $index => $question) {
            $question_num = $index + 1;
            $user_answer = $_SESSION['user_answers'][$question_num];
            
            if (!empty($user_answer)) {
                $is_correct = ($user_answer == $question['correct_answer']);
                if ($is_correct) $score++;
                
                $stmt = $conn->prepare("INSERT INTO user_answers (user_id, exam_id, question_id, user_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisi", $_SESSION['user_id'], $exam_id, $question['id'], $user_answer, $is_correct);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO exam_results (user_id, exam_id, score, total_questions) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $_SESSION['user_id'], $exam_id, $score, $total_questions);
        $stmt->execute();
        $stmt->close();
        
        unset($_SESSION['exam_start_time']);
        unset($_SESSION['exam_duration']);
        unset($_SESSION['exam_id']);
        unset($_SESSION['user_answers']);
        
        echo "<h2>Exam Submitted</h2>";
        echo "<p>Your score: $score/$total_questions</p>";
        echo "<a href='results.php'>Lihat hasil</a>";
        exit();
    }
}

if (isset($_GET['q'])) {
    $current_question = max(1, min($total_questions, intval($_GET['q'])));
}

$current_question_data = $questions[$current_question - 1];
$user_answer = $_SESSION['user_answers'][$current_question];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Take Exam - <?php echo $exam['exam_name']; ?></title>
     <link rel="stylesheet" href="css/take_exam.css">
    <script>
        var timeLeft = <?php echo $time_left; ?>;
        
        function startTimer() {
            setInterval(function() {
                timeLeft--;
                var minutes = Math.floor(timeLeft / 60);
                var seconds = timeLeft % 60;
                
                document.getElementById('timer').innerHTML = 
                    minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                
                if (timeLeft <= 0) {
                    document.getElementById('examForm').submit();
                }
            }, 1000);
        }
        
        function selectOption(optionValue) {
            var radios = document.getElementsByName('answer');
            for (var i = 0; i < radios.length; i++) {
                radios[i].checked = (radios[i].value === optionValue);
            }
            
            var optionItems = document.getElementsByClassName('option-item');
            for (var i = 0; i < optionItems.length; i++) {
                optionItems[i].classList.remove('selected');
            }
            
            var selectedItem = document.querySelector('.option-item[data-value="' + optionValue + '"]');
            if (selectedItem) {
                selectedItem.classList.add('selected');
            }
        }
        
        function goToQuestion(questionNum) {
            document.getElementById('goto_question').value = questionNum;
            document.getElementById('examForm').submit();
        }
        
        window.onload = function() {
            startTimer();
            var currentAnswer = '<?php echo $user_answer; ?>';
            if (currentAnswer) {
                selectOption(currentAnswer);
            }
        };
    </script>
</head>
<body>
    <div class="header">
        <div class="exam-title"><?php echo $exam['exam_name']; ?></div>
        <div class="timer-box">
            <div class="timer" id="timer">
                <?php echo floor($time_left / 60); ?>:<?php echo str_pad($time_left % 60, 2, '0', STR_PAD_LEFT); ?>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="question-column">
            <div class="question-box">
                <div class="question-header">
                    Soal <?php echo $current_question; ?> dari <?php echo $total_questions; ?>
                </div>
                <div class="question-text">
                    <?php echo $current_question_data['question_text']; ?>
                </div>
            </div>
        </div>
        
        <div class="options-column">
            <form id="examForm" method="post">
                <input type="hidden" name="question_num" value="<?php echo $current_question; ?>">
                <input type="hidden" name="goto_question" id="goto_question" value="">
                
                <input type="radio" name="answer" value="A" <?php echo ($user_answer == 'A') ? 'checked' : ''; ?>>
                <input type="radio" name="answer" value="B" <?php echo ($user_answer == 'B') ? 'checked' : ''; ?>>
                <input type="radio" name="answer" value="C" <?php echo ($user_answer == 'C') ? 'checked' : ''; ?>>
                <input type="radio" name="answer" value="D" <?php echo ($user_answer == 'D') ? 'checked' : ''; ?>>
                
                <div class="option-item <?php echo ($user_answer == 'A') ? 'selected' : ''; ?>" data-value="A" onclick="selectOption('A')">
                    <div class="option-circle">A</div>
                    <div class="option-text"><?php echo $current_question_data['option_a']; ?></div>
                </div>
                
                <div class="option-item <?php echo ($user_answer == 'B') ? 'selected' : ''; ?>" data-value="B" onclick="selectOption('B')">
                    <div class="option-circle">B</div>
                    <div class="option-text"><?php echo $current_question_data['option_b']; ?></div>
                </div>
                
                <div class="option-item <?php echo ($user_answer == 'C') ? 'selected' : ''; ?>" data-value="C" onclick="selectOption('C')">
                    <div class="option-circle">C</div>
                    <div class="option-text"><?php echo $current_question_data['option_c']; ?></div>
                </div>
                
                <div class="option-item <?php echo ($user_answer == 'D') ? 'selected' : ''; ?>" data-value="D" onclick="selectOption('D')">
                    <div class="option-circle">D</div>
                    <div class="option-text"><?php echo $current_question_data['option_d']; ?></div>
                </div>
                
                <div class="nav-buttons">
                    <?php if ($current_question > 1): ?>
                        <button type="submit" name="prev" class="nav-btn">Sebelumnya</button>
                    <?php endif; ?>
                    
                    <?php if ($current_question < $total_questions): ?>
                        <button type="submit" name="next" class="nav-btn">Selanjutnya</button>
                    <?php else: ?>
                        <button type="submit" name="submit_exam" class="nav-btn">Kirimkan</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="question-nav">
        <div class="question-nav-container">
            <?php for ($i = 1; $i <= $total_questions; $i++): 
                $answered = !empty($_SESSION['user_answers'][$i]);
                $class = $answered ? 'answered' : 'unanswered';
                if ($i == $current_question) $class = 'current';
            ?>
                <div class="question-nav-item <?php echo $class; ?>" onclick="goToQuestion(<?php echo $i; ?>)">
                    <?php echo $i; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>