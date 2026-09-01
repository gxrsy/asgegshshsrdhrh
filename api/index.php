<?php
session_start();

// 1. DATABASE INITIALIZATION & SETUP
$db = new PDO('sqlite:forum.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create database tables automatically on first run
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        likes INTEGER DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        FOREIGN KEY(post_id) REFERENCES posts(id),
        FOREIGN KEY(user_id) REFERENCES users(id)
    );
");

// 2. FORM ACTION HANDLING
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Register User
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if (!empty($username) && !empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashedPassword]);
                $message = "Account created! You can now log in.";
            } catch (PDOException $e) {
                $message = "Username already exists.";
            }
        }
    }

    // Login User
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit;
        } else {
            $message = "Invalid username or password.";
        }
    }

    // Logout
    if ($action === 'logout') {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Create Post
    if ($action === 'create_post' && isset($_SESSION['user_id'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!empty($title) && !empty($content)) {
            $stmt = $db->prepare("INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $content]);
            header("Location: index.php");
            exit;
        }
    }

    // Create Comment
    if ($action === 'create_comment' && isset($_SESSION['user_id'])) {
        $postId = (int)$_POST['post_id'];
        $content = trim($_POST['content'] ?? '');
        if (!empty($content)) {
            $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$postId, $_SESSION['user_id'], $content]);
            header("Location: index.php");
            exit;
        }
    }

    // Like Reaction
    if ($action === 'like_post') {
        $postId = (int)$_POST['post_id'];
        $stmt = $db->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$postId]);
        header("Location: index.php");
        exit;
    }
}

// 3. FETCH DATA FOR DISPLAY
$postsStmt = $db->query("
    SELECT posts.*, users.username 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    ORDER BY posts.id DESC
");
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simple Forum</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px auto; max-width: 700px; background: #f4f4f9; color: #333; }
        .card { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2, h3 { margin-top: 0; }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 8px; margin: 5px 0 10px; box-sizing: border-box; }
        button { background: #007bff; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .author { font-size: 0.85em; color: #666; }
        .comment { background: #f9f9f9; padding: 8px; border-radius: 4px; margin-top: 8px; }
        .alert { background: #ffdddd; color: #a00; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .inline-form { display: inline; }
    </style>
</head>
<body>

    <h1>Single-File Forum</h1>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- User Auth Section -->
    <div class="card">
        <?php if (isset($_SESSION['user_id'])): ?>
            <p>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</p>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Log Out</button>
            </form>
        <?php else: ?>
            <h2>Login or Register</h2>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="action" value="login">Log In</button>
                <button type="submit" name="action" value="register" style="background: #28a745;">Register</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Create Post Section -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="card">
            <h2>Create a Post</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_post">
                <input type="text" name="title" placeholder="Post Title" required>
                <textarea name="content" rows="4" placeholder="Write something..." required></textarea>
                <button type="submit">Publish Post</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Posts Feed -->
    <h2>Forum Feed</h2>
    <?php foreach ($posts as $post): ?>
        <div class="card">
            <h3><?= htmlspecialchars($post['title']) ?></h3>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            <div class="author">Posted by <strong><?= htmlspecialchars($post['username']) ?></strong></div>
            
            <br>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="like_post">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <button type="submit" style="background: #6c757d;">❤️ <?= $post['likes'] ?> Likes</button>
            </form>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <h4>Comments</h4>
            <?php
            $commentsStmt = $db->prepare("
                SELECT comments.*, users.username 
                FROM comments 
                JOIN users ON comments.user_id = users.id 
                WHERE post_id = ? 
                ORDER BY comments.id ASC
            ");
            $commentsStmt->execute([$post['id']]);
            $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <strong><?= htmlspecialchars($comment['username']) ?>:</strong>
                    <?= htmlspecialchars($comment['content']) ?>
                </div>
            <?php endforeach; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="create_comment">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <input type="text" name="content" placeholder="Write a comment..." required>
                    <button type="submit" style="padding: 5px 10px;">Comment</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</body>
</html>
