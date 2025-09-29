<?php 
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="profile">
            <div class="avatar">
                <img src="profile.jpeg" alt="admin avatar">
            </div>
            <h2><?php echo $_SESSION['name']; ?></h2>
            <p><?php echo $_SESSION['role']; ?></p>
        </div>
        <nav>
            <?php if ($_SESSION['role'] == 'user'): ?>
                <a href="select_exam.php">Kerjakan Ulangan</a>
                <a href="results.php">Lihat Hasil</a>
            <?php else: ?>
                <a href="manage_users.php">Kelola User</a>
                <a href="manage_exams.php">Kelola Ulangan</a>
                <a href="admin_results.php">Lihat semua hasil</a>
            <?php endif; ?>
            <a href="logout.php">Keluar</a>
        </nav>
    </aside>

  
    <main class="main-content">
        <header>
            <h1>Selamat Datang <?php echo $_SESSION['role']; ?>!</h1>
        </header>

        <section class="cards">
            <div class="card">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
                Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. 
                Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. </div>
            <div class="card">Consectetur adipiscing elit.</div>
          
        </section>
    </main>
</div>
</body>
</html>
