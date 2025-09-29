<?php 
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_GET['user_id']);
$exam_id = intval($_GET['exam_id']);

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
</head>
<body>
    <h2>User Answers: <?php echo $details['username']; ?> (<?php echo $details['class']; ?>)</h2>
    <h3>Exam: <?php echo $details['exam_name']; ?></h3>
    
    <table border="1">
        <tr>
            <th>Question</th>
            <th>Options</th>
            <th>User Answer</th>
            <th>Correct Answer</th>
            <th>Result</th>
        </tr>
        <?php foreach ($answers as $answer): ?>
        <tr>
            <td><?php echo $answer['question_text']; ?></td>
            <td>
                A: <?php echo $answer['option_a']; ?><br>
                B: <?php echo $answer['option_b']; ?><br>
                C: <?php echo $answer['option_c']; ?><br>
                D: <?php echo $answer['option_d']; ?>
            </td>
            <td><?php echo $answer['user_answer']; ?></td>
            <td><?php echo $answer['correct_answer']; ?></td>
            <td><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <br>
    <a href="admin_results.php">Back to Results</a>
</body>
</html>